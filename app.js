const CONFIG = {
  appName: "Cottage Passport Canada",
  headerText: "Collect all 30 Canadiana Cottage stamps",
  geofenceMeters: 550,
  locations: []
};

const UUID_RE = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i;
let db;
let currentLocation;
let stampVisibilityObserver;

const el = (id) => document.getElementById(id);
const text = (id, value) => { const node = el(id); if (node) node.textContent = value; };

async function initDB() {
  if (db) return;
  try {
    db = await new Promise((resolve, reject) => {
      const req = indexedDB.open("cottage-passport", 2);
      req.onupgradeneeded = () => {
        const d = req.result;
        if (!d.objectStoreNames.contains("stamps")) d.createObjectStore("stamps", { keyPath: "locationId" });
        if (!d.objectStoreNames.contains("meta")) d.createObjectStore("meta", { keyPath: "key" });
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  } catch {
    db = null;
  }
}

function reqP(req) { return new Promise((resolve, reject) => { req.onsuccess = () => resolve(req.result); req.onerror = () => reject(req.error); }); }
function store(name, mode = "readonly") { return db.transaction(name, mode).objectStore(name); }

// ── Dual storage: IndexedDB + localStorage fallback ──

const LS_PREFIX = "passportData:";

function lsWrite(storeName, key, data) {
  try { localStorage.setItem(`${LS_PREFIX}${storeName}:${key}`, JSON.stringify(data)); } catch {}
}

function lsRead(storeName, key) {
  try {
    const raw = localStorage.getItem(`${LS_PREFIX}${storeName}:${key}`);
    return raw ? JSON.parse(raw) : null;
  } catch { return null; }
}

async function getStamp(locationId) {
  if (db) {
    try {
      const result = await reqP(store("stamps").get(locationId));
      if (result) return result;
    } catch {}
  }
  return lsRead("stamps", locationId);
}

async function putStamp(data) {
  lsWrite("stamps", data.locationId, data);
  if (db) await reqP(store("stamps", "readwrite").put(data));
}

async function getMeta(key) {
  if (db) {
    try {
      const result = await reqP(store("meta").get(key));
      if (result) return result;
    } catch {}
  }
  return lsRead("meta", key);
}

async function putMeta(data) {
  lsWrite("meta", data.key, data);
  if (db) await reqP(store("meta", "readwrite").put(data));
}

function setStatus(message) {
  const section = el("statusSection");
  if (!section) return;
  const drawBtn = section.querySelector("#enterDrawBtn");
  section.textContent = message;
  if (drawBtn) section.appendChild(drawBtn);
}

// ── Encryption helpers (Web Crypto API) ──

async function getOrCreateEncryptionKey() {
  const existing = await getMeta("encryption-key");
  if (existing?.value) {
    return crypto.subtle.importKey("jwk", existing.value, { name: "AES-GCM" }, true, ["encrypt", "decrypt"]);
  }

  const key = await crypto.subtle.generateKey({ name: "AES-GCM", length: 256 }, true, ["encrypt", "decrypt"]);
  const jwk = await crypto.subtle.exportKey("jwk", key);
  await putMeta({
    key: "encryption-key",
    value: jwk,
    updatedAt: new Date().toISOString()
  });
  return key;
}

async function encryptToken(data) {
  const key = await getOrCreateEncryptionKey();
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const encoded = new TextEncoder().encode(JSON.stringify(data));
  const ciphertext = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, encoded);

  const combined = new Uint8Array(iv.length + ciphertext.byteLength);
  combined.set(iv, 0);
  combined.set(new Uint8Array(ciphertext), iv.length);
  return btoa(String.fromCharCode(...combined));
}

// ── App config ──

async function loadAppConfig() {
  const res = await fetch("?action=locations", { headers: { Accept: "application/json" } });
  const data = await res.json().catch(() => ({}));

  if (!res.ok || !data.success || !Array.isArray(data.locations)) {
    throw new Error(data.error || "Could not load location config from server.");
  }

  CONFIG.appName = data.appName || CONFIG.appName;
  CONFIG.headerText = data.headerText || CONFIG.headerText;
  CONFIG.geofenceMeters = Number.isFinite(data.geofenceMeters) ? data.geofenceMeters : CONFIG.geofenceMeters;
  CONFIG.locations = data.locations
    .filter((loc) => Number.isInteger(loc.locationId) && UUID_RE.test(loc.uuid || ""))
    .map((loc) => ({
      locationId: loc.locationId,
      name: loc.name,
      tagline: loc.tagline,
      qrToken: loc.uuid
    }));
}

function parseSignedQr(raw) {
  if (!raw) return null;
  if (UUID_RE.test(raw)) return { uuid: raw, signed: false };
  const [payload, sig] = raw.split(".");
  if (!payload || !sig) return null;
  try {
    const json = JSON.parse(atob(payload.replace(/-/g, "+").replace(/_/g, "/")));
    if (!UUID_RE.test(json.uuid || "")) return null;
    return { uuid: json.uuid, signed: true, sig, payload: json };
  } catch { return null; }
}

function getQrTokenFromUrl() {
  const url = new URL(window.location.href);
  return (url.searchParams.get("q") || "").trim();
}

function getLocationByUuid(uuid) {
  return CONFIG.locations.find((l) => l.qrToken === uuid) || null;
}

function watchStampVisibility(stampEl) {
  if (!stampEl) return;

  if (!("IntersectionObserver" in window)) {
    stampEl.classList.add("is-visible");
    return;
  }

  if (!stampVisibilityObserver) {
    stampVisibilityObserver = new IntersectionObserver((entries, observer) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      }
    }, { threshold: 0.35 });
  }

  stampVisibilityObserver.observe(stampEl);
}

