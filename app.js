const CONFIG = {
  appName: "Cottage Passport Canada",
  headerText: "Collect all 30 Canadiana Cottage stamps",
  geofenceMeters: 500,
  locations: [
    [1, "Lakeside Dock", 45.4208, -75.6972, "Misty sunrise by the water"],
    [2, "Cedar Sauna", 45.4216, -75.6949, "Warm cedar steam"],
    [3, "Maple Grove", 45.4231, -75.6984, "Crunchy leaves and sap"],
    [4, "Pine Trail", 45.4225, -75.7011, "Needles underfoot"],
    [5, "Campfire Ring", 45.4197, -75.7004, "Stories at dusk"],
    [6, "Canoe Launch", 45.4189, -75.6976, "Paddle into calm"],
    [7, "Birch Overlook", 45.4242, -75.6965, "White bark skyline"],
    [8, "Rocky Point", 45.4251, -75.6992, "Granite and gulls"],
    [9, "Fox Den", 45.4174, -75.6959, "Quiet woodland lane"],
    [10, "Loon Bay", 45.4168, -75.7022, "Echoing loon calls"],
    [11, "Spruce Cabin", 45.4262, -75.6978, "Classic plaid porch"],
    [12, "Aurora Hill", 45.4276, -75.7008, "Northern night skies"],
    [13, "Snowshoe Loop", 45.4154, -75.6999, "Powder path"],
    [14, "Blueberry Patch", 45.4149, -75.6968, "Sweet summer stop"],
    [15, "Trout Stream", 45.4284, -75.6942, "Silver ripples"],
    [16, "Hemlock Ridge", 45.4291, -75.7016, "Tall evergreens"],
    [17, "Northern Porch", 45.4138, -75.6987, "Rocking-chair views"],
    [18, "Cabin Pantry", 45.4127, -75.7001, "Jam jars and tea"],
    [19, "Map Room", 45.4305, -75.6971, "Pins and trails"],
    [20, "Storm Watch Deck", 45.4312, -75.6997, "Rain on tin roof"],
    [21, "Moose Meadow", 45.4111, -75.6973, "Wild morning paths"],
    [22, "S'more Station", 45.4104, -75.7012, "Toasty treats"],
    [23, "Whispering Reeds", 45.4328, -75.6955, "Windy marsh edge"],
    [24, "Paddle Shed", 45.4334, -75.7002, "Stacked cedar paddles"],
    [25, "Bear Bell Trail", 45.4092, -75.6991, "Rugged lookout"],
    [26, "Lantern Nook", 45.4088, -75.6961, "Warm evening glow"],
    [27, "Tamarack Bend", 45.4349, -75.6986, "Golden needles"],
    [28, "Ice Fishing Hut", 45.4354, -75.7014, "Frozen lake day"],
    [29, "Wool Blanket Loft", 45.4079, -75.6977, "Cozy reading corner"],
    [30, "Grand Maple Arch", 45.4068, -75.7006, "Final passport stamp"]
  ].map(([locationId, name, lat, lng, tagline], idx) => ({
    locationId,
    name,
    lat,
    lng,
    tagline,
    qrToken: (window.VALID_QR_UUIDS || [])[idx]
  }))
};

const UUID_RE = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i;
let db;
let currentLocation;
const isDevtools = /devtools\.html$/i.test(window.location.pathname);

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
  if (!(window.VALID_QR_UUIDS || []).includes(uuid)) return null;
  return CONFIG.locations.find((l) => l.qrToken === uuid) || null;
}

function toRad(v) { return v * Math.PI / 180; }
function distanceMeters(aLat, aLng, bLat, bLng) {
  const R = 6371000;
  const dLat = toRad(bLat - aLat);
  const dLng = toRad(bLng - aLng);
  const h = Math.sin(dLat/2) ** 2 + Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * Math.sin(dLng/2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
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

function showDisclaimerOnce() {
  const modal = el("disclaimerModal");
  if (!modal || localStorage.getItem("passportDisclaimerAccepted") === "1") return;
  modal.classList.remove("hidden");
  modal.querySelector("button")?.addEventListener("click", () => {
    localStorage.setItem("passportDisclaimerAccepted", "1");
    modal.classList.add("hidden");
  }, { once: true });
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

async function handleSubmit(e) {
  e.preventDefault();
  const email = (el("emailInput")?.value || "").trim();
  const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  el("emailError")?.classList.toggle("hidden", valid);
  if (!valid || !currentLocation) return;

  const geo = await getCurrentGeolocation();
  const meters = distanceMeters(geo.latitude, geo.longitude, currentLocation.lat, currentLocation.lng);
  if (meters > CONFIG.geofenceMeters) {
    setStatus(`Outside allowed radius. You are ${Math.round(meters)}m away; max is ${CONFIG.geofenceMeters}m.`);
    return;
  }

  await saveStamp(email, geo, meters);
  el("resultSection")?.classList.remove("hidden");
  text("prizeMessage", `Stamp accepted at ${Math.round(meters)}m from target${geo.simulated ? " (simulated devtools geolocation)." : "."}`);
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
    const selected = CONFIG.locations.find((l) => String(l.locationId) === locationSelect.value) || CONFIG.locations[0];
    localStorage.setItem("passportGeoSim", JSON.stringify({ enabled: true, lat: selected.lat, lng: selected.lng, accuracy: 3 }));
    setStatus("Devtools geolocation set inside radius.");
  });
  el("simOutRadiusBtn")?.addEventListener("click", () => {
    const selected = CONFIG.locations.find((l) => String(l.locationId) === locationSelect.value) || CONFIG.locations[0];
    localStorage.setItem("passportGeoSim", JSON.stringify({ enabled: true, lat: selected.lat + 0.02, lng: selected.lng + 0.02, accuracy: 3 }));
    setStatus("Devtools geolocation set outside radius (~2km+).\n");
  });
  el("simClearBtn")?.addEventListener("click", () => {
    localStorage.removeItem("passportGeoSim");
    setStatus("Devtools geolocation simulation cleared.");
  });
}

async function init() {
  document.title = CONFIG.appName;
  text("appName", CONFIG.appName);
  text("appTagline", CONFIG.headerText);
  await initDB();
  renderLocationPicker();
  bindDevtoolsSimulation();
  showDisclaimerOnce();
  await loadLocationState();
  await renderStampGrid();
  el("collectForm")?.addEventListener("submit", (e) => handleSubmit(e).catch((err) => setStatus(err.message)));
  if (isDevtools) setStatus("Devtools mode: use geolocation simulation controls to test in/out of 500m geofence.");
}

init().catch((err) => setStatus(`Initialization failed: ${err.message}`));
