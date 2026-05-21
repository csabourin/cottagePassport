/**
 * Stamp Passport — Frontend JavaScript
 *
 * Handles IndexedDB storage, QR-based stamp collection,
 * geofence validation, progress tracking, GA4 events,
 * Freeform modal triggers, and cross-domain contest progress sync.
 *
 * Expects window.__PASSPORT_CONFIG__ to be set by the Twig layout.
 */
(function () {
    'use strict';

    var CFG = window.__PASSPORT_CONFIG__ || {};
    var TXT = CFG.text || {};

    /* ── Selectors ── */
    var el  = function (id) { return document.getElementById(id); };
    var qs  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var qsa = function (sel, ctx) { return (ctx || document).querySelectorAll(sel); };

    /* ── State ── */
    var db = null;
    var itemsData = [];         // Loaded from API
    var currentItem = null;     // Resolved from ?q= param
    var contestCid = null;      // Contest participant ID (UUID v4)
    var contestWriteToken = null; // Session-bound anonymous write capability

    /* ── Focusable elements query (for focus trap) ── */
    var FOCUSABLE_SELECTOR = 'a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])';

    /* ═══════════════════════════════════════════
       IndexedDB + localStorage fallback
       ═══════════════════════════════════════════ */

    var DB_NAME    = 'stamp-passport';
    var DB_VERSION = 2;
    var LS_PREFIX  = 'passportData:';
    var LANG_LS_KEY = 'passport:langChoice';
    var LANG_META_KEY = 'langChoice';

    function initDB() {
        return new Promise(function (resolve) {
            try {
                var req = indexedDB.open(DB_NAME, DB_VERSION);
                req.onupgradeneeded = function () {
                    var d = req.result;
                    if (!d.objectStoreNames.contains('stamps'))
                        d.createObjectStore('stamps', { keyPath: 'shortCode' });
                    if (!d.objectStoreNames.contains('meta'))
                        d.createObjectStore('meta', { keyPath: 'key' });
                    if (!d.objectStoreNames.contains('state'))
                        d.createObjectStore('state', { keyPath: 'key' });
                };
                req.onsuccess = function () { db = req.result; resolve(); };
                req.onerror   = function () { db = null; resolve(); };
            } catch (e) { db = null; resolve(); }
        });
    }

    function idbReq(req) {
        return new Promise(function (resolve, reject) {
            req.onsuccess = function () { resolve(req.result); };
            req.onerror   = function () { reject(req.error); };
        });
    }

    function store(name, mode) {
        return db.transaction(name, mode || 'readonly').objectStore(name);
    }

    /* localStorage helpers */
    function safeLsGet(key) {
        try { return localStorage.getItem(key); } catch (e) { return null; }
    }

    function safeLsSet(key, value) {
        try { localStorage.setItem(key, value); return true; } catch (e) { return false; }
    }

    function safeLsRemove(key) {
        try { localStorage.removeItem(key); } catch (e) {}
    }

    function lsWrite(ns, key, data) {
        safeLsSet(LS_PREFIX + ns + ':' + key, JSON.stringify(data));
    }
    function lsRead(ns, key) {
        try {
            var raw = safeLsGet(LS_PREFIX + ns + ':' + key);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    /* Dual-storage accessors */
    async function getStamp(shortCode) {
        if (db) { try { var r = await idbReq(store('stamps').get(shortCode)); if (r) return r; } catch (e) {} }
        return lsRead('stamps', shortCode);
    }

    async function putStamp(data) {
        lsWrite('stamps', data.shortCode, data);
        if (db) { try { await idbReq(store('stamps', 'readwrite').put(data)); } catch (e) { db = null; } }
    }

    async function getMeta(key) {
        if (db) { try { var r = await idbReq(store('meta').get(key)); if (r) return r; } catch (e) {} }
        return lsRead('meta', key);
    }

    async function putMeta(data) {
        lsWrite('meta', data.key, data);
        if (db) { try { await idbReq(store('meta', 'readwrite').put(data)); } catch (e) { db = null; } }
    }

    function normalizeLang(lang) {
        return ((lang || '').substring(0, 2)).toLowerCase();
    }

    async function persistLanguageChoice(lang) {
        var normalized = normalizeLang(lang);
        if (!normalized) return;

        safeLsSet(LANG_LS_KEY, normalized);

        if (db) {
            try {
                await idbReq(store('meta', 'readwrite').put({
                    key: LANG_META_KEY,
                    value: normalized,
                    updatedAt: new Date().toISOString()
                }));
            } catch (e) {}
        }
    }

    /* ═══════════════════════════════════════════
       GA4 event helper
       ═══════════════════════════════════════════ */

    function ga4Event(name, params) {
        if (typeof gtag === 'function') {
            gtag('event', name, params || {});
        }
    }

    /* ═══════════════════════════════════════════
       API helpers
       ═══════════════════════════════════════════ */

    async function fetchLocations() {
        var res = await fetch(CFG.locationsUrl, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('Failed to load items. (HTTP ' + res.status + ')');
        var data = await res.json();
        if (!data.success) throw new Error(data.error || 'Failed to load items.');
        return data.items || [];
    }

    async function apiCollect(shortCode, lat, lng) {
        var res = await fetch(CFG.collectUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ shortCode: shortCode, latitude: lat, longitude: lng }),
        });
        try { return await res.json(); } catch (e) {
            return { success: false, error: 'Server error (' + res.status + ')' };
        }
    }

    async function apiResolve(shortCode) {
        var res = await fetch(CFG.resolveUrl + '?q=' + encodeURIComponent(shortCode), {
            headers: { Accept: 'application/json' },
        });
        try { return await res.json(); } catch (e) {
            return { success: false, error: 'Server error (' + res.status + ')' };
        }
    }

    /* ═══════════════════════════════════════════
       URL / QR helpers
       ═══════════════════════════════════════════ */

    function getShortCodeFromUrl() {
        return new URL(window.location.href).searchParams.get('q') || '';
    }

    function findItemByCode(code) {
        return itemsData.find(function (i) { return i.shortCode === code; }) || null;
    }

    function isKnownItemCode(code) {
        return !!findItemByCode(code);
    }

    /* ═══════════════════════════════════════════
       Contest Progress — UUID & CID Management
       ═══════════════════════════════════════════ */

    var CONTEST_VERSION = CFG.contestVersion || '2026.02';

    function generateUUID() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function isValidUUID(str) {
        return /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(str);
    }

    function initCid() {
        var params = new URL(window.location.href).searchParams;
        var urlCid = params.get('cid');

        // If user arrived with ?lang=, record explicit language choice
        var urlLang = params.get('lang');
        if (urlLang) {
            persistLanguageChoice(urlLang).catch(function () {});
        }

        if (urlCid && isValidUUID(urlCid)) {
            persistCid(urlCid);
            cleanUrlParams(['cid', 'lang']);
            return urlCid;
        }

        var stored = null;
        stored = safeLsGet('contest:cid');
        if (stored && isValidUUID(stored)) {
            return stored;
        }

        var newCid = generateUUID();
        persistCid(newCid);
        return newCid;
    }

    function persistCid(cid) {
        safeLsSet('contest:cid', cid);
    }

    function cleanUrlParams(keys) {
        var url = new URL(window.location.href);
        var changed = false;
        keys.forEach(function (k) {
            if (url.searchParams.has(k)) {
                url.searchParams.delete(k);
                changed = true;
            }
        });
        if (changed) {
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        }
    }

    /* ═══════════════════════════════════════════
       Contest Progress — Payload Builder
       ═══════════════════════════════════════════ */

    async function buildPayload() {
        var stepsCompleted = [];
        var stampTimestamps = {};

        for (var i = 0; i < itemsData.length; i++) {
            var s = await getStamp(itemsData[i].shortCode);
            if (s) {
                stepsCompleted.push(s.shortCode);
                stampTimestamps[s.shortCode] = s.collectedAt || new Date().toISOString();
            }
        }

        return {
            schemaVersion: 1,
            contestVersion: CONTEST_VERSION,
            progress: {
                stepsCompleted: stepsCompleted,
                answers: {},
                score: stepsCompleted.length,
                badges: [],
                custom: {
                    stampTimestamps: stampTimestamps,
                    formStates: {
                        drawSubmitted:    safeLsGet('passportDrawSubmitted')   === '1',
                        drawDismissed:    safeLsGet('passportDrawDismissed')   === '1',
                        stickerSubmitted: safeLsGet('passportStickerSubmitted') === '1',
                        stickerDismissed: safeLsGet('passportStickerDismissed') === '1'
                    }
                }
            },
            updatedAt: new Date().toISOString()
        };
    }

    /* ═══════════════════════════════════════════
       Contest Progress — Remote API
       ═══════════════════════════════════════════ */

    async function fetchRemoteProgress(cid) {
        var res = await fetch(CFG.contestProgressUrl + '?cid=' + encodeURIComponent(cid), {
            headers: { Accept: 'application/json' }
        });
        var data = await res.json();
        if (data && typeof data.writeToken === 'string' && data.writeToken) {
            contestWriteToken = data.writeToken;
        }
        return data;
    }

    async function pushRemoteProgress(cid, payload, clientRevision) {
        if (!contestWriteToken) {
            try {
                await fetchRemoteProgress(cid);
            } catch (e) {
                // If we cannot hydrate a token right now, let the write fail gracefully.
            }
        }

        async function sendWrite() {
            var res = await fetch(CFG.contestProgressUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    cid: cid,
                    payload: payload,
                    clientRevision: clientRevision,
                    writeToken: contestWriteToken || ''
                })
            });
            var data = await res.json();
            if (data && typeof data.writeToken === 'string' && data.writeToken) {
                contestWriteToken = data.writeToken;
            }
            return data;
        }

        var data = await sendWrite();
        if (data && (data.error === 'missing_write_token' || data.error === 'invalid_write_token' || data.error === 'expired_write_token')) {
            if (contestWriteToken) {
                data = await sendWrite();
            }
        }

        return data;
    }

    /* ═══════════════════════════════════════════
       Contest Progress — Merge Logic
       ═══════════════════════════════════════════ */

    function mergePayloads(local, server) {
        var localSteps = (local.progress && local.progress.stepsCompleted) || [];
        var serverSteps = (server.progress && server.progress.stepsCompleted) || [];
        var localTs = (local.progress && local.progress.custom && local.progress.custom.stampTimestamps) || {};
        var serverTs = (server.progress && server.progress.custom && server.progress.custom.stampTimestamps) || {};

        var allSteps = {};
        var mergedTs = {};

        var i;
        for (i = 0; i < localSteps.length; i++) {
            allSteps[localSteps[i]] = true;
            mergedTs[localSteps[i]] = localTs[localSteps[i]];
        }
        for (i = 0; i < serverSteps.length; i++) {
            var sc = serverSteps[i];
            allSteps[sc] = true;
            if (!mergedTs[sc]) {
                mergedTs[sc] = serverTs[sc] || new Date().toISOString();
            } else if (serverTs[sc] && serverTs[sc] < mergedTs[sc]) {
                mergedTs[sc] = serverTs[sc];
            }
        }

        var mergedSteps = Object.keys(allSteps);

        /* Merge form states — submitted is permanent and overrides dismissed */
        var localFs  = (local.progress  && local.progress.custom  && local.progress.custom.formStates)  || {};
        var serverFs = (server.progress && server.progress.custom && server.progress.custom.formStates) || {};

        var drawSubmitted    = !!(localFs.drawSubmitted    || serverFs.drawSubmitted);
        var stickerSubmitted = !!(localFs.stickerSubmitted || serverFs.stickerSubmitted);
        var drawDismissed    = !drawSubmitted    && !!(localFs.drawDismissed    || serverFs.drawDismissed);
        var stickerDismissed = !stickerSubmitted && !!(localFs.stickerDismissed || serverFs.stickerDismissed);

        var mergedFs = {
            drawSubmitted:    drawSubmitted,
            drawDismissed:    drawDismissed,
            stickerSubmitted: stickerSubmitted,
            stickerDismissed: stickerDismissed
        };

        var serverFs2 = (server.progress && server.progress.custom && server.progress.custom.formStates) || {};
        var fsChanged = mergedFs.drawSubmitted    !== !!serverFs2.drawSubmitted    ||
                        mergedFs.drawDismissed    !== !!serverFs2.drawDismissed    ||
                        mergedFs.stickerSubmitted !== !!serverFs2.stickerSubmitted ||
                        mergedFs.stickerDismissed !== !!serverFs2.stickerDismissed;

        var changed = fsChanged ||
            mergedSteps.length !== serverSteps.length ||
            mergedSteps.some(function (s) { return serverSteps.indexOf(s) === -1; });

        return {
            changed: changed,
            payload: {
                schemaVersion: 1,
                contestVersion: CONTEST_VERSION,
                progress: {
                    stepsCompleted: mergedSteps,
                    answers: {},
                    score: mergedSteps.length,
                    badges: [],
                    custom: {
                        stampTimestamps: mergedTs,
                        formStates: mergedFs
                    }
                },
                updatedAt: new Date().toISOString()
            }
        };
    }

    async function applyPayloadLocally(payload) {
        var steps = (payload.progress && payload.progress.stepsCompleted) || [];
        var ts = (payload.progress && payload.progress.custom && payload.progress.custom.stampTimestamps) || {};

        for (var i = 0; i < steps.length; i++) {
            var code = steps[i];
            if (!isKnownItemCode(code)) continue;
            var existing = await getStamp(code);
            if (!existing) {
                await putStamp({
                    shortCode: code,
                    collectedAt: ts[code] || new Date().toISOString()
                });
            }
        }

        /* Apply form states — submitted is sticky; dismissed only applied when
           the form has not already been submitted locally. */
        var fs = (payload.progress && payload.progress.custom && payload.progress.custom.formStates) || {};

        if (fs.drawSubmitted) {
            safeLsSet('passportDrawSubmitted', '1');
            safeLsRemove('passportDrawDismissed');
        } else if (fs.drawDismissed && safeLsGet('passportDrawSubmitted') !== '1') {
            safeLsSet('passportDrawDismissed', '1');
        }

        if (fs.stickerSubmitted) {
            safeLsSet('passportStickerSubmitted', '1');
            safeLsRemove('passportStickerDismissed');
        } else if (fs.stickerDismissed && safeLsGet('passportStickerSubmitted') !== '1') {
            safeLsSet('passportStickerDismissed', '1');
        }
    }

    /* ═══════════════════════════════════════════
       Contest Progress — Outbox (Offline Support)
       ═══════════════════════════════════════════ */

    function queueOutbox(payload) {
        try {
            safeLsSet('contest:outbox', JSON.stringify(payload));
        } catch (e) {}
    }

    function clearOutbox() {
        safeLsRemove('contest:outbox');
    }

    function getOutbox() {
        try {
            var raw = safeLsGet('contest:outbox');
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    async function flushOutbox() {
        var payload = getOutbox();
        if (!payload || !contestCid) return;

        if (!contestWriteToken) {
            try {
                await fetchRemoteProgress(contestCid);
            } catch (e) {
                // Token hydration failed; keep outbox for later.
            }
            if (!contestWriteToken) return;
        }

        var rev = getLastServerRevision();
        try {
            var result = await pushRemoteProgress(contestCid, payload, rev);
            if (result.ok) {
                clearOutbox();
                setLastServerRevision(result.revision);
                setLastSyncAt();
            } else if (result.error === 'conflict') {
                var merged = mergePayloads(payload, result.serverPayload);
                var retry = await pushRemoteProgress(contestCid, merged.payload, result.serverRevision);
                if (retry.ok) {
                    clearOutbox();
                    setLastServerRevision(retry.revision);
                    setLastSyncAt();
                    await applyPayloadLocally(merged.payload);
                }
            }
        } catch (e) {
            // Still offline, keep in outbox
        }
    }

    /* ═══════════════════════════════════════════
       Contest Progress — localStorage Helpers
       ═══════════════════════════════════════════ */

    function getLastServerRevision() {
        try { return parseInt(safeLsGet('contest:lastServerRevision') || '0', 10); } catch (e) { return 0; }
    }

    function setLastServerRevision(rev) {
        safeLsSet('contest:lastServerRevision', String(rev));
    }

    function setLastSyncAt() {
        safeLsSet('contest:lastSyncAt', new Date().toISOString());
    }

    /* ═══════════════════════════════════════════
       Contest Progress — Sync Lifecycle
       ═══════════════════════════════════════════ */

    var syncTimer = null;

    function scheduleSyncDebounced() {
        if (syncTimer) clearTimeout(syncTimer);
        syncTimer = setTimeout(function () {
            syncProgress().catch(function () {});
        }, 3000);
    }

    async function syncProgress() {
        if (!navigator.onLine || !contestCid || !CFG.contestProgressUrl) return;

        await flushOutbox();

        var localPayload = await buildPayload();
        var serverRevision = getLastServerRevision();

        try {
            var remote = await fetchRemoteProgress(contestCid);

            if (remote.ok) {
                serverRevision = remote.revision;
                var merged = mergePayloads(localPayload, remote.payload);

                if (merged.changed) {
                    var result = await pushRemoteProgress(contestCid, merged.payload, serverRevision);
                    if (result.ok) {
                        setLastServerRevision(result.revision);
                        setLastSyncAt();
                        await applyPayloadLocally(merged.payload);
                    } else if (result.error === 'conflict') {
                        var reMerged = mergePayloads(merged.payload, result.serverPayload);
                        var retry = await pushRemoteProgress(contestCid, reMerged.payload, result.serverRevision);
                        if (retry.ok) {
                            setLastServerRevision(retry.revision);
                            setLastSyncAt();
                            await applyPayloadLocally(reMerged.payload);
                        }
                    }
                } else {
                    await applyPayloadLocally(remote.payload);
                    setLastServerRevision(serverRevision);
                    setLastSyncAt();
                }
            } else if (remote.error === 'not_found') {
                if (localPayload.progress.stepsCompleted.length > 0) {
                    var createResult = await pushRemoteProgress(contestCid, localPayload, 0);
                    if (createResult.ok) {
                        setLastServerRevision(createResult.revision);
                        setLastSyncAt();
                    }
                }
            }
        } catch (e) {
            queueOutbox(localPayload);
        }
    }

    function syncBeforeUnload() {
        if (!contestCid || !CFG.contestProgressUrl || !navigator.onLine || !contestWriteToken) return;

        try {
            var payload = {
                cid: contestCid,
                payload: null,
                clientRevision: getLastServerRevision(),
                writeToken: contestWriteToken
            };

            // Best-effort: use sendBeacon if available
            if (typeof navigator.sendBeacon === 'function') {
                // Build a minimal sync payload from localStorage stamps
                var steps = [];
                var ts = {};
                for (var i = 0; i < itemsData.length; i++) {
                    var stamp = lsRead('stamps', itemsData[i].shortCode);
                    if (stamp) {
                        steps.push(stamp.shortCode);
                        ts[stamp.shortCode] = stamp.collectedAt || new Date().toISOString();
                    }
                }

                var beaconFs = {
                    drawSubmitted:    safeLsGet('passportDrawSubmitted')   === '1',
                    drawDismissed:    safeLsGet('passportDrawDismissed')   === '1',
                    stickerSubmitted: safeLsGet('passportStickerSubmitted') === '1',
                    stickerDismissed: safeLsGet('passportStickerDismissed') === '1'
                };
                var hasFormState = beaconFs.drawSubmitted || beaconFs.drawDismissed ||
                                   beaconFs.stickerSubmitted || beaconFs.stickerDismissed;

                if (steps.length === 0 && !hasFormState) return;

                payload.payload = {
                    schemaVersion: 1,
                    contestVersion: CONTEST_VERSION,
                    progress: {
                        stepsCompleted: steps,
                        answers: {},
                        score: steps.length,
                        badges: [],
                        custom: { stampTimestamps: ts, formStates: beaconFs }
                    },
                    updatedAt: new Date().toISOString()
                };

                navigator.sendBeacon(
                    CFG.contestProgressUrl,
                    new Blob([JSON.stringify(payload)], { type: 'application/json' })
                );
            }
        } catch (e) {
            // Best effort — ignore errors on unload
        }
    }

    /* ═══════════════════════════════════════════
       Contest Progress — Language Switch Helper
       ═══════════════════════════════════════════ */

    function languageSwitchUrl(targetBaseUrl, targetLang) {
        var currentQ = new URL(window.location.href).searchParams.get('q');
        var sep = targetBaseUrl.indexOf('?') === -1 ? '?' : '&';
        var url = targetBaseUrl;
        if (targetLang) {
            url = url + sep + 'lang=' + encodeURIComponent(normalizeLang(targetLang));
            sep = '&';
        }
        if (currentQ) {
            url = url + sep + 'q=' + encodeURIComponent(currentQ);
            sep = '&';
        }
        if (contestCid) {
            url = url + sep + 'cid=' + encodeURIComponent(contestCid);
        }
        return url;
    }

    function bindLanguageSwitchLinks() {
        var links = qsa('[data-passport-lang]');
        for (var i = 0; i < links.length; i++) {
            (function (link) {
                link.addEventListener('click', function (e) {
                    var targetLang = link.getAttribute('data-passport-lang');
                    var baseHref = link.getAttribute('data-passport-href') || link.getAttribute('href');
                    if (baseHref) {
                        e.preventDefault();
                        var nextHref = languageSwitchUrl(baseHref, targetLang);
                        // Record explicit language choice so auto-redirect doesn't fight it.
                        persistLanguageChoice(targetLang).then(function () {
                            window.location.href = nextHref;
                        }, function () {
                            window.location.href = nextHref;
                        });
                    }
                });
            })(links[i]);
        }
    }

    // Expose for external language switch integrations
    window.__PASSPORT_LANG_SWITCH__ = function (targetBaseUrl, targetLang) {
        if (targetLang) {
            persistLanguageChoice(targetLang).catch(function () {});
        }
        return languageSwitchUrl(targetBaseUrl, targetLang);
    };

    /* ═══════════════════════════════════════════
       Stamp Grid Rendering
       ═══════════════════════════════════════════ */

    function scrollToSlot(shortCode) {
        var item = findItemByCode(shortCode);
        var slot = item ? qs('.stamp-slot[data-id="' + item.id + '"]') : null;
        if (slot) {
            slot.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    async function renderGrid(newlyCollectedCode) {
        var grid = el('stampGrid');
        if (!grid) return;

        var slots = qsa('.stamp-slot', grid);

        for (var i = 0; i < slots.length; i++) {
            var slot   = slots[i];
            var itemId = parseInt(slot.dataset.id, 10);
            var meta   = itemsData.find(function (x) { return x.id === itemId; });
            var code   = meta ? meta.shortCode : null;
            var stamp  = code ? await getStamp(code) : null;
            var isNew  = newlyCollectedCode === code;
            var check  = qs('.stamp-check', slot);

            if (stamp) {
                slot.classList.add('collected');
                if (check) check.classList.remove('hidden');
                if (isNew) slot.classList.add('just-collected');
            } else {
                slot.classList.remove('collected');
                if (check) check.classList.add('hidden');
            }
        }

        await updateProgress();
    }

    /* ═══════════════════════════════════════════
       Progress
       ═══════════════════════════════════════════ */

    async function countStamps() {
        var count = 0;
        for (var i = 0; i < itemsData.length; i++) {
            var s = await getStamp(itemsData[i].shortCode);
            if (s) count++;
        }
        return count;
    }

    async function updateProgress() {
        var count = await countStamps();
        var total = itemsData.length;
        var pct   = total ? Math.round((count / total) * 100) : 0;

        var fill = el('progressFill');
        var text = el('progressText');
        var bar  = fill ? fill.parentElement : null;
        if (fill) fill.style.width = pct + '%';
        if (bar)  bar.setAttribute('aria-valuenow', count);
        if (text) text.textContent = count + ' / ' + total;
    }

    /* ═══════════════════════════════════════════
       Stamp Collection
       ═══════════════════════════════════════════ */

    async function collectStamp(item) {
        if (!item) return;

        var existing = await getStamp(item.shortCode);
        if (existing) {
            showStatus(TXT.alreadyCheckedIn || 'You already checked in here!');
            ga4Event('passport_stamp_duplicate', { item_code: item.shortCode });
            await renderGrid();
            await checkThresholds();
            return;
        }

        /* Geofence check (if enabled) */
        if (CFG.enableGeofence) {
            showStatus(TXT.checkingLocation || 'Checking your location\u2026');
            if (!window.isSecureContext) {
                showStatus('Location requires a secure HTTPS page. Open the passport using the secure site URL.');
                return;
            }
            if (!navigator.geolocation || typeof navigator.geolocation.getCurrentPosition !== 'function') {
                showStatus(TXT.locationError || 'Location is not available in this browser. Please open the passport in your phone browser and allow location access.');
                return;
            }

            var pos;
            try {
                pos = await new Promise(function (resolve, reject) {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                    });
                });
            } catch (err) {
                var message = TXT.locationError || 'Could not determine your location. Please allow location access and try again.';
                if (err && err.code === 1) {
                    message = 'Location permission was denied. Enable location access for this site and scan again.';
                } else if (err && err.code === 3) {
                    message = 'Location lookup timed out. Move outdoors or closer to the location and try again.';
                } else if (!window.isSecureContext) {
                    message = 'Location requires a secure HTTPS page. Open the passport using the secure site URL.';
                }
                showStatus(message);
                return;
            }

            var result;
            try {
                result = await apiCollect(item.shortCode, pos.coords.latitude, pos.coords.longitude);
            } catch (err) {
                showStatus(TXT.checkinFailed || 'Check-in failed. Please check your connection and try again.');
                return;
            }
            if (!result.success) {
                showStatus(result.error || TXT.checkinFailed || 'Check-in failed. Are you at the right location?');
                ga4Event('passport_geofence_fail', { item_code: item.shortCode, distance: result.distance });
                return;
            }
        }

        /* Save stamp locally */
        await putStamp({
            shortCode: item.shortCode,
            collectedAt: new Date().toISOString(),
        });

        showStatus((TXT.checkedIn || 'Checked in!') + ' ' + (item.title || ''));
        ga4Event('passport_stamp_collected', { item_code: item.shortCode, item_title: item.title });

        await renderGrid(item.shortCode);
        await checkThresholds();

        /* Trigger remote sync after stamp collection */
        scheduleSyncDebounced();
    }

    /* ═══════════════════════════════════════════
       Threshold Checks (draw + sticker modals)
       ═══════════════════════════════════════════ */

    async function checkThresholds() {
        var count = await countStamps();

        /* Draw threshold */
        if (count >= CFG.drawThreshold) {
            if (safeLsGet('passportDrawSubmitted') !== '1') {
                if (safeLsGet('passportDrawDismissed') !== '1') {
                    showModal('drawModal');
                    ga4Event('passport_draw_eligible', { stamps: count });
                } else {
                    /* Previously dismissed — show reopen button */
                    updateReopenButton('drawReopenSection', true);
                }
            }
        }

        /* Sticker: all items completed */
        if (count >= itemsData.length && itemsData.length > 0) {
            if (safeLsGet('passportStickerSubmitted') !== '1') {
                if (safeLsGet('passportStickerDismissed') !== '1') {
                    showModal('stickerModal');
                    ga4Event('passport_all_complete', { stamps: count });
                } else {
                    /* Previously dismissed — show reopen button */
                    updateReopenButton('stickerReopenSection', true);
                }
            }
        }
    }

    /* ═══════════════════════════════════════════
       Focus Trap — WCAG 2.1 SC 2.1.2
       ═══════════════════════════════════════════ */

    function trapFocus(modal) {
        function handler(e) {
            if (e.key !== 'Tab') return;
            var focusable = qsa(FOCUSABLE_SELECTOR, modal);
            if (focusable.length < 2) return;
            var first = focusable[0];
            var last  = focusable[focusable.length - 1];
            if (e.shiftKey) {
                if (document.activeElement === first) { e.preventDefault(); last.focus(); }
            } else {
                if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
            }
        }
        modal._focusTrapHandler = handler;
        modal.addEventListener('keydown', handler);
    }

    function releaseFocusFromModal(modal) {
        if (modal._focusTrapHandler) {
            modal.removeEventListener('keydown', modal._focusTrapHandler);
            modal._focusTrapHandler = null;
        }
        var trigger = modal._focusTrigger || null;
        modal._focusTrigger = null;
        if (trigger && typeof trigger.focus === 'function') {
            trigger.focus();
        }
    }

    /* ═══════════════════════════════════════════
       Modals
       ═══════════════════════════════════════════ */

    function showModal(id, triggerEl) {
        var modal = el(id);
        if (!modal) return;
        modal._focusTrigger = triggerEl || null;
        if (modal._focusTrapHandler) {
            modal.removeEventListener('keydown', modal._focusTrapHandler);
            modal._focusTrapHandler = null;
        }
        modal.classList.remove('hidden');
        /* Move focus inside the modal — close button or first focusable */
        var focusable = qsa(FOCUSABLE_SELECTOR, modal);
        if (focusable.length) focusable[0].focus();
        trapFocus(modal);
    }

    function updateReopenButton(sectionId, show) {
        var section = el(sectionId);
        if (!section) return;
        if (show) {
            section.classList.remove('hidden');
        } else {
            section.classList.add('hidden');
        }
    }

    function hideModal(id) {
        var modal = el(id);
        if (!modal) return;
        modal.classList.add('hidden');
        releaseFocusFromModal(modal);

        /* Track dismissed state for form modals saved in localStorage */
        if (id === 'drawModal') {
            if (safeLsGet('passportDrawSubmitted') !== '1') {
                safeLsSet('passportDrawDismissed', '1');
                updateReopenButton('drawReopenSection', true);
                scheduleSyncDebounced();
            } else {
                updateReopenButton('drawReopenSection', false);
            }
        }
        if (id === 'stickerModal') {
            if (safeLsGet('passportStickerSubmitted') !== '1') {
                safeLsSet('passportStickerDismissed', '1');
                updateReopenButton('stickerReopenSection', true);
                scheduleSyncDebounced();
            } else {
                updateReopenButton('stickerReopenSection', false);
            }
        }
    }

    function bindModals() {
        /* Close buttons */
        qsa('.passport-modal-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = btn.closest('.passport-modal');
                if (modal) hideModal(modal.id);
            });
        });

        /* Generic open triggers: <button data-open-modal="modalId"> */
        qsa('[data-open-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                showModal(btn.getAttribute('data-open-modal'), btn);
            });
        });

        /* Backdrop close */
        qsa('.passport-modal-backdrop').forEach(function (bd) {
            bd.addEventListener('click', function () {
                var modal = bd.closest('.passport-modal');
                if (modal) hideModal(modal.id);
            });
        });

        /* Escape key — close topmost open modal */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var openModals = qsa('.passport-modal:not(.hidden)');
            if (!openModals.length) return;
            hideModal(openModals[openModals.length - 1].id);
        });

        /* Freeform: freeform-ajax-success is dispatched on document (not the form
           element), so e.target is document — containment checks always fail.
           Instead, identify the relevant form by which modal is currently open. */
        document.addEventListener('freeform-ajax-success', function () {
            var drawModal    = el('drawModal');
            var stickerModal = el('stickerModal');

            if (drawModal && !drawModal.classList.contains('hidden')) {
                safeLsSet('passportDrawSubmitted', '1');
                safeLsRemove('passportDrawDismissed');
                updateReopenButton('drawReopenSection', false);
                ga4Event('passport_draw_submitted');
                scheduleSyncDebounced();
                setTimeout(function () { hideModal('drawModal'); }, 2000);
            }

            if (stickerModal && !stickerModal.classList.contains('hidden')) {
                safeLsSet('passportStickerSubmitted', '1');
                safeLsRemove('passportStickerDismissed');
                updateReopenButton('stickerReopenSection', false);
                ga4Event('passport_sticker_submitted');
                scheduleSyncDebounced();
                setTimeout(function () { hideModal('stickerModal'); }, 2000);
            }
        });
    }

    function bindReopenButtons() {
        var drawBtn = el('drawReopenBtn');
        if (drawBtn) {
            drawBtn.addEventListener('click', function () {
                showModal('drawModal', drawBtn);
            });
        }
        var stickerBtn = el('stickerReopenBtn');
        if (stickerBtn) {
            stickerBtn.addEventListener('click', function () {
                showModal('stickerModal', stickerBtn);
            });
        }
    }

    /* ═══════════════════════════════════════════
       Disclaimer
       ═══════════════════════════════════════════ */

    function showDisclaimerOnce(callback) {
        var modal = el('disclaimerModal');
        if (!modal || safeLsGet('passportDisclaimerAccepted') === '1' || CFG.requireDisclaimerAck === false) {
            return callback();
        }

        showModal('disclaimerModal');  /* focuses accept button, traps focus */

        var btn = el('disclaimerAccept');
        if (!btn) return callback();

        btn.addEventListener('click', function () {
            safeLsSet('passportDisclaimerAccepted', '1');
            hideModal('disclaimerModal');
            callback();
        }, { once: true });
    }

    /* ═══════════════════════════════════════════
       Item Detail Modal
       ═══════════════════════════════════════════ */

    /**
     * Strip all but a safe subset of HTML tags and attributes.
     * Prevents XSS when injecting admin-authored description HTML into the DOM.
     */
    function sanitizeDescHtml(html) {
        var SAFE_TAGS = ['P','BR','STRONG','EM','B','I','U','UL','OL','LI',
                         'A','SPAN','BLOCKQUOTE','HR','H1','H2','H3','H4','H5','H6'];

        var doc = (new DOMParser()).parseFromString(html, 'text/html');

        function cleanDocNode(srcNode, dstNode) {
            Array.from(srcNode.childNodes).forEach(function (child) {
                if (child.nodeType === 3) {
                    dstNode.appendChild(document.createTextNode(child.textContent));
                    return;
                }
                if (child.nodeType !== 1) return; // skip comments, PIs, etc.

                if (SAFE_TAGS.indexOf(child.tagName) === -1) {
                    // Unwrap unsafe element: recurse its children into the parent
                    cleanDocNode(child, dstNode);
                    return;
                }

                var copy = document.createElement(child.tagName);
                if (child.tagName === 'A') {
                    var href = child.getAttribute('href') || '';
                    if (href) {
                        try {
                            var proto = new URL(href, location.href).protocol;
                            if (proto === 'http:' || proto === 'https:') {
                                copy.setAttribute('href', href);
                            }
                        } catch (e) { /* discard malformed URLs */ }
                    }
                    // Always enforce noopener noreferrer; only allow _blank as target.
                    copy.setAttribute('rel', 'noopener noreferrer');
                    var target = child.getAttribute('target');
                    if (target === '_blank') copy.setAttribute('target', '_blank');
                }
                cleanDocNode(child, copy);
                dstNode.appendChild(copy);
            });
        }

        var out = document.createElement('div');
        cleanDocNode(doc.body, out);
        return out.innerHTML;
    }

    function openItemDetail(btn) {
        var itemId   = btn.getAttribute('data-item-id');
        var linkUrl  = btn.getAttribute('data-item-link-url')  || '';
        var linkText = (btn.getAttribute('data-item-link-text') || '').trim() || TXT.learnMore || 'Learn more';

        /* Title lives in the sibling .stamp-title */
        var slot    = btn.closest('.stamp-slot');
        var titleEl = slot ? qs('.stamp-title', slot) : null;
        var title   = titleEl ? titleEl.textContent.trim() : '';

        /* Description HTML lives in the per-item <template> */
        var tmpl     = document.getElementById('item-desc-' + itemId);
        var descHtml = tmpl ? tmpl.innerHTML : '';

        var modal = el('itemDetailModal');
        if (!modal) return;

        var titleNode = qs('.item-detail-title', modal);
        var bodyNode  = qs('.item-detail-body',  modal);
        var linkNode  = qs('.item-detail-link',  modal);

        if (titleNode) titleNode.textContent = title;
        if (bodyNode)  bodyNode.innerHTML    = sanitizeDescHtml(descHtml);

        if (linkNode) {
            var safeUrl = '';
            if (linkUrl) {
                try {
                    var parsed = new URL(linkUrl, location.href);
                    if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
                        safeUrl = parsed.href;
                    }
                } catch (e) { /* malformed URL — leave safeUrl empty */ }
            }
            if (safeUrl) {
                linkNode.href        = safeUrl;
                linkNode.textContent = linkText;
                linkNode.classList.remove('hidden');
            } else {
                linkNode.href = '';
                linkNode.classList.add('hidden');
            }
        }

        showModal('itemDetailModal', btn);  /* btn is focus trigger — returned on close */
    }

    function bindItemDetailButtons() {
        qsa('.stamp-slot-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { openItemDetail(btn); });
        });
    }

    /* ═══════════════════════════════════════════
       Status
       ═══════════════════════════════════════════ */

    function showStatus(msg) {
        var section = el('statusSection');
        if (!section) return;
        section.textContent = msg;
        section.classList.remove('hidden');
    }

    /* ═══════════════════════════════════════════
       Init
       ═══════════════════════════════════════════ */

    async function init() {
        await initDB();

        /* Initialize contest ID (from URL, localStorage, or generate new) */
        contestCid = initCid();

        /* Load items from API */
        try {
            itemsData = await fetchLocations();
        } catch (err) {
            showStatus(TXT.loadError || 'Could not load passport data. Please try again later.');
            return;
        }

        /* Perform initial remote sync (fetches server state and applies locally) */
        if (CFG.contestProgressUrl) {
            try {
                await syncProgress();
            } catch (e) {
                // Sync failure is non-fatal; local state still works
            }
        }

        /* Render grid with saved state (includes any stamps pulled from server) */
        await renderGrid();

        /* Bind modal interactions */
        bindModals();

        /* Bind reopen form buttons */
        bindReopenButtons();

        /* Bind item detail buttons (full row click → detail modal) */
        bindItemDetailButtons();

        /* Bind language switch links */
        bindLanguageSwitchLinks();

        /* Resolve QR code from URL */
        var code = getShortCodeFromUrl();
        if (code) {
            currentItem = findItemByCode(code);
            if (!currentItem) {
                /* Try resolving via API in case items list is stale */
                try {
                    var resolved = await apiResolve(code);
                    if (resolved.success) {
                        currentItem = resolved.item;
                    }
                } catch (e) {}
            }

            if (currentItem) {
                var itemToCollect = currentItem;
                showDisclaimerOnce(function () {
                    collectStamp(itemToCollect).then(function () {
                        scrollToSlot(itemToCollect.shortCode);
                    }).catch(function () {});
                });
            } else {
                showStatus(TXT.qrNotRecognized || 'This QR code is not recognized.');
            }
        }

        /* Check thresholds for returning visitors */
        await checkThresholds();

        /* Register beforeunload sync */
        window.addEventListener('beforeunload', syncBeforeUnload);

        /* Register online event to flush outbox */
        window.addEventListener('online', function () {
            flushOutbox().catch(function () {});
        });

        ga4Event('passport_page_view', { items_total: itemsData.length });
    }

    /* ═══════════════════════════════════════════
       Freeform AJAX — early opt-in
       ═══════════════════════════════════════════ */

    /* freeform-ready fires on the form element (may not bubble), so we use
       capture phase to intercept it reliably. We enable AJAX mode only for
       forms inside our modal containers; all other Freeform forms are unaffected.
       This listener is registered synchronously before init() so it is always
       in place before Freeform initialises the forms on DOMContentLoaded. */
    document.addEventListener('freeform-ready', function (e) {
        var form = e.target;
        var drawContainer    = el('drawFormContainer');
        var stickerContainer = el('stickerFormContainer');
        if (form && (
            (drawContainer    && drawContainer.contains(form))    ||
            (stickerContainer && stickerContainer.contains(form))
        )) {
            e.options.ajax = true;
        }
    }, true /* capture */);

    /* Boot */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init().catch(function (err) { showStatus((TXT.errorPrefix || 'Error: ') + err.message); });
        });
    } else {
        init().catch(function (err) { showStatus((TXT.errorPrefix || 'Error: ') + err.message); });
    }

})();
