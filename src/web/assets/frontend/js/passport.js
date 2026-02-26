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

    /* ── Selectors ── */
    var el  = function (id) { return document.getElementById(id); };
    var qs  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    var qsa = function (sel, ctx) { return (ctx || document).querySelectorAll(sel); };

    /* ── State ── */
    var db = null;
    var itemsData = [];         // Loaded from API
    var currentItem = null;     // Resolved from ?q= param
    var contestCid = null;      // Contest participant ID (UUID v4)

    /* ═══════════════════════════════════════════
       IndexedDB + localStorage fallback
       ═══════════════════════════════════════════ */

    var DB_NAME    = 'stamp-passport';
    var DB_VERSION = 2;
    var LS_PREFIX  = 'passportData:';

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
    function lsWrite(ns, key, data) {
        try { localStorage.setItem(LS_PREFIX + ns + ':' + key, JSON.stringify(data)); } catch (e) {}
    }
    function lsRead(ns, key) {
        try {
            var raw = localStorage.getItem(LS_PREFIX + ns + ':' + key);
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
        if (db) await idbReq(store('stamps', 'readwrite').put(data));
    }

    async function getMeta(key) {
        if (db) { try { var r = await idbReq(store('meta').get(key)); if (r) return r; } catch (e) {} }
        return lsRead('meta', key);
    }

    async function putMeta(data) {
        lsWrite('meta', data.key, data);
        if (db) await idbReq(store('meta', 'readwrite').put(data));
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
        var data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'Failed to load items.');
        return data.items || [];
    }

    async function apiCollect(shortCode, lat, lng) {
        var res = await fetch(CFG.collectUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ shortCode: shortCode, latitude: lat, longitude: lng }),
        });
        return res.json();
    }

    async function apiResolve(shortCode) {
        var res = await fetch(CFG.resolveUrl + '?q=' + encodeURIComponent(shortCode), {
            headers: { Accept: 'application/json' },
        });
        return res.json();
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

        if (urlCid && isValidUUID(urlCid)) {
            persistCid(urlCid);
            cleanUrlParams(['cid', 'lang']);
            return urlCid;
        }

        var stored = null;
        try { stored = localStorage.getItem('contest:cid'); } catch (e) {}
        if (stored && isValidUUID(stored)) {
            return stored;
        }

        var newCid = generateUUID();
        persistCid(newCid);
        return newCid;
    }

    function persistCid(cid) {
        try { localStorage.setItem('contest:cid', cid); } catch (e) {}
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
                custom: { stampTimestamps: stampTimestamps }
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
        return res.json();
    }

    async function pushRemoteProgress(cid, payload, clientRevision) {
        var res = await fetch(CFG.contestProgressUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                cid: cid,
                payload: payload,
                clientRevision: clientRevision
            })
        });
        return res.json();
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

        var changed = mergedSteps.length !== serverSteps.length ||
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
                    custom: { stampTimestamps: mergedTs }
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
            var existing = await getStamp(code);
            if (!existing) {
                await putStamp({
                    shortCode: code,
                    collectedAt: ts[code] || new Date().toISOString()
                });
            }
        }
    }

    /* ═══════════════════════════════════════════
       Contest Progress — Outbox (Offline Support)
       ═══════════════════════════════════════════ */

    function queueOutbox(payload) {
        try {
            localStorage.setItem('contest:outbox', JSON.stringify(payload));
        } catch (e) {}
    }

    function clearOutbox() {
        try { localStorage.removeItem('contest:outbox'); } catch (e) {}
    }

    function getOutbox() {
        try {
            var raw = localStorage.getItem('contest:outbox');
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    async function flushOutbox() {
        var payload = getOutbox();
        if (!payload || !contestCid) return;

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
        try { return parseInt(localStorage.getItem('contest:lastServerRevision') || '0', 10); } catch (e) { return 0; }
    }

    function setLastServerRevision(rev) {
        try { localStorage.setItem('contest:lastServerRevision', String(rev)); } catch (e) {}
    }

    function setLastSyncAt() {
        try { localStorage.setItem('contest:lastSyncAt', new Date().toISOString()); } catch (e) {}
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
        if (!contestCid || !CFG.contestProgressUrl || !navigator.onLine) return;

        try {
            var payload = {
                cid: contestCid,
                payload: null,
                clientRevision: getLastServerRevision()
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

                if (steps.length === 0) return;

                payload.payload = {
                    schemaVersion: 1,
                    contestVersion: CONTEST_VERSION,
                    progress: {
                        stepsCompleted: steps,
                        answers: {},
                        score: steps.length,
                        badges: [],
                        custom: { stampTimestamps: ts }
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

    function languageSwitchUrl(targetBaseUrl) {
        if (!contestCid) return targetBaseUrl;
        var sep = targetBaseUrl.indexOf('?') === -1 ? '?' : '&';
        return targetBaseUrl + sep + 'cid=' + encodeURIComponent(contestCid);
    }

    function bindLanguageSwitchLinks() {
        var links = qsa('[data-passport-lang]');
        for (var i = 0; i < links.length; i++) {
            (function (link) {
                link.addEventListener('click', function (e) {
                    var baseHref = link.getAttribute('data-passport-href') || link.getAttribute('href');
                    if (baseHref && contestCid) {
                        e.preventDefault();
                        window.location.href = languageSwitchUrl(baseHref);
                    }
                });
            })(links[i]);
        }
    }

    // Expose for external language switch integrations
    window.__PASSPORT_LANG_SWITCH__ = function (targetBaseUrl) {
        return languageSwitchUrl(targetBaseUrl);
    };

    /* ═══════════════════════════════════════════
       Stamp Grid Rendering
       ═══════════════════════════════════════════ */

    async function renderGrid(newlyCollectedCode) {
        var grid = el('stampGrid');
        if (!grid) return;

        var slots = qsa('.stamp-slot', grid);

        for (var i = 0; i < slots.length; i++) {
            var slot   = slots[i];
            var code   = slot.dataset.code;
            var stamp  = await getStamp(code);
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
            showStatus('You already checked in here!');
            ga4Event('passport_stamp_duplicate', { item_code: item.shortCode });
            await renderGrid();
            await checkThresholds();
            return;
        }

        /* Geofence check (if enabled) */
        if (CFG.enableGeofence) {
            showStatus('Checking your location\u2026');
            var pos;
            try {
                pos = await new Promise(function (resolve, reject) {
                    navigator.geolocation.getCurrentPosition(resolve, reject, {
                        enableHighAccuracy: true,
                        timeout: 10000,
                    });
                });
            } catch (err) {
                showStatus('Could not determine your location. Please allow location access and try again.');
                return;
            }

            var result = await apiCollect(item.shortCode, pos.coords.latitude, pos.coords.longitude);
            if (!result.success) {
                showStatus(result.error || 'Check-in failed. Are you at the right location?');
                ga4Event('passport_geofence_fail', { item_code: item.shortCode, distance: result.distance });
                return;
            }
        }

        /* Save stamp locally */
        await putStamp({
            shortCode: item.shortCode,
            collectedAt: new Date().toISOString(),
        });

        showStatus('Checked in! ' + (item.title || ''));
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
            if (localStorage.getItem('passportDrawSubmitted') !== '1') {
                if (localStorage.getItem('passportDrawDismissed') !== '1') {
                    showModal('drawModal');
                    ga4Event('passport_draw_eligible', { stamps: count });
                }
            }
        }

        /* Sticker: all items completed */
        if (count >= itemsData.length && itemsData.length > 0) {
            if (localStorage.getItem('passportStickerSubmitted') !== '1') {
                showModal('stickerModal');
                ga4Event('passport_bucket_list_complete', { stamps: count });
            }
        }
    }

    /* ═══════════════════════════════════════════
       Modals
       ═══════════════════════════════════════════ */

    function showModal(id) {
        var modal = el(id);
        if (modal) modal.classList.remove('hidden');
    }

    function hideModal(id) {
        var modal = el(id);
        if (modal) modal.classList.add('hidden');
    }

    function bindModals() {
        /* Close buttons */
        qsa('.passport-modal-close').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = btn.closest('.passport-modal');
                if (modal) modal.classList.add('hidden');
            });
        });

        /* Backdrop close */
        qsa('.passport-modal-backdrop').forEach(function (bd) {
            bd.addEventListener('click', function () {
                var modal = bd.closest('.passport-modal');
                if (modal) modal.classList.add('hidden');
            });
        });

        /* Freeform AJAX success hooks */
        document.addEventListener('freeform-ajax-success', function (e) {
            var form = e.target;
            var drawContainer = el('drawFormContainer');
            var stickerContainer = el('stickerFormContainer');

            if (drawContainer && drawContainer.contains(form)) {
                localStorage.setItem('passportDrawSubmitted', '1');
                ga4Event('passport_draw_submitted');
                setTimeout(function () { hideModal('drawModal'); }, 2000);
            }

            if (stickerContainer && stickerContainer.contains(form)) {
                localStorage.setItem('passportStickerSubmitted', '1');
                ga4Event('passport_sticker_submitted');
                setTimeout(function () { hideModal('stickerModal'); }, 2000);
            }
        });
    }

    /* ═══════════════════════════════════════════
       Disclaimer
       ═══════════════════════════════════════════ */

    function showDisclaimerOnce(callback) {
        var modal = el('disclaimerModal');
        if (!modal || localStorage.getItem('passportDisclaimerAccepted') === '1') {
            return callback();
        }

        modal.classList.remove('hidden');

        var btn = el('disclaimerAccept');
        if (!btn) return callback();

        btn.addEventListener('click', function () {
            localStorage.setItem('passportDisclaimerAccepted', '1');
            modal.classList.add('hidden');
            callback();
        }, { once: true });
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
            showStatus('Could not load passport data. Please try again later.');
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
                showDisclaimerOnce(function () {
                    collectStamp(currentItem);
                });
            } else {
                showStatus('This QR code is not recognized.');
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

    /* Boot */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init().catch(function (err) { showStatus('Error: ' + err.message); });
        });
    } else {
        init().catch(function (err) { showStatus('Error: ' + err.message); });
    }

})();
