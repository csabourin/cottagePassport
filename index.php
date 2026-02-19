<?php
/**
 * Cottage Passport - PHP Backend Validation API
 * Hosted at: canadainacottage.replit.app
 *
 * Endpoints:
 *   GET  ?action=resolve&q=<encrypted>          Decrypt QR, return UUID
 *   POST ?action=register  {uuid, email}         Generate signed token + mailto link
 *   GET  ?action=validate&token=<token>          Verify a token
 *   POST ?action=generate  {uuid, admin_key}     (Admin) Create encrypted QR data
 *
 * Required environment variables:
 *   COTTAGE_AES_KEY            AES-256 encryption secret (any length, hashed to 32 bytes)
 *   COTTAGE_HMAC_SECRET        HMAC-SHA256 signing secret for tokens
 *   COTTAGE_VALIDATION_EMAIL   Email address where users send their tokens
 *   COTTAGE_ADMIN_KEY          Admin secret for the generate endpoint
 *
 * Optional environment variables:
 *   COTTAGE_TOKEN_EXPIRY_DAYS  Token lifetime in days (default: 7)
 *   COTTAGE_BASE_URL           Base URL for generated QR links
 *                              (default: https://canadainacottage.replit.app)
 */

// ============================================================
// CONFIGURATION
// ============================================================

$config = [
    // AES-256-CBC needs exactly 32 bytes; derive from env secret via SHA-256
    'aes_key'          => hash('sha256', getenv('COTTAGE_AES_KEY') ?: '', true),
    'hmac_secret'      => getenv('COTTAGE_HMAC_SECRET') ?: '',
    'validation_email' => getenv('COTTAGE_VALIDATION_EMAIL') ?: '',
    'admin_key'        => getenv('COTTAGE_ADMIN_KEY') ?: '',
    'token_expiry'     => ((int)(getenv('COTTAGE_TOKEN_EXPIRY_DAYS') ?: 7)) * 86400,
    'base_url'         => rtrim(
        getenv('COTTAGE_BASE_URL') ?: 'https://canadainacottage.replit.app', '/'
    ),
];

// ============================================================
// BOOT CHECKS
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS — restrict in production to your client origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ============================================================
// VALID UUIDs (shared with client config)
// ============================================================

$validUuids = require __DIR__ . '/config/valid-qr-uuids.php';
$locationRows = require __DIR__ . '/config/locations.php';
$locationsByUuid = build_locations_by_uuid($validUuids, $locationRows);

// ============================================================
// ROUTING
// ============================================================

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'locations':
        handle_locations($locationsByUuid);
        break;
    case 'collect':
        handle_collect($locationsByUuid);
        break;
    case 'resolve':
    case 'register':
    case 'validate':
    case 'generate':
        if (getenv('COTTAGE_AES_KEY') === false || $config['hmac_secret'] === '') {
            json_out(['success' => false, 'error' => 'Server misconfigured'], 500);
        }
        switch ($action) {
            case 'resolve':   handle_resolve($config, $validUuids); break;
            case 'register':  handle_register($config, $validUuids); break;
            case 'validate':  handle_validate($config, $validUuids); break;
            case 'generate':  handle_generate($config, $validUuids); break;
        }
        break;
    default:
        json_out([
            'success' => false,
            'error'   => 'Unknown action',
            'usage'   => 'action= resolve | register | validate | locations | collect',
        ], 400);
        break;
}

// ============================================================
// HANDLERS
// ============================================================

/**
 * GET ?action=resolve&q=<encrypted>
 *
 * Decrypts the AES-256-CBC payload from a QR code and returns the cottage UUID.
 * The client uses the UUID to navigate to the correct cottage page.
 */
function handle_resolve(array $cfg, array $uuids) {
    $q = $_GET['q'] ?? '';
    if ($q === '') {
        json_out(['success' => false, 'error' => 'Missing q parameter'], 400);
    }

    $data = decrypt_payload($q, $cfg['aes_key']);
    if ($data === null || !isset($data['uuid'])) {
        json_out(['success' => false, 'error' => 'Invalid or corrupted QR code'], 400);
    }

    $uuid = $data['uuid'];

    if (!is_uuid_v4($uuid)) {
        json_out(['success' => false, 'error' => 'Malformed UUID in QR data'], 400);
    }

    if (!in_array($uuid, $uuids, true)) {
        json_out(['success' => false, 'error' => 'Unknown cottage location'], 404);
    }

    json_out(['success' => true, 'uuid' => $uuid]);
}

/**
 * POST ?action=register
 * Body: {"uuid": "<cottage-uuid>", "email": "<visitor-email>"}
 *
 * Creates an HMAC-signed token embedding the UUID + email + timestamp,
 * and returns a mailto: link pre-filled with that token addressed to
 * the backend-configured validation email.
 */
