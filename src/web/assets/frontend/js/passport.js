/**
 * Cottage Passport — Frontend JavaScript
 *
 * Handles IndexedDB storage, QR-based stamp collection,
 * geofence validation, progress tracking, GA4 events,
 * and Freeform modal triggers.
 *
 * Expects window.__PASSPORT_CONFIG__ to be set by the Twig layout.
 */
(function () {
    'use strict';

    const CFG = window.__PASSPORT_CONFIG__ || {};

    /* ── Selectors ── */
    const el  = (id) => document.getElementById(id);
    const qs  = (sel, ctx) => (ctx || document).querySelector(sel);
    const qsa = (sel, ctx) => (ctx || document).querySelectorAll(sel);

    /* ── State ── */
    let db = null;
    let itemsData = [];         // Loaded from API
    let currentItem = null;     // Resolved from ?q= param

    /* ═══════════════════════════════════════════
       IndexedDB + localStorage fallback
       ═══════════════════════════════════════════ */

    const DB_NAME    = 'cottage-passport';
    const DB_VERSION = 1;
    const LS_PREFIX  = 'passportData:';

    function initDB() {
        return new Promise((resolve) => {
            try {
                const req = indexedDB.open(DB_NAME, DB_VERSION);
                req.onupgradeneeded = () => {
                    const d = req.result;
                    if (!d.objectStoreNames.contains('stamps'))
                        d.createObjectStore('stamps', { keyPath: 'shortCode' });
                    if (!d.objectStoreNames.contains('meta'))
                        d.createObjectStore('meta', { keyPath: 'key' });
                };
                req.onsuccess = () => { db = req.result; resolve(); };
                req.onerror   = () => { db = null; resolve(); };
            } catch { db = null; resolve(); }
        });
    }

    function idbReq(req) {
        return new Promise((resolve, reject) => {
            req.onsuccess = () => resolve(req.result);
            req.onerror   = () => reject(req.error);
        });
    }

    function store(name, mode) {
        return db.transaction(name, mode || 'readonly').objectStore(name);
    }

    /* localStorage helpers */
    function lsWrite(ns, key, data) {
        try { localStorage.setItem(LS_PREFIX + ns + ':' + key, JSON.stringify(data)); } catch {}
    }
    function lsRead(ns, key) {
        try {
            const raw = localStorage.getItem(LS_PREFIX + ns + ':' + key);
            return raw ? JSON.parse(raw) : null;
        } catch { return null; }
    }

    /* Dual-storage accessors */
    async function getStamp(shortCode) {
        if (db) { try { const r = await idbReq(store('stamps').get(shortCode)); if (r) return r; } catch {} }
        return lsRead('stamps', shortCode);
    }

    async function putStamp(data) {
        lsWrite('stamps', data.shortCode, data);
        if (db) await idbReq(store('stamps', 'readwrite').put(data));
    }

    async function getMeta(key) {
        if (db) { try { const r = await idbReq(store('meta').get(key)); if (r) return r; } catch {} }
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
        const res = await fetch(CFG.locationsUrl, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || 'Failed to load items.');
        return data.items || [];
    }

    async function apiCollect(shortCode, lat, lng) {
        const res = await fetch(CFG.collectUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ shortCode: shortCode, latitude: lat, longitude: lng }),
        });
        return res.json();
    }

    async function apiResolve(shortCode) {
        const res = await fetch(CFG.resolveUrl + '?q=' + encodeURIComponent(shortCode), {
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
        if (fill) fill.style.width = pct + '%';
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

        /* Load items from API */
        try {
            itemsData = await fetchLocations();
        } catch (err) {
            showStatus('Could not load passport data. Please try again later.');
            return;
        }

        /* Render grid with saved state */
        await renderGrid();

        /* Bind modal interactions */
        bindModals();

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
                } catch {}
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
