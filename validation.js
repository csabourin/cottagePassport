const UUID_RE = /^[a-f0-9]{8}$/i;

function parseToken(input) {
  const raw = (input || "").trim();
  if (!raw) return "";

  try {
    const asUrl = new URL(raw);
    return (asUrl.searchParams.get("q") || "").trim();
  } catch {
    return raw;
  }
}

function parseSignedQr(raw) {
  if (!raw) return null;
  if (UUID_RE.test(raw)) return { uuid: raw, signed: false };

  const [payload, sig] = raw.split(".");
  if (!payload || !sig) return null;

  try {
    const json = JSON.parse(atob(payload.replace(/-/g, "+").replace(/_/g, "/")));
    if (!UUID_RE.test(json.uuid || "")) return null;
    return { uuid: json.uuid, signed: true };
  } catch {
    return null;
  }
}

function setStatus(message) {
  const node = document.getElementById("statusSection");
  if (node) node.textContent = message;
}

function validateToken() {
  const input = document.getElementById("qrInput")?.value || "";
  const q = parseToken(input);
  const parsed = parseSignedQr(q);

  if (!parsed) {
    setStatus("Invalid token format. Provide a v4 UUID or signed token payload.");
    return;
  }

  const allowlisted = (window.VALID_QR_UUIDS || []).includes(parsed.uuid);
  if (!allowlisted) {
    setStatus(`Parsed UUID ${parsed.uuid} is well-formed but not in the allowlist.`);
    return;
  }

  setStatus(`✅ Valid Cottage Passport token. UUID ${parsed.uuid} is allowlisted${parsed.signed ? " (signed payload)." : "."}`);
}

document.getElementById("validateBtn")?.addEventListener("click", validateToken);
