const ENV_SECRET = "CHANGE_ME_DEV_SECRET"; // Frontend secret is visible to users; real secrets must be server-side.

const CONFIG = {
  appName: "Cottage Passport Canada",
  headerText: "Collect all 30 Canadiana Cottage stamps",
  emailSubjectTemplate: "{APP_NAME} — Stamp confirmation for {LOCATION_NAME}",
  emailBodyTemplate:
`Hello,

I collected a stamp in {APP_NAME}.
Location: {LOCATION_NAME} (#{LOCATION_ID})
Time: {TIMESTAMP}
Email: {EMAIL}
Device: {DEVICE_ID}
QR token: {QR_TOKEN}
Coordinates: {GEO_COORDS}

Encrypted token:
{TOKEN}
`,
  prizeTiers: [
    { min: 0, max: 9, message: "Great start — keep exploring the cottage trail!" },
    { min: 10, max: 19, message: "You're on fire — half-way to grand prize territory." },
    { min: 20, max: 29, message: "Elite collector status unlocked. One push left!" },
    { min: 30, max: 30, message: "All 30 stamps complete! Claim the grand Canadiana prize." }
  ],
  theme: { accent: "#af2a2a", accent2: "#1f5c42" },
  locations: [
    [1,"Lakeside Dock","550e8400-e29b-41d4-a716-446655440001","Misty sunrise by the water"],
    [2,"Cedar Sauna","550e8400-e29b-41d4-a716-446655440002","Warm cedar steam"],
    [3,"Maple Grove","550e8400-e29b-41d4-a716-446655440003","Crunchy leaves and sap"],
    [4,"Pine Trail","550e8400-e29b-41d4-a716-446655440004","Needles underfoot"],
    [5,"Campfire Ring","550e8400-e29b-41d4-a716-446655440005","Stories at dusk"],
    [6,"Canoe Launch","550e8400-e29b-41d4-a716-446655440006","Paddle into calm"],
    [7,"Birch Overlook","550e8400-e29b-41d4-a716-446655440007","White bark skyline"],
    [8,"Rocky Point","550e8400-e29b-41d4-a716-446655440008","Granite and gulls"],
    [9,"Fox Den","550e8400-e29b-41d4-a716-446655440009","Quiet woodland lane"],
    [10,"Loon Bay","550e8400-e29b-41d4-a716-44665544000a","Echoing loon calls"],
    [11,"Spruce Cabin","550e8400-e29b-41d4-a716-44665544000b","Classic plaid porch"],
    [12,"Aurora Hill","550e8400-e29b-41d4-a716-44665544000c","Northern night skies"],
    [13,"Snowshoe Loop","550e8400-e29b-41d4-a716-44665544000d","Powder path"],
    [14,"Blueberry Patch","550e8400-e29b-41d4-a716-44665544000e","Sweet summer stop"],
    [15,"Trout Stream","550e8400-e29b-41d4-a716-44665544000f","Silver ripples"],
    [16,"Hemlock Ridge","550e8400-e29b-41d4-a716-446655440010","Tall evergreens"],
    [17,"Northern Porch","550e8400-e29b-41d4-a716-446655440011","Rocking-chair views"],
    [18,"Cabin Pantry","550e8400-e29b-41d4-a716-446655440012","Jam jars and tea"],
    [19,"Map Room","550e8400-e29b-41d4-a716-446655440013","Pins and trails"],
    [20,"Storm Watch Deck","550e8400-e29b-41d4-a716-446655440014","Rain on tin roof"],
    [21,"Moose Meadow","550e8400-e29b-41d4-a716-446655440015","Wild morning paths"],
    [22,"S'more Station","550e8400-e29b-41d4-a716-446655440016","Toasty treats"],
    [23,"Whispering Reeds","550e8400-e29b-41d4-a716-446655440017","Windy marsh edge"],
    [24,"Paddle Shed","550e8400-e29b-41d4-a716-446655440018","Stacked cedar paddles"],
    [25,"Bear Bell Trail","550e8400-e29b-41d4-a716-446655440019","Rugged lookout"],
    [26,"Lantern Nook","550e8400-e29b-41d4-a716-44665544001a","Warm evening glow"],
    [27,"Tamarack Bend","550e8400-e29b-41d4-a716-44665544001b","Golden needles"],
    [28,"Ice Fishing Hut","550e8400-e29b-41d4-a716-44665544001c","Frozen lake day"],
    [29,"Wool Blanket Loft","550e8400-e29b-41d4-a716-44665544001d","Cozy reading corner"],
    [30,"Grand Maple Arch","550e8400-e29b-41d4-a716-44665544001e","Final passport stamp"]
  ].map(([locationId, name, qrToken, tagline]) => ({ locationId, name, qrToken, tagline }))
};