// ── Stamp grid ──

async function renderStampGrid(newlyCollectedId) {
  const grid = el("stampGrid");
  if (!grid) return;
  grid.innerHTML = "";
  for (const loc of CONFIG.locations) {
    const stamp = await getStamp(loc.locationId);
    const isNew = newlyCollectedId === loc.locationId;
    const div = document.createElement("div");
    div.className = `stamp-slot${stamp ? " collected" : ""}${isNew ? " just-collected" : ""}`;
    div.style.setProperty("--bg-image", `url(https://picsum.photos/seed/cottage${loc.locationId}/200/200)`);
    div.setAttribute("role", "listitem");

    const content = document.createElement("span");
    content.className = "stamp-content";
    if (stamp) {
      content.innerHTML = `🍁<strong>${loc.name}</strong>`;
    } else {
      content.textContent = loc.locationId;
    }
    div.appendChild(content);

    grid.appendChild(div);

    if (isNew) watchStampVisibility(div);
  }
}

// ── Count collected stamps ──

async function countCollectedStamps() {
  let count = 0;
  for (const loc of CONFIG.locations) {
    const stamp = await getStamp(loc.locationId);
    if (stamp) count++;
  }
  return count;
}

// ── Save stamp + encrypted token ──

async function saveStamp(location) {
  const now = new Date().toISOString();

  await putStamp({
    locationId: location.locationId,
    collectedAt: now
  });

  const tokenData = {
    uuid: location.qrToken,
    locationId: location.locationId,
    collectedAt: now,
    nonce: crypto.getRandomValues(new Uint8Array(8)).join("")
  };
  const encryptedToken = await encryptToken(tokenData);

  await putMeta({
    key: `encrypted-token:${location.locationId}`,
    value: encryptedToken,
    updatedAt: now
  });
}

// ── Collect stamp for current location ──

async function collectCurrentStamp() {
  if (!currentLocation) return;

  const existing = await getStamp(currentLocation.locationId);
  if (existing) {
    el("collectSection")?.classList.remove("hidden");
    text("locationDisplay", `${currentLocation.locationId}. ${currentLocation.name} — ${currentLocation.tagline}`);
    text("collectMessage", "You already collected this stamp!");
    setStatus(`Already collected at ${currentLocation.name}.`);
    await renderStampGrid();
    await checkDrawEligibility();
    return;
  }

  await saveStamp(currentLocation);

  el("collectSection")?.classList.remove("hidden");
  text("locationDisplay", `${currentLocation.locationId}. ${currentLocation.name} — ${currentLocation.tagline}`);
  text("collectMessage", "Stamp saved to your passport!");
  setStatus(`Stamp collected at ${currentLocation.name}!`);
  await renderStampGrid(currentLocation.locationId);
  await checkDrawEligibility();
}

