const CONFIG = {
  appName: "Cottage Passport Canada",
  headerText: "Collect all 30 Canadiana Cottage stamps",
  geofenceMeters: 550,
  locations: []
};

const UUID_RE = /^[a-f0-9]{8}$/i;
let db;
let currentLocation;

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

function getGeoSimulation() {
  try { return JSON.parse(localStorage.getItem("passportGeoSim") || "{}"); } catch { return {}; }
}

async function getCurrentGeolocation() {
  const sim = getGeoSimulation();
  if (sim.enabled && Number.isFinite(sim.lat) && Number.isFinite(sim.lng)) {
    return { latitude: sim.lat, longitude: sim.lng, accuracy: sim.accuracy || 5, simulated: true };
  }
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

function renderLocationPicker() {
  const ul = el("locationList");
  if (!ul) return;
  ul.innerHTML = "";
  CONFIG.locations.forEach((loc) => {
    const li = document.createElement("li");
    const btn = document.createElement("button");
    btn.type = "button";
    btn.textContent = `${loc.locationId}. ${loc.name}`;
    btn.addEventListener("click", () => {
      const url = new URL(window.location.href);
      url.searchParams.set("q", loc.qrToken);
      window.location.href = url.toString();
    });
    li.appendChild(btn);
    ul.appendChild(li);
  });
}

async function loadLocationState() {
  const qr = parseSignedQr(getQrTokenFromUrl());
  if (!qr) {
    el("locationPicker")?.classList.remove("hidden");
    return setStatus("Missing/invalid QR URL. Choose a stop for testing.");
  }

  const location = getLocationByUuid(qr.uuid);
  if (!location) {
    el("locationPicker")?.classList.remove("hidden");
    return setStatus("QR UUID not allowlisted.");
  }

  currentLocation = location;
  el("collectSection")?.classList.remove("hidden");
  text("locationDisplay", `${location.locationId}. ${location.name} — ${location.tagline}`);
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

async function handleSubmit(e) {
  e.preventDefault();
  const email = (el("emailInput")?.value || "").trim();
  const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  el("emailError")?.classList.toggle("hidden", valid);
  if (!valid || !currentLocation) return;

  const geo = await getCurrentGeolocation();
  const result = await validateCollectOnServer(email, geo);
  await saveStamp(email, geo, result.distance);

  el("resultSection")?.classList.remove("hidden");
  text("prizeMessage", `Stamp accepted at ${Math.round(result.distance)}m from target${geo.simulated ? " (simulated devtools geolocation)." : "."}`);
  setStatus("Stamp validated and saved on this device.");
  await renderStampGrid();
}

function bindDevtoolsSimulation() {
  const panel = el("devGeoPanel");
  if (!panel) return;
  panel.classList.remove("hidden");
  const locationSelect = el("simLocation");
  CONFIG.locations.forEach((loc) => {
    const opt = document.createElement("option");
    opt.value = String(loc.locationId);
    opt.textContent = `${loc.locationId}. ${loc.name}`;
    locationSelect?.appendChild(opt);
  });
  el("simInRadiusBtn")?.addEventListener("click", () => {
    localStorage.setItem("passportGeoSim", JSON.stringify({ enabled: true, lat: 45.4272254, lng: -75.6942809, accuracy: 3 }));
    setStatus(`Devtools geolocation set inside ${CONFIG.geofenceMeters}m radius.`);
  });
  el("simOutRadiusBtn")?.addEventListener("click", () => {
    localStorage.setItem("passportGeoSim", JSON.stringify({ enabled: true, lat: 45.4372254, lng: -75.6842809, accuracy: 3 }));
    setStatus("Devtools geolocation set outside radius (~1km+).\n");
  });
  el("simClearBtn")?.addEventListener("click", () => {
    localStorage.removeItem("passportGeoSim");
    setStatus("Devtools geolocation simulation cleared.");
  });
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
  renderLocationPicker();
  bindDevtoolsSimulation();
  await loadLocationState();
  await renderStampGrid();
  el("collectForm")?.addEventListener("submit", (e) => handleSubmit(e).catch((err) => setStatus(err.message)));
  setStatus(`Devtools mode: use geolocation simulation controls to test in/out of ${CONFIG.geofenceMeters}m geofence.`);
}

init().catch((err) => setStatus(`Initialization failed: ${err.message}`));