let db;
let stream;
let currentLocation;
let lastTokenRecord;

const el = (id) => document.getElementById(id);

function getQrTokenFromUrl() {
  const url = new URL(window.location.href);
  const q = url.searchParams.get("q");
  if (q) return q.trim();
  const match = window.location.hash.match(/^#\/q\/([a-f0-9-]{36})$/i);
  return match ? match[1] : "";
}

function openTxn(store, mode = "readonly") {
  return db.transaction(store, mode).objectStore(store);
}

async function initDB() {
  if (db) return db;
  db = await new Promise((resolve, reject) => {
    const req = indexedDB.open("cottage-passport", 1);
    req.onupgradeneeded = () => {
      const d = req.result;
      if (!d.objectStoreNames.contains("meta")) d.createObjectStore("meta", { keyPath: "key" });
      if (!d.objectStoreNames.contains("stamps")) d.createObjectStore("stamps", { keyPath: "locationId" });
      if (!d.objectStoreNames.contains("tokens")) d.createObjectStore("tokens", { keyPath: "id", autoIncrement: true });
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
  return db;
}

function getReq(req) {
  return new Promise((resolve, reject) => {
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function getOrCreateDeviceId() {
  const store = openTxn("meta", "readwrite");
  const existing = await getReq(store.get("deviceId"));
  if (existing?.value) return existing.value;
  const createdAt = new Date().toISOString();
  const value = crypto.randomUUID();
  await getReq(store.put({ key: "deviceId", value }));
  await getReq(store.put({ key: "createdAt", value: createdAt }));
  return value;
}

function getLocationByQrToken(qrToken) {
  return CONFIG.locations.find((loc) => loc.qrToken === qrToken) || null;
}

async function hasStamp(locationId) {
  const item = await getReq(openTxn("stamps").get(locationId));
  return item || null;
}

async function saveStampOnce(location, tokenRecordId) {
  const existing = await hasStamp(location.locationId);
  if (existing) return { saved: false, record: existing };
  const stamp = {
    locationId: location.locationId,
    qrToken: location.qrToken,
    firstCollectedAt: new Date().toISOString(),
    tokenId: tokenRecordId
  };
  await getReq(openTxn("stamps", "readwrite").add(stamp));
  return { saved: true, record: stamp };
}

function bufToB64Url(buf) {
  const bytes = new Uint8Array(buf);
  let s = "";
  bytes.forEach((b) => (s += String.fromCharCode(b)));
  return btoa(s).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}

function b64UrlToBuf(str) {
  const base = str.replace(/-/g, "+").replace(/_/g, "/") + "===".slice((str.length + 3) % 4);
  const bin = atob(base);
  const arr = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
  return arr.buffer;
}

async function deriveKey(secret, salt) {
  const enc = new TextEncoder();
  const baseKey = await crypto.subtle.importKey("raw", enc.encode(secret), "PBKDF2", false, ["deriveKey"]);
  return crypto.subtle.deriveKey(
    { name: "PBKDF2", hash: "SHA-256", salt, iterations: 120000 },
    baseKey,
    { name: "AES-GCM", length: 256 },
    false,
    ["encrypt", "decrypt"]
  );
}

async function encryptPayload(payload) {
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const key = await deriveKey(ENV_SECRET, salt);
  const data = new TextEncoder().encode(JSON.stringify(payload));
  const ciphertext = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, data);
  return `v1.${bufToB64Url(salt)}.${bufToB64Url(iv)}.${bufToB64Url(ciphertext)}`;
}

async function decryptToken(token) {
  const [version, saltB64, ivB64, cipherB64] = token.split(".");
  if (version !== "v1" || !saltB64 || !ivB64 || !cipherB64) throw new Error("Invalid token format");
  const salt = b64UrlToBuf(saltB64);
  const iv = new Uint8Array(b64UrlToBuf(ivB64));
  const cipher = b64UrlToBuf(cipherB64);
  const key = await deriveKey(ENV_SECRET, new Uint8Array(salt));
  const plain = await crypto.subtle.decrypt({ name: "AES-GCM", iv }, key, cipher);
  return JSON.parse(new TextDecoder().decode(plain));
}

function renderTemplate(template, data) {
  return template.replace(/\{([A-Z_]+)\}/g, (_, key) => String(data[key] ?? ""));
}

function buildMailto(email, subject, body) {
  return `mailto:${encodeURIComponent(email)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
}

async function startCamera() {
  if (!navigator.mediaDevices?.getUserMedia) throw new Error("Camera API unavailable");
  stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
  el("cameraPreview").srcObject = stream;
}

function captureFrame() {
  const video = el("cameraPreview");
  const canvas = el("captureCanvas");
  const ctx = canvas.getContext("2d");
  if (!video.videoWidth || !video.videoHeight) throw new Error("No camera frame available");
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
}

function stopCamera() {
  if (!stream) return;
  stream.getTracks().forEach((track) => track.stop());
  el("cameraPreview").srcObject = null;
  stream = null;
}

async function insertTokenRecord(record) {
  return getReq(openTxn("tokens", "readwrite").add(record));
}

async function getTokenById(id) {
  return getReq(openTxn("tokens").get(id));
}

async function getStampCount() {
  return getReq(openTxn("stamps").count());
}

function getPrizeMessage(count) {
  const found = CONFIG.prizeTiers.find((tier) => count >= tier.min && count <= tier.max);
  return found?.message || "Keep collecting!";
}

async function renderStampGrid() {
  const grid = el("stampGrid");
  grid.innerHTML = "";
  for (const location of CONFIG.locations) {
    const stamp = await hasStamp(location.locationId);
    const div = document.createElement("div");
    div.className = `stamp-slot ${stamp ? "collected" : ""}`;
    div.role = "listitem";
    div.innerHTML = stamp
      ? `🍁<br><strong>${location.locationId}</strong>`
      : `<span>${location.locationId}</span>`;
    div.title = location.name;
    grid.appendChild(div);
  }
}

function setStatus(message) {
  const status = el("statusSection");
  if (status) status.textContent = message;
}

function setResult(mailto) {
  const mailtoLink = el("mailtoLink");
  const result = el("resultSection");
  if (mailtoLink) mailtoLink.href = mailto;
  if (result) result.classList.remove("hidden");
}

async function getCurrentGeolocation() {
  if (!navigator.geolocation) {
    return { latitude: null, longitude: null, accuracy: null, capturedAt: null, error: "unsupported" };
  }

  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({
        latitude: Number(pos.coords.latitude.toFixed(6)),
        longitude: Number(pos.coords.longitude.toFixed(6)),
        accuracy: Math.round(pos.coords.accuracy),
        capturedAt: new Date(pos.timestamp).toISOString(),
        error: null
      }),
      (err) => resolve({ latitude: null, longitude: null, accuracy: null, capturedAt: null, error: err.message || "unavailable" }),
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
    );
  });
}

async function handleCollectSubmit(event) {
  event.preventDefault();
  if (!currentLocation) return;

  const email = el("emailInput").value.trim();
  const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  el("emailError").classList.toggle("hidden", valid);
  if (!valid) return;

  const deviceId = await getOrCreateDeviceId();
  const geo = await getCurrentGeolocation();
  const payload = {
    email,
    locationId: currentLocation.locationId,
    locationName: currentLocation.name,
    qrToken: currentLocation.qrToken,
    ts: new Date().toISOString(),
    deviceId,
    geolocation: geo
  };

  const token = await encryptPayload(payload);
  const tokenRecordId = await insertTokenRecord({
    locationId: payload.locationId,
    qrToken: payload.qrToken,
    email: payload.email,
    ts: payload.ts,
    token
  });
  const saveResult = await saveStampOnce(currentLocation, tokenRecordId);
  if (!saveResult.saved) {
    setStatus(`Stamp already collected on ${saveResult.record.firstCollectedAt}`);
    lastTokenRecord = await getTokenById(saveResult.record.tokenId);
  } else {
    setStatus(`Stamp collected for ${currentLocation.name}`);
    lastTokenRecord = await getTokenById(tokenRecordId);
  }

  const tplData = {
    APP_NAME: CONFIG.appName,
    LOCATION_NAME: currentLocation.name,
    LOCATION_ID: currentLocation.locationId,
    TIMESTAMP: payload.ts,
    EMAIL: payload.email,
    TOKEN: lastTokenRecord.token,
    DEVICE_ID: payload.deviceId,
    QR_TOKEN: currentLocation.qrToken,
    GEO_COORDS: geo.latitude != null ? `${geo.latitude}, ${geo.longitude} (±${geo.accuracy}m)` : "Not available"
  };
  const subject = renderTemplate(CONFIG.emailSubjectTemplate, tplData);
  const body = renderTemplate(CONFIG.emailBodyTemplate, tplData);
  const mailto = buildMailto(payload.email, subject, body);
  setResult(mailto);

  const help = el("emailHelp");
  if (help) {
    help.textContent = geo.latitude != null
      ? `Token and geolocation (${geo.latitude}, ${geo.longitude}) are included in your email draft.`
      : "Token included. Geolocation was unavailable or permission was denied.";
  }

  const count = await getStampCount();
  el("prizeMessage").textContent = `${count}/30 collected — ${getPrizeMessage(count)}`;
  await renderStampGrid();
}

function bindActions() {
  el("collectForm")?.addEventListener("submit", (e) => handleCollectSubmit(e).catch((err) => setStatus(err.message)));
  el("startCameraBtn")?.addEventListener("click", () => startCamera().catch((err) => setStatus(`Camera error: ${err.message}`)));
  el("captureBtn")?.addEventListener("click", () => {
    try {
      captureFrame();
      stopCamera();
      setStatus("Photo captured.");
    } catch (err) { setStatus(err.message); }
  });
  el("stopCameraBtn")?.addEventListener("click", stopCamera);
  el("decryptBtn")?.addEventListener("click", async () => {
    try {
      const parsed = await decryptToken(el("devTokenInput").value.trim());
      el("decryptOutput").textContent = JSON.stringify(parsed, null, 2);
    } catch (err) {
      el("decryptOutput").textContent = `Decrypt failed: ${err.message}`;
    }
  });
}

function renderLocationPicker() {
  const ul = el("locationList");
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
  const token = getQrTokenFromUrl();
  const validUuidLike = /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(token);
  if (!token || !validUuidLike) {
    el("locationPicker")?.classList.remove("hidden");
    setStatus("Missing or invalid QR token. Choose a location for testing.");
    return;
  }

  const location = getLocationByQrToken(token);
  if (!location) {
    el("locationPicker")?.classList.remove("hidden");
    setStatus("QR token not allowlisted. Choose a location for testing.");
    return;
  }

  currentLocation = location;
  el("collectSection")?.classList.remove("hidden");
  el("locationDisplay").textContent = `${location.locationId}. ${location.name} — ${location.tagline || ""}`;

  const existingStamp = await hasStamp(location.locationId);
  if (existingStamp) {
    lastTokenRecord = await getTokenById(existingStamp.tokenId);
    const deviceId = await getOrCreateDeviceId();
    const tplData = {
      APP_NAME: CONFIG.appName,
      LOCATION_NAME: location.name,
      LOCATION_ID: location.locationId,
      TIMESTAMP: lastTokenRecord.ts,
      EMAIL: lastTokenRecord.email,
      TOKEN: lastTokenRecord.token,
      DEVICE_ID: deviceId,
      QR_TOKEN: location.qrToken,
      GEO_COORDS: "Saved in encrypted token"
    };
    setStatus(`Stamp already collected on ${existingStamp.firstCollectedAt}`);
    setResult(buildMailto(lastTokenRecord.email,
      renderTemplate(CONFIG.emailSubjectTemplate, tplData),
      renderTemplate(CONFIG.emailBodyTemplate, tplData)));
  } else {
    setStatus(`Ready to collect stamp for ${location.name}`);
  }
}

async function init() {
  document.title = CONFIG.appName;
  if (el("appName")) el("appName").textContent = CONFIG.appName;
  if (el("appTagline")) el("appTagline").textContent = CONFIG.headerText;
  document.documentElement.style.setProperty("--accent", CONFIG.theme.accent);
  document.documentElement.style.setProperty("--accent-2", CONFIG.theme.accent2);

  await initDB();
  await getOrCreateDeviceId();
  bindActions();
  renderLocationPicker();
  await loadLocationState();
  const count = await getStampCount();
  if (el("prizeMessage")) el("prizeMessage").textContent = `${count}/30 collected — ${getPrizeMessage(count)}`;
  await renderStampGrid();
}

window.addEventListener("beforeunload", stopCamera);
init().catch((err) => setStatus(`Initialization failed: ${err.message}`));