// ── Draw eligibility modal (5+ stamps) ──

function showDrawButton() {
  const section = el("statusSection");
  if (!section || section.querySelector("#enterDrawBtn")) return;
  const btn = document.createElement("button");
  btn.id = "enterDrawBtn";
  btn.className = "primary";
  btn.textContent = "Enter the draw";
  btn.addEventListener("click", async () => {
    const count = await countCollectedStamps();
    text("drawStampCount", String(count));
    const modal = el("drawModal");
    if (modal) modal.classList.remove("hidden");
  });
  section.appendChild(btn);
}

async function checkDrawEligibility() {
  const count = await countCollectedStamps();
  if (count < 5) return;
  if (localStorage.getItem("passportDrawEmailSubmitted") === "1") return;

  text("drawStampCount", String(count));

  if (localStorage.getItem("passportDrawDismissed") === "1") {
    showDrawButton();
    return;
  }

  const modal = el("drawModal");
  if (modal) modal.classList.remove("hidden");
}

function bindDrawModal() {
  const form = el("drawEmailForm");
  const dismissBtn = el("drawDismissBtn");
  const modal = el("drawModal");

  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const email = (el("drawEmailInput")?.value || "").trim();
      const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      el("drawEmailError")?.classList.toggle("hidden", valid);
      if (!valid) return;

      await putMeta({
        key: "draw-email",
        value: email,
        updatedAt: new Date().toISOString()
      });

      localStorage.setItem("passportDrawEmailSubmitted", "1");
      if (modal) modal.classList.add("hidden");
      const drawBtn = el("enterDrawBtn");
      if (drawBtn) drawBtn.remove();
      setStatus("You're in the draw! Good luck!");
    });
  }

  if (dismissBtn) {
    dismissBtn.addEventListener("click", () => {
      localStorage.setItem("passportDrawDismissed", "1");
      if (modal) modal.classList.add("hidden");
      showDrawButton();
    });
  }
}

// ── Disclaimer ──

function showDisclaimerOnce(onAccept) {
  const modal = el("disclaimerModal");
  if (!modal || localStorage.getItem("passportDisclaimerAccepted") === "1") {
    if (onAccept) onAccept();
    return;
  }

  const acceptBtn = el("disclaimerAcceptBtn") || modal.querySelector("button");
  if (!acceptBtn) {
    setStatus("Disclaimer could not be acknowledged because the accept button is missing.");
    return;
  }

  modal.classList.remove("hidden");
  acceptBtn.addEventListener("click", () => {
    localStorage.setItem("passportDisclaimerAccepted", "1");
    modal.classList.add("hidden");
    if (onAccept) onAccept();
  }, { once: true });
}

// ── Location state ──

function loadLocationState() {
  const rawToken = getQrTokenFromUrl();
  const qr = parseSignedQr(rawToken);
  if (!qr) {
    return setStatus("Scan an official stop QR code to collect a stamp.");
  }

  const location = getLocationByUuid(qr.uuid);
  if (!location) {
    return setStatus("Sorry — this QR code is not valid for the Cottage Passport event.");
  }

  currentLocation = location;
}

// ── Error handling ──

function bindGlobalErrorHandling() {
  window.addEventListener("error", (event) => {
    const message = event?.error?.message || event?.message || "Unexpected runtime error.";
    setStatus(`Error: ${message}`);
  });

  window.addEventListener("unhandledrejection", (event) => {
    const reason = event?.reason;
    const message = reason?.message || (typeof reason === "string" ? reason : "Unhandled async error.");
    setStatus(`Error: ${message}`);
  });
}

// ── Init ──

async function init() {
  bindGlobalErrorHandling();
  await loadAppConfig();
  document.title = CONFIG.appName;
  text("appName", CONFIG.appName);
  text("appTagline", CONFIG.headerText);
  await initDB();

  loadLocationState();
  await renderStampGrid();
  bindDrawModal();

  showDisclaimerOnce(async () => {
    try {
      await collectCurrentStamp();
    } catch (err) {
      setStatus(`Error: ${err.message}`);
    }
  });

  // Show draw button for returning visitors who previously dismissed
  await checkDrawEligibility();
}

init().catch((err) => setStatus(`Initialization failed: ${err.message}`));