function handle_register(array $cfg, array $uuids) {
    require_method('POST');

    $body  = read_json_body();
    $uuid  = trim($body['uuid']  ?? '');
    $email = trim($body['email'] ?? '');

    if ($uuid === '' || $email === '') {
        json_out(['success' => false, 'error' => 'Missing uuid or email'], 400);
    }

    if (!is_uuid_v4($uuid) || !in_array($uuid, $uuids, true)) {
        json_out(['success' => false, 'error' => 'Invalid cottage UUID'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['success' => false, 'error' => 'Invalid email address'], 400);
    }

    if ($cfg['validation_email'] === '') {
        json_out(['success' => false, 'error' => 'Validation email not configured'], 500);
    }

    $token = sign_token($uuid, $email, $cfg['hmac_secret']);

    $subject = rawurlencode('Cottage Passport - Validation Token');
    $body_text = rawurlencode(
        "My Cottage Passport validation token:\r\n\r\n"
        . $token . "\r\n\r\n"
        . "--- Do not modify above this line ---"
    );
    $mailto = 'mailto:' . rawurlencode($cfg['validation_email'])
            . '?subject=' . $subject
            . '&body='    . $body_text;

    json_out([
        'success' => true,
        'token'   => $token,
        'mailto'  => $mailto,
    ]);
}

/**
 * GET ?action=validate&token=<token>
 *
 * Verifies the HMAC signature and expiry of a token, then returns
 * the embedded UUID, email, and timestamps.
 */
function handle_validate(array $cfg, array $uuids) {
    $token = $_GET['token'] ?? '';
    if ($token === '') {
        json_out(['success' => false, 'error' => 'Missing token parameter'], 400);
    }

    $result = verify_token($token, $cfg['hmac_secret'], $cfg['token_expiry']);

    if ($result === null) {
        json_out(['success' => false, 'error' => 'Invalid token'], 401);
    }

    if (!empty($result['expired'])) {
        json_out([
            'success'   => false,
            'error'     => 'Token expired',
            'uuid'      => $result['uuid'],
            'email'     => $result['email'],
            'issued_at' => date('c', $result['iat']),
        ], 401);
    }

    if (!in_array($result['uuid'], $uuids, true)) {
        json_out(['success' => false, 'error' => 'Unknown cottage location'], 404);
    }

    json_out([
        'success'    => true,
        'uuid'       => $result['uuid'],
        'email'      => $result['email'],
        'issued_at'  => date('c', $result['iat']),
        'expires_at' => date('c', $result['iat'] + $cfg['token_expiry']),
    ]);
}

/**
 * POST ?action=generate
 * Body: {"uuid": "<cottage-uuid>", "admin_key": "<secret>"}
 *
 * Admin-only. Encrypts a UUID with AES-256-CBC and returns both the
 * cipher blob and the full QR-code URL.
 */
function handle_generate(array $cfg, array $uuids) {
    require_method('POST');

    $body     = read_json_body();
    $adminKey = $body['admin_key'] ?? '';
    $uuid     = trim($body['uuid'] ?? '');

    if ($cfg['admin_key'] === '' || !hash_equals($cfg['admin_key'], $adminKey)) {
        json_out(['success' => false, 'error' => 'Unauthorized'], 403);
    }

    if (!is_uuid_v4($uuid) || !in_array($uuid, $uuids, true)) {
        json_out(['success' => false, 'error' => 'Invalid cottage UUID'], 400);
    }

    $encrypted = encrypt_payload(['uuid' => $uuid], $cfg['aes_key']);
    $qr_url    = $cfg['base_url'] . '/?q=' . urlencode($encrypted);

    json_out([
        'success'   => true,
        'uuid'      => $uuid,
        'encrypted' => $encrypted,
        'qr_url'    => $qr_url,
    ]);
}

function handle_locations(array $locationsByUuid) {
    $locations = [];
    foreach ($locationsByUuid as $uuid => $location) {
        $locations[] = [
            'locationId' => $location['locationId'],
            'name' => $location['name'],
            'tagline' => $location['tagline'],
            'uuid' => $uuid,
        ];
    }

    json_out([
        'success' => true,
        'appName' => 'Cottage Passport Canada',
        'headerText' => 'Collect all 30 Canadiana Cottage stamps',
        'geofenceMeters' => 550,
        'locations' => $locations,
    ]);
}

function handle_collect(array $locationsByUuid) {
    require_method('POST');

    $body = read_json_body();
    $uuid = trim((string)($body['uuid'] ?? ''));
    $email = trim((string)($body['email'] ?? ''));
    $latitude = $body['latitude'] ?? null;
    $longitude = $body['longitude'] ?? null;

    if ($uuid === '' || $email === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
        json_out(['success' => false, 'error' => 'Missing or invalid uuid/email/coordinates'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['success' => false, 'error' => 'Invalid email address'], 400);
    }

    $location = $locationsByUuid[$uuid] ?? null;
    if ($location === null) {
        json_out(['success' => false, 'error' => 'Unknown cottage location'], 404);
    }

    $distance = haversine_meters((float)$latitude, (float)$longitude, (float)$location['lat'], (float)$location['lng']);
    $allowedRadius = 550;
    if ($distance > $allowedRadius) {
        json_out([
            'success' => false,
            'error' => 'Outside allowed radius',
            'distance' => round($distance),
            'allowedRadius' => $allowedRadius,
            'location' => [
                'locationId' => $location['locationId'],
                'name' => $location['name'],
                'tagline' => $location['tagline'],
            ],
        ], 403);
    }

    json_out([
        'success' => true,
        'distance' => round($distance),
        'allowedRadius' => $allowedRadius,
        'location' => [
            'locationId' => $location['locationId'],
            'name' => $location['name'],
            'tagline' => $location['tagline'],
        ],
    ]);
}

// ============================================================
// CRYPTO HELPERS
// ============================================================

/** AES-256-CBC encrypt an associative array; returns URL-safe base64. */
function encrypt_payload(array $payload, string $key) {
    $json = json_encode($payload);
    $iv   = random_bytes(16);

    $ciphertext = openssl_encrypt($json, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) {
        json_out(['success' => false, 'error' => 'Encryption failed'], 500);
    }

    return b64url_encode($iv . $ciphertext);
}

/** AES-256-CBC decrypt a URL-safe base64 blob; returns assoc array or null. */
function decrypt_payload(string $encrypted, string $key) {
    $raw = b64url_decode($encrypted);
    if ($raw === false || strlen($raw) < 17) {
        return null;
    }

    $iv         = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);

    $json = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/** Create a signed token: base64url(payload).base64url(hmac). */
function sign_token(string $uuid, string $email, string $secret) {
    $payload = b64url_encode(json_encode([
        'uuid'  => $uuid,
        'email' => $email,
        'iat'   => time(),
    ]));
    $sig = b64url_encode(hash_hmac('sha256', $payload, $secret, true));

    return $payload . '.' . $sig;
}

/** Verify a signed token; returns payload array (with optional 'expired' flag) or null. */
function verify_token(string $token, string $secret, int $expiry) {
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }

    [$payload, $sig] = $parts;

    $expected = b64url_encode(hash_hmac('sha256', $payload, $secret, true));
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    $decoded = b64url_decode($payload);
    if ($decoded === false) {
        return null;
    }

    $data = json_decode($decoded, true);
    if (!is_array($data) || !isset($data['uuid'], $data['email'], $data['iat'])) {
        return null;
    }

    if (time() - (int)$data['iat'] > $expiry) {
        return ['expired' => true] + $data;
    }

    return $data;
}

// ============================================================
// UTILITY HELPERS
// ============================================================

function b64url_encode(string $data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data) {
    $remainder = strlen($data) % 4;
    if ($remainder !== 0) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'), true);
}

function is_uuid_v4(string $s) {
    return (bool)preg_match(
        '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i',
        $s
    );
}

function json_out(array $data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function read_json_body() {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        json_out(['success' => false, 'error' => 'Empty request body'], 400);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_out(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    return $data;
}

function require_method(string $method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_out(['success' => false, 'error' => $method . ' method required'], 405);
    }
}

function build_locations_by_uuid(array $uuids, array $locationRows) {
    if (count($uuids) !== count($locationRows)) {
        json_out(['success' => false, 'error' => 'Server misconfigured: location map size mismatch'], 500);
    }

    $result = [];
    foreach ($locationRows as $idx => $locationRow) {
        $uuid = $uuids[$idx] ?? null;
        if (!is_string($uuid) || !isset($locationRow['locationId'], $locationRow['name'], $locationRow['tagline'], $locationRow['lat'], $locationRow['lng'])) {
            json_out(['success' => false, 'error' => 'Server misconfigured: invalid location map'], 500);
        }
        $result[$uuid] = $locationRow;
    }

    return $result;
}

function haversine_meters(float $aLat, float $aLng, float $bLat, float $bLng) {
    $earthRadius = 6371000;
    $dLat = deg2rad($bLat - $aLat);
    $dLng = deg2rad($bLng - $aLng);
    $h = sin($dLat / 2) ** 2
        + cos(deg2rad($aLat)) * cos(deg2rad($bLat)) * sin($dLng / 2) ** 2;
    return 2 * $earthRadius * asin(sqrt($h));
}
