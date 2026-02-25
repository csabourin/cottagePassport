const CONFIG = {
  appName: "Cottage Passport Canada",
  headerText: "Collect all 30 Canadiana Cottage stamps",
  geofenceMeters: 550,
  locations: []
};

const UUID_RE = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i;
let db;
let currentLocation;
let validationEmail = "";

const el = (id) => document.getElementById(id);
const text = (id, value) => { const node = el(id); if (node) node.textContent = value; };

async function initDB() {
  if (db) return;
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
}

function reqP(req) { return new Promise((resolve, reject) => { req.onsuccess = () => resolve(req.result); req.onerror = () => reject(req.error); }); }
function store(name, mode = "readonly") { return db.transaction(name, mode).objectStore(name); }

function getTokenMetaKey(locationId, email) {
  return `secure-token:${locationId}:${email.toLowerCase()}`;
}

async function readStoredToken(locationId, email) {
  const entry = await reqP(store("meta").get(getTokenMetaKey(locationId, email)));
  return entry?.value || "";
}

async function writeStoredToken(locationId, email, token) {
  await reqP(store("meta", "readwrite").put({
    key: getTokenMetaKey(locationId, email),
    value: token,
    updatedAt: new Date().toISOString()
  }));
}

function setStatus(message) { text("statusSection", message); }

async function loadAppConfig() {
  const res = await fetch("?action=locations", { headers: { Accept: "application/json" } });
  const data = await res.json().catch(() => ({}));

  if (!res.ok || !data.success || !Array.isArray(data.locations)) {
    throw new Error(data.error || "Could not load location config from server.");
  }

  CONFIG.appName = data.appName || CONFIG.appName;
  CONFIG.headerText = data.headerText || CONFIG.headerText;
  CONFIG.geofenceMeters = Number.isFinite(data.geofenceMeters) ? data.geofenceMeters : CONFIG.geofenceMeters;
  validationEmail = (data.validationEmail || "").trim();
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

async function getCurrentGeolocation() {
  if (!navigator.geolocation) throw new Error("Geolocation is not available in this browser.");
  return new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy || 0, simulated: false }),
      () => reject(new Error("Geolocation permission denied. Enable location to collect a stamp.")),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
  });
}

async function renderStampGrid() {
  const grid = el("stampGrid");
  if (!grid) return;
  grid.innerHTML = "";
  for (const loc of CONFIG.locations) {
    const stamp = await reqP(store("stamps").get(loc.locationId));
    const div = document.createElement("div");
    div.className = `stamp-slot ${stamp ? "collected" : ""}`;
    div.textContent = stamp ? `🍁 ${loc.locationId}` : `${loc.locationId}`;
    grid.appendChild(div);
  }
}

function showDisclaimerOnce() {
  const modal = el("disclaimerModal");
  if (!modal || localStorage.getItem("passportDisclaimerAccepted") === "1") return;

  const acceptBtn = el("disclaimerAcceptBtn") || modal.querySelector("button");
  if (!acceptBtn) {
    setStatus("Disclaimer could not be acknowledged because the accept button is missing.");
    return;
  }

  modal.classList.remove("hidden");
  acceptBtn.addEventListener("click", () => {
    localStorage.setItem("passportDisclaimerAccepted", "1");
    modal.classList.add("hidden");
  }, { once: true });
}

async function loadLocationState() {
  const rawToken = getQrTokenFromUrl();
  const qr = parseSignedQr(rawToken);
  if (!qr) {
    renderProgressMailto("");
    return setStatus("Sorry — you must be on site to tag a location. Please scan an official stop QR code.");
  }

  const location = getLocationByUuid(qr.uuid);
  if (!location) {
    return setStatus("Sorry — this QR code is not valid for the Cottage Passport event.");
  }

  currentLocation = location;
  el("collectSection")?.classList.remove("hidden");
  text("locationDisplay", `${location.locationId}. ${location.name} — ${location.tagline}`);
  renderProgressMailto("");
  setStatus(`Ready to collect at ${location.name}. Geofence radius: ${CONFIG.geofenceMeters}m.`);
}

async function saveStamp(email, geo, distance) {
  await reqP(store("stamps", "readwrite").put({
    locationId: currentLocation.locationId,
    email,
    geolocation: `${geo.latitude},${geo.longitude}`,
    distance,
    collectedAt: new Date().toISOString()
  }));
}

async function validateCollectOnServer(email, geo) {
  const response = await fetch("?action=collect", {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({
      uuid: currentLocation.qrToken,
      email,
      latitude: geo.latitude,
      longitude: geo.longitude
    })
  });

  const body = await response.json().catch(() => ({}));
  if (!response.ok || !body.success) {
    const detail = Number.isFinite(body.distance)
      ? ` You are ${Math.round(body.distance)}m away; max is ${body.allowedRadius || CONFIG.geofenceMeters}m.`
      : "";
    throw new Error((body.error || "Location validation failed.") + detail);
  }

  return body;
}

async function getSecureProgressToken(email) {
  const cached = await readStoredToken(currentLocation.locationId, email);
  if (cached) return cached;

  const response = await fetch("?action=register", {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({
      uuid: currentLocation.qrToken,
      email
    })
  });
  const body = await response.json().catch(() => ({}));

  if (!response.ok || !body.success || typeof body.token !== "string" || body.token.trim() === "") {
    throw new Error(body.error || "Could not create secure progress token.");
  }

  const secureToken = body.token.trim();
  await writeStoredToken(currentLocation.locationId, email, secureToken);
  return secureToken;
}

async function handleSubmit(e) {
  e.preventDefault();
  const email = (el("emailInput")?.value || "").trim();
  const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  el("emailError")?.classList.toggle("hidden", valid);
  if (!valid || !currentLocation) return;

  const geo = await getCurrentGeolocation();
  const result = await validateCollectOnServer(email, geo);
  await saveStamp(email, geo, result.distance);
  const secureToken = await getSecureProgressToken(email);

  el("resultSection")?.classList.remove("hidden");
  text("prizeMessage", `Stamp accepted at ${Math.round(result.distance)}m from target.`);
  renderProgressMailto(secureToken);
  setStatus("Stamp validated and saved on this device.");
  await renderStampGrid();
}

function renderProgressMailto(token) {
  const link = el("progressMailtoLink");
  if (!link) return;

  if (!token || !validationEmail) {
    link.classList.add("hidden");
    link.removeAttribute("href");
    return;
  }

  const subject = "Cottage Passport progress";
  const body = `My Cottage Passport token:

${token}`;
  link.href = `mailto:${encodeURIComponent(validationEmail)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  link.classList.remove("hidden");
}

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

async function init() {
  bindGlobalErrorHandling();
  await loadAppConfig();
  document.title = CONFIG.appName;
  text("appName", CONFIG.appName);
  text("appTagline", CONFIG.headerText);
  await initDB();
  showDisclaimerOnce();
  await loadLocationState();
  await renderStampGrid();
  el("collectForm")?.addEventListener("submit", (e) => handleSubmit(e).catch((err) => setStatus(err.message)));
}

init().catch((err) => setStatus(`Initialization failed: ${err.message}`));
