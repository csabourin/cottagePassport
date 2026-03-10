<?php
/**
 * Stamp Passport — Standalone PHP backend + frontend renderer.
 *
 * Reads all configuration from JSON flat files:
 *   config/settings.json  — plugin settings (mirrors Settings.php model)
 *   config/items.json     — passport locations with per-language content
 *
 * Routes:
 *   (no action)                    → render frontend HTML
 *   ?action=locations              → GET  items list (API)
 *   ?action=collect                → POST geofence check-in (API)
 *   ?action=resolve                → GET  shortCode → item (API)
 *   ?action=contest-progress       → GET/POST contest progress sync (API)
 */

declare(strict_types=1);

// ============================================================
// LOAD CONFIGURATION
// ============================================================

$settings = json_decode(
    file_get_contents(__DIR__ . '/config/settings.json'), true
);
$allItems = json_decode(
    file_get_contents(__DIR__ . '/config/items.json'), true
);

if (!is_array($settings) || !is_array($allItems)) {
    http_response_code(500);
    echo 'Server misconfigured: invalid JSON flat files.';
    exit;
}

// ── Language detection ────────────────────────────────────────────────────────

$availableSites = $settings['sites'] ?? [
    ['handle' => 'default', 'lang' => 'en', 'name' => 'English', 'url' => '/', 'current' => true]
];

/**
 * Detect the best language to use, in priority order:
 * 1. ?lang= URL param
 * 2. Accept-Language header (first 2 chars matched to a site)
 * 3. First site in the list (default)
 */
function detect_lang(array $sites): string {
    $available = array_column($sites, 'lang');

    if (!empty($_GET['lang'])) {
        $requested = strtolower(substr(trim($_GET['lang']), 0, 2));
        if (in_array($requested, $available, true)) {
            return $requested;
        }
    }

    $acceptHeader = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if ($acceptHeader) {
        foreach (explode(',', $acceptHeader) as $entry) {
            $l = strtolower(substr(trim(strtok($entry, ';')), 0, 2));
            if (in_array($l, $available, true)) {
                return $l;
            }
        }
    }

    return $sites[0]['lang'] ?? 'en';
}

$currentLang = detect_lang($availableSites);

// Mark current site
foreach ($availableSites as &$site) {
    $site['current'] = ($site['lang'] === $currentLang);
}
unset($site);

// ── Text helpers ──────────────────────────────────────────────────────────────

/**
 * Return a UI text value for a key, respecting the 3-level fallback:
 *   1. Per-language override in settings.uiText[lang][key]
 *   2. Built-in defaults indexed by language
 *   3. Built-in defaults['default'][key]
 */
function get_text(array $settings, string $key, string $lang): string {
    $defaults = [
        'default' => [
            'orgName'          => 'Your Organization',
            'challengeName'    => 'Stamp Passport',
            'challengeTitle'   => 'Challenge',
            'scanInstructions' => 'Scan all QR codes at participating locations to complete your passport.',
            'disclaimerTitle'  => 'Before You Begin',
            'disclaimerBody'   => 'Your progress is saved locally and synced online.',
            'disclaimerButton' => "Got it, let\u2019s go!",
            'alreadyCheckedIn' => 'You already checked in here.',
            'checkingLocation' => 'Checking your location\u2026',
            'locationError'    => 'Could not determine your location. Please allow location access and try again.',
            'checkinFailed'    => 'Check-in failed. Please confirm you are at the right location.',
            'checkedIn'        => 'Checked in!',
            'qrNotRecognized'  => 'This QR code is not recognized.',
            'loadError'        => 'Could not load passport data. Please try again later.',
            'ogTitle'          => '',
            'ogDescription'    => '',
        ],
        'fr' => [
            'orgName'          => 'Votre organisation',
            'challengeName'    => 'Stamp Passport',
            'challengeTitle'   => 'D\u00e9fi',
            'scanInstructions' => 'Scannez tous les codes QR aux emplacements participants pour compl\u00e9ter votre passeport.',
            'disclaimerTitle'  => 'Avant de commencer',
            'disclaimerBody'   => 'Votre progression est sauvegard\u00e9e localement et synchronis\u00e9e en ligne.',
            'disclaimerButton' => "Compris, c\u2019est parti\u00a0!",
            'alreadyCheckedIn' => 'Vous avez d\u00e9j\u00e0 valid\u00e9 cet emplacement.',
            'checkingLocation' => 'V\u00e9rification de votre position\u2026',
            'locationError'    => "Impossible de d\u00e9terminer votre position. Veuillez autoriser l\u2019acc\u00e8s \u00e0 la localisation et r\u00e9essayer.",
            'checkinFailed'    => "\u00c9chec de la validation. Veuillez confirmer que vous \u00eates au bon emplacement.",
            'checkedIn'        => 'Validation r\u00e9ussie\u00a0!',
            'qrNotRecognized'  => "Ce code QR n\u2019est pas reconnu.",
            'loadError'        => 'Impossible de charger les donn\u00e9es du passeport. Veuillez r\u00e9essayer plus tard.',
            'ogTitle'          => '',
            'ogDescription'    => '',
        ],
    ];

    // 1. Per-language override
    $uiText = $settings['uiText'] ?? [];
    $override = $uiText[$lang][$key] ?? ($uiText['default'][$key] ?? '');
    if ($override !== '') {
        return $override;
    }

    // 2. Language-specific built-in default
    $localized = $defaults[$lang][$key] ?? null;
    if ($localized !== null && $localized !== '') {
        return $localized;
    }

    // 3. Generic built-in default
    return $defaults['default'][$key] ?? '';
}

// ── Item helpers ──────────────────────────────────────────────────────────────

/** Return enabled items in sort order. */
function get_enabled_items(array $allItems): array {
    $items = array_filter($allItems, fn($i) => !empty($i['enabled']));
    usort($items, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
    return array_values($items);
}

/** Get per-language content for an item, falling back to 'en'. */
function item_content(array $item, string $lang): array {
    return $item['content'][$lang] ?? $item['content']['en'] ?? [
        'title' => $item['shortCode'],
        'description' => '',
        'linkUrl' => '',
        'linkText' => 'Learn more',
    ];
}

/** Look up an item by shortCode (null if not found/disabled). */
function find_item_by_code(array $allItems, string $code): ?array {
    foreach ($allItems as $item) {
        if (($item['shortCode'] ?? '') === $code && !empty($item['enabled'])) {
            return $item;
        }
    }
    return null;
}

// ── URL builder ───────────────────────────────────────────────────────────────

/** Base URL for this script (strips ?action= etc.). */
function base_url(): string {
    // Trust X-Forwarded-Proto from reverse proxies (e.g. Replit, nginx, Cloudflare).
    $forwarded = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    if ($forwarded === 'https') {
        $scheme = 'https';
    } elseif ($forwarded === 'http') {
        $scheme = 'http';
    } else {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $scheme . '://' . $host . $path;
}

$base = base_url();

// ============================================================
// API ROUTING
// ============================================================

$action = $_GET['action'] ?? '';

if ($action !== '') {
    // All API responses are JSON
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    switch ($action) {
        case 'locations':
            handle_locations($settings, $allItems, $currentLang);
            break;
        case 'collect':
            handle_collect($settings, $allItems, $currentLang);
            break;
        case 'resolve':
            handle_resolve($allItems, $currentLang);
            break;
        case 'contest-progress':
            handle_contest_progress($settings);
            break;
        default:
            json_out(['success' => false, 'error' => 'Unknown action'], 400);
    }
}

// ============================================================
// API HANDLERS
// ============================================================

/**
 * GET ?action=locations
 * Returns enabled items with per-language content — same shape as the plugin.
 */
function handle_locations(array $settings, array $allItems, string $lang): void {
    $items = get_enabled_items($allItems);
    $out   = [];
    foreach ($items as $item) {
        $c    = item_content($item, $lang);
        $out[] = [
            'id'          => $item['sortOrder'] ?? 0,
            'shortCode'   => $item['shortCode'],
            'title'       => $c['title'],
            'description' => $c['description'] ?? '',
            'linkUrl'     => $c['linkUrl'] ?? '',
            'linkText'    => $c['linkText'] ?? 'Learn more',
            'imageUrl'    => $item['imageUrl'] ?? null,
            'lat'         => $item['lat'] ?? null,
            'lng'         => $item['lng'] ?? null,
        ];
    }

    json_out([
        'success'        => true,
        'pluginName'     => $settings['pluginName'] ?? 'Stamp Passport',
        'drawThreshold'  => $settings['drawThreshold'] ?? 5,
        'maxStickers'    => $settings['maxStickers'] ?? 100,
        'enableGeofence' => $settings['enableGeofence'] ?? true,
        'geofenceRadius' => $settings['geofenceRadius'] ?? 550,
        'items'          => $out,
    ]);
}

/**
 * POST ?action=collect
 * Body: { shortCode, latitude, longitude }
 * Validates geofence and returns the same shape as the plugin.
 */
function handle_collect(array $settings, array $allItems, string $lang): void {
    require_method('POST');
    $body      = read_json_body();
    $shortCode = trim((string)($body['shortCode'] ?? ''));
    $latitude  = $body['latitude']  ?? null;
    $longitude = $body['longitude'] ?? null;

    if ($shortCode === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
        json_out(['success' => false, 'error' => 'Missing or invalid shortCode/coordinates'], 400);
    }

    $item = find_item_by_code($allItems, $shortCode);
    if ($item === null) {
        json_out(['success' => false, 'error' => 'Unknown location'], 404);
    }

    $geofenceEnabled = $settings['enableGeofence'] ?? true;
    $radius          = (int)($settings['geofenceRadius'] ?? 550);
    $c               = item_content($item, $lang);

    $itemPayload = [
        'id'        => $item['sortOrder'] ?? 0,
        'shortCode' => $item['shortCode'],
        'title'     => $c['title'],
    ];

    // If geofence disabled or no coordinates on item → allow
    if (!$geofenceEnabled || empty($item['lat']) || empty($item['lng'])) {
        json_out([
            'success'       => true,
            'distance'      => 0,
            'allowedRadius' => $radius,
            'item'          => $itemPayload,
        ]);
    }

    $distance = haversine_meters(
        (float)$latitude, (float)$longitude,
        (float)$item['lat'], (float)$item['lng']
    );

    if ($distance > $radius) {
        json_out([
            'success'       => false,
            'error'         => 'Outside allowed radius',
            'distance'      => (int)round($distance),
            'allowedRadius' => $radius,
            'item'          => $itemPayload,
        ], 403);
    }

    json_out([
        'success'       => true,
        'distance'      => (int)round($distance),
        'allowedRadius' => $radius,
        'item'          => $itemPayload,
    ]);
}

/**
 * GET ?action=resolve&q=<shortCode>
 * Returns item basic info — same shape as the plugin.
 */
function handle_resolve(array $allItems, string $lang): void {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        json_out(['success' => false, 'error' => 'Missing q parameter'], 400);
    }

    $item = find_item_by_code($allItems, $q);
    if ($item === null) {
        json_out(['success' => false, 'error' => 'Unknown location'], 404);
    }

    $c = item_content($item, $lang);
    json_out([
        'success' => true,
        'item'    => [
            'id'          => $item['sortOrder'] ?? 0,
            'shortCode'   => $item['shortCode'],
            'title'       => $c['title'],
            'description' => $c['description'] ?? '',
        ],
    ]);
}

/**
 * GET  ?action=contest-progress&cid=<uuid>   → fetch progress + write token
 * POST ?action=contest-progress              → upsert progress (optimistic concurrency)
 *
 * Progress is stored as JSON files in data/contest/{cid}.json.
 */
function handle_contest_progress(array $settings): void {
    $dataDir = __DIR__ . '/data/contest';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }

    $secret = $settings['writeTokenSecret'] ?? 'change-me';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $cid = trim($_GET['cid'] ?? '');
        if (!is_valid_cid($cid)) {
            json_out(['success' => false, 'error' => 'invalid_cid'], 400);
        }

        $file = $dataDir . '/' . safe_cid_filename($cid);
        if (!is_file($file)) {
            $token = issue_write_token($cid, $secret);
            json_out(['success' => false, 'error' => 'not_found', 'writeToken' => $token], 404);
        }

        $record = json_decode(file_get_contents($file), true);
        $token  = issue_write_token($cid, $secret);

        json_out([
            'ok'              => true,
            'cid'             => $record['cid'],
            'revision'        => $record['revision'],
            'payload'         => json_decode($record['payload_json'], true),
            'serverUpdatedAt' => $record['updated_at'],
            'writeToken'      => $token,
        ]);
    }

    // POST
    require_method('POST');
    $body           = read_json_body();
    $cid            = trim((string)($body['cid'] ?? ''));
    $payload        = $body['payload'] ?? null;
    $clientRevision = (int)($body['clientRevision'] ?? 0);
    $writeToken     = trim((string)($body['writeToken'] ?? ''));

    if (!is_valid_cid($cid)) {
        json_out(['success' => false, 'error' => 'invalid_cid'], 400);
    }

    if (!validate_write_token($writeToken, $cid, $secret)) {
        // Issue a fresh token and return the error so the client can retry
        $fresh = issue_write_token($cid, $secret);
        json_out(['success' => false, 'error' => 'invalid_write_token', 'writeToken' => $fresh], 403);
    }

    if (!validate_payload($payload)) {
        json_out(['success' => false, 'error' => 'invalid_payload'], 400);
    }

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($payloadJson) > 32768) {
        json_out(['success' => false, 'error' => 'payload_too_large'], 413);
    }

    $payloadHash = hash('sha256', $payloadJson);
    $file        = $dataDir . '/' . safe_cid_filename($cid);
    $now         = date('c');

    // Atomic read-modify-write with file lock
    $fh = fopen($file . '.lock', 'c');
    if (!$fh || !flock($fh, LOCK_EX)) {
        json_out(['success' => false, 'error' => 'server_lock_error'], 500);
    }

    try {
        if (!is_file($file)) {
            // New record — revision must be 0 for a fresh create
            $newRecord = [
                'cid'          => $cid,
                'revision'     => 1,
                'payload_json' => $payloadJson,
                'payload_hash' => $payloadHash,
                'updated_at'   => $now,
                'created_at'   => $now,
            ];
            file_put_contents($file, json_encode($newRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $newToken = issue_write_token($cid, $secret);
            json_out(['ok' => true, 'revision' => 1, 'writeToken' => $newToken]);
        }

        $existing = json_decode(file_get_contents($file), true);
        if (!is_array($existing)) {
            json_out(['success' => false, 'error' => 'server_data_error'], 500);
        }

        // Idempotent: same hash, nothing to write
        if ($existing['payload_hash'] === $payloadHash) {
            $newToken = issue_write_token($cid, $secret);
            json_out(['ok' => true, 'revision' => $existing['revision'], 'writeToken' => $newToken]);
        }

        // Optimistic concurrency check
        if ($existing['revision'] !== $clientRevision) {
            $serverPayload = json_decode($existing['payload_json'], true);
            json_out([
                'ok'              => false,
                'error'           => 'conflict',
                'serverRevision'  => $existing['revision'],
                'serverPayload'   => $serverPayload,
                'serverUpdatedAt' => $existing['updated_at'],
            ], 409);
        }

        $newRevision = $existing['revision'] + 1;
        $updated = array_merge($existing, [
            'revision'     => $newRevision,
            'payload_json' => $payloadJson,
            'payload_hash' => $payloadHash,
            'updated_at'   => $now,
        ]);
        file_put_contents($file, json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $newToken = issue_write_token($cid, $secret);
        json_out(['ok' => true, 'revision' => $newRevision, 'writeToken' => $newToken]);

    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($file . '.lock');
    }
}

// ── Contest progress helpers ──────────────────────────────────────────────────

function is_valid_cid(string $s): bool {
    return (bool)preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $s
    );
}

function safe_cid_filename(string $cid): string {
    return preg_replace('/[^a-f0-9\-]/i', '', $cid) . '.json';
}

function validate_payload($p): bool {
    if (!is_array($p)) return false;
    if (!isset($p['schemaVersion'], $p['contestVersion'], $p['progress'], $p['updatedAt'])) return false;
    $pr = $p['progress'];
    if (!is_array($pr)) return false;
    if (!isset($pr['stepsCompleted'], $pr['score'])) return false;
    if (!is_array($pr['stepsCompleted'])) return false;
    return true;
}

/**
 * Write tokens: base64url(json({cid,exp})).base64url(hmac-sha256)
 * 10-minute TTL.
 */
function issue_write_token(string $cid, string $secret): string {
    $payload = b64url_encode(json_encode(['cid' => $cid, 'exp' => time() + 600]));
    $sig     = b64url_encode(hash_hmac('sha256', $payload, $secret, true));
    return $payload . '.' . $sig;
}

function validate_write_token(string $token, string $cid, string $secret): bool {
    if ($token === '') return false;
    $parts = explode('.', $token);
    if (count($parts) !== 2) return false;
    [$payload, $sig] = $parts;

    $expected = b64url_encode(hash_hmac('sha256', $payload, $secret, true));
    if (!hash_equals($expected, $sig)) return false;

    $decoded = b64url_decode($payload);
    if ($decoded === false) return false;

    $data = json_decode($decoded, true);
    if (!is_array($data)) return false;
    if (($data['cid'] ?? '') !== $cid) return false;
    if (!isset($data['exp']) || (int)$data['exp'] < time()) return false;

    return true;
}

// ── General helpers ───────────────────────────────────────────────────────────

function json_out(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        json_out(['success' => false, 'error' => $method . ' method required'], 405);
    }
}

function read_json_body(): array {
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

function haversine_meters(float $aLat, float $aLng, float $bLat, float $bLng): float {
    $R    = 6371000;
    $dLat = deg2rad($bLat - $aLat);
    $dLng = deg2rad($bLng - $aLng);
    $h    = sin($dLat / 2) ** 2 + cos(deg2rad($aLat)) * cos(deg2rad($bLat)) * sin($dLng / 2) ** 2;
    return 2 * $R * asin(sqrt($h));
}

function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data) {
    $rem = strlen($data) % 4;
    if ($rem) {
        $data .= str_repeat('=', 4 - $rem);
    }
    return base64_decode(strtr($data, '-_', '+/'), true);
}

// ============================================================
// FRONTEND HTML RENDERER
// ============================================================

/**
 * No ?action= → render the full passport frontend HTML.
 * Mirrors the plugin's _layout.twig + index.twig output.
 */

$enabledItems = get_enabled_items($allItems);

// Build the sites list for the language switcher + early-redirect script
$sitesForJs = [];
foreach ($availableSites as $s) {
    $sitesForJs[] = [
        'handle'  => $s['handle'],
        'lang'    => $s['lang'],
        'name'    => $s['name'],
        'url'     => $s['url'],
        'current' => $s['current'],
    ];
}

// API URLs pointing back to this script with different actions
$locationsUrl      = $base . '?action=locations';
$collectUrl        = $base . '?action=collect';
$resolveUrl        = $base . '?action=resolve';
$contestProgressUrl = $base . '?action=contest-progress';

// Text for current language
function t(array $settings, string $key, string $lang): string {
    return get_text($settings, $key, $lang);
}

$contestRules = ($settings['contestRules'][$currentLang] ?? null)
             ?: ($settings['contestRules']['default'] ?? []);

// Escape helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function js(string $s): string { return addslashes($s); }

$pluginName        = h($settings['pluginName'] ?? 'Stamp Passport');
$faviconUrl        = $settings['faviconUrl'] ?? null;
$ogTitle           = t($settings, 'ogTitle', $currentLang);
$ogDesc            = t($settings, 'ogDescription', $currentLang);
$ogImageUrl        = $settings['ogImageUrl'] ?? null;
$logoUrl           = $settings['logoUrl'] ?? null;
$woodPanelUrl      = $settings['woodPanelUrl'] ?? null;
$checkedMarkerUrl  = $settings['checkedMarkerUrl'] ?? null;
$bodyBgUrl         = $settings['bodyBackgroundUrl'] ?? null;
$bodyBgMode        = $settings['bodyBackgroundMode'] ?? 'cover';
$bodyBgSize        = $settings['bodyBackgroundSize'] ?? '800px';
$bodyBgColor       = $settings['bodyBackgroundColor'] ?? null;
$primaryColor      = $settings['primaryColor'] ?? null;
$primaryColorDark  = $settings['primaryColorDark'] ?? null;
$accentColor       = $settings['accentColor'] ?? null;
$customCss         = $settings['customCss'] ?? null;
$ga4Id             = $settings['ga4MeasurementId'] ?? null;
$showLangSwitcher  = !empty($settings['showLanguageSwitcher']);
$requireDisclaimer = !empty($settings['requireDisclaimerAck']);

$drawThreshold   = (int)($settings['drawThreshold'] ?? 5);
$maxStickers     = (int)($settings['maxStickers'] ?? 100);
$enableGeofence  = !empty($settings['enableGeofence']);
$contestVersion  = $settings['contestVersion'] ?? '2026.02';
$sitesJson       = json_encode($sitesForJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
?><!DOCTYPE html>
<html lang="<?= h($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <?php if ($faviconUrl): ?>
    <link rel="icon" href="<?= h($faviconUrl) ?>" type="image/png">
    <?php endif; ?>

    <title><?= $pluginName ?></title>

    <?php if ($ogTitle): ?><meta property="og:title" content="<?= h($ogTitle) ?>"><?php endif; ?>
    <?php if ($ogDesc):  ?><meta property="og:description" content="<?= h($ogDesc) ?>"><?php endif; ?>
    <?php if ($ogImageUrl): ?><meta property="og:image" content="<?= h($ogImageUrl) ?>"><?php endif; ?>
    <?php if ($ogTitle || $ogDesc || $ogImageUrl): ?>
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($base) ?>">
    <?php endif; ?>

    <!-- Early language redirect — runs before body to prevent flicker -->
    <script>
    (function(){
        var html = document.documentElement;
        var unlocked = false;
        function unlock() {
            if (unlocked) return;
            unlocked = true;
            html.style.visibility = '';
        }
        html.style.visibility = 'hidden';
        setTimeout(unlock, 800);

        try {
            var currentLang = '<?= js($currentLang) ?>';
            var sites = <?= $sitesJson ?>;
            var params = new URL(window.location.href).searchParams;
            var LS_KEY = 'passport:langChoice';
            var DB_NAME = 'stamp-passport';
            var DB_VERSION = 2;
            var META_STORE = 'meta';
            var META_KEY = 'langChoice';

            function normalizeLang(lang) { return ((lang||'').substring(0,2)).toLowerCase(); }

            function findSiteByLang(lang) {
                for (var i=0; i<sites.length; i++) {
                    if ((sites[i].lang||'').toLowerCase()===lang) return sites[i];
                }
                return null;
            }

            function writeLocalLang(lang) { try { localStorage.setItem(LS_KEY,lang); } catch(e){} }
            function readLocalLang() { try { return normalizeLang(localStorage.getItem(LS_KEY)); } catch(e){ return ''; } }

            function writeIdbLang(lang) {
                return new Promise(function(resolve) {
                    try {
                        var req = indexedDB.open(DB_NAME, DB_VERSION);
                        req.onupgradeneeded = function() {
                            var d=req.result;
                            if (!d.objectStoreNames.contains(META_STORE))
                                d.createObjectStore(META_STORE,{keyPath:'key'});
                        };
                        req.onsuccess = function() {
                            try {
                                var d=req.result;
                                var tx=d.transaction(META_STORE,'readwrite');
                                tx.objectStore(META_STORE).put({key:META_KEY,value:lang,updatedAt:new Date().toISOString()});
                                tx.oncomplete=function(){try{d.close();}catch(e){}resolve();};
                                tx.onerror=function(){try{d.close();}catch(e){}resolve();};
                            } catch(e){ resolve(); }
                        };
                        req.onerror=function(){resolve();};
                        req.onblocked=function(){resolve();};
                    } catch(e){ resolve(); }
                });
            }

            function readIdbLang() {
                return new Promise(function(resolve) {
                    try {
                        var req = indexedDB.open(DB_NAME, DB_VERSION);
                        req.onupgradeneeded = function() {
                            var d=req.result;
                            if (!d.objectStoreNames.contains(META_STORE))
                                d.createObjectStore(META_STORE,{keyPath:'key'});
                        };
                        req.onsuccess = function() {
                            try {
                                var d=req.result;
                                var tx=d.transaction(META_STORE,'readonly');
                                var getReq=tx.objectStore(META_STORE).get(META_KEY);
                                getReq.onsuccess=function(){
                                    var rec=getReq.result;
                                    var lang=normalizeLang(rec&&rec.value);
                                    try{d.close();}catch(e){}
                                    resolve(lang||'');
                                };
                                getReq.onerror=function(){try{d.close();}catch(e){}resolve('');};
                            } catch(e){ resolve(''); }
                        };
                        req.onerror=function(){resolve('');};
                        req.onblocked=function(){resolve('');};
                    } catch(e){ resolve(''); }
                });
            }

            function persistLangChoice(lang) {
                var normalized = normalizeLang(lang);
                if (!normalized) return Promise.resolve('');
                writeLocalLang(normalized);
                return writeIdbLang(normalized).then(function(){ return normalized; });
            }

            function redirectToLang(lang) {
                var normalized = normalizeLang(lang);
                var site = findSiteByLang(normalized);
                if (!site || site.current || normalized===currentLang) return false;
                // Append ?lang= so the server picks the right language
                var url = site.url.replace(/\?.*$/, '') + '?lang=' + encodeURIComponent(normalized);
                persistLangChoice(normalized).then(function(){ window.location.replace(url); }, function(){ window.location.replace(url); });
                return true;
            }

            function getDevicePreferredLang() {
                var langs = navigator.languages || [navigator.language||navigator.userLanguage||''];
                for (var i=0; i<langs.length; i++) {
                    var l=normalizeLang(langs[i]);
                    if (!l) continue;
                    if (findSiteByLang(l)) return l;
                }
                return '';
            }

            if (params.has('lang') || params.has('cid')) {
                persistLangChoice(currentLang).then(unlock, unlock);
                return;
            }

            var localChoice = readLocalLang();
            if (localChoice) {
                if (redirectToLang(localChoice)) return;
                persistLangChoice(localChoice).then(unlock, unlock);
                return;
            }

            readIdbLang().then(function(savedChoice) {
                if (savedChoice) {
                    if (redirectToLang(savedChoice)) return;
                    persistLangChoice(savedChoice).then(unlock, unlock);
                    return;
                }
                var preferred = getDevicePreferredLang();
                if (preferred && redirectToLang(preferred)) return;
                unlock();
            }, function() {
                var preferred = getDevicePreferredLang();
                if (preferred && redirectToLang(preferred)) return;
                unlock();
            });
        } catch(e) { unlock(); }
    })();
    </script>

    <link rel="stylesheet" href="passport.css">

    <?php if ($woodPanelUrl): ?>
    <style>.passport-header.has-wood-bg { --passport-wood-img: url('<?= h($woodPanelUrl) ?>'); }</style>
    <?php endif; ?>

    <?php if ($bodyBgUrl): ?>
    <style>
    body.stamp-passport {
        <?php if ($bodyBgMode === 'tiled'): ?>
        background-image: url('<?= h($bodyBgUrl) ?>');
        background-size: auto;
        background-repeat: repeat;
        background-position: left top;
        <?php elseif ($bodyBgMode === 'custom'): ?>
        background-image: url('<?= h($bodyBgUrl) ?>');
        background-size: <?= h($bodyBgSize) ?>;
        background-repeat: no-repeat;
        background-position: center top;
        <?php if ($bodyBgColor): ?>background-color: <?= h($bodyBgColor) ?>;<?php endif; ?>
        <?php else: ?>
        background-image: url('<?= h($bodyBgUrl) ?>');
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center top;
        <?php if ($bodyBgColor): ?>background-color: <?= h($bodyBgColor) ?>;<?php endif; ?>
        <?php endif; ?>
    }
    </style>
    <?php endif; ?>

    <?php if ($primaryColor || $primaryColorDark || $accentColor): ?>
    <style>
    :root {
        <?php if ($primaryColor): ?>--passport-teal: <?= h($primaryColor) ?>;--passport-focus: <?= h($primaryColor) ?>;<?php endif; ?>
        <?php if ($primaryColorDark): ?>--passport-teal-dark: <?= h($primaryColorDark) ?>;<?php endif; ?>
        <?php if ($accentColor): ?>--passport-green: <?= h($accentColor) ?>;<?php endif; ?>
    }
    </style>
    <?php endif; ?>

    <?php if ($customCss): ?>
    <style id="stamp-passport-custom-css"><?= str_replace('</style', '<\/style', $customCss) ?></style>
    <?php endif; ?>

    <?php if ($ga4Id): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= h($ga4Id) ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= js($ga4Id) ?>');
    </script>
    <?php endif; ?>
</head>
<body class="stamp-passport">
    <main class="passport-main">

        <!-- Header — wood panel with circular logo -->
        <header class="passport-header<?= $woodPanelUrl ? ' has-wood-bg' : '' ?>">

            <!-- Language switcher (top-right) -->
            <?php if ($showLangSwitcher && count($availableSites) > 1): ?>
            <nav class="passport-lang-nav" aria-label="Language">
                <?php foreach ($availableSites as $s): ?>
                    <?php if (!$s['current']): ?>
                    <a href="<?= h(rtrim($s['url'], '/')) ?>?lang=<?= h($s['lang']) ?>"
                       class="passport-lang-link"
                       data-passport-lang="<?= h($s['lang']) ?>"
                       data-passport-href="<?= h(rtrim($s['url'], '/')) ?>?lang=<?= h($s['lang']) ?>"
                       lang="<?= h($s['lang']) ?>"
                       hreflang="<?= h($s['lang']) ?>"
                       aria-label="<?= h($s['name']) ?>">
                        <?= h($s['lang']) ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>

            <div class="passport-header-logo">
                <?php if ($logoUrl): ?>
                    <img src="<?= h($logoUrl) ?>" alt="<?= $pluginName ?>" class="passport-logo">
                <?php else: ?>
                    <div class="passport-logo-placeholder">🍁</div>
                <?php endif; ?>
            </div>

            <p class="passport-org"><?= nl2br(h(t($settings, 'orgName', $currentLang))) ?></p>

            <h1 class="passport-title">
                <span class="passport-title-light"><?= h(t($settings, 'challengeName', $currentLang)) ?></span>
                <span class="passport-title-bold"><?= h(t($settings, 'challengeTitle', $currentLang)) ?></span>
            </h1>
        </header>

        <!-- Instruction strip -->
        <div class="passport-scan-strip"><?= h(t($settings, 'scanInstructions', $currentLang)) ?></div>

        <!-- Status area (JS-populated) -->
        <section id="statusSection" class="passport-status hidden" role="status" aria-live="polite" aria-atomic="true"></section>

        <!-- Item list -->
        <section class="passport-grid" id="stampGrid">
            <?php foreach ($enabledItems as $item):
                $c = item_content($item, $currentLang);
                $title = h($c['title'] ?? '');
                $linkUrl  = h($c['linkUrl']  ?? '');
                $linkText = h($c['linkText'] ?? 'Learn more');
            ?>
            <article class="stamp-slot"
                     data-code="<?= h($item['shortCode']) ?>"
                     data-id="<?= h((string)($item['sortOrder'] ?? 0)) ?>">
                <button class="stamp-slot-btn"
                        type="button"
                        aria-label="Details: <?= $title ?>"
                        aria-haspopup="dialog"
                        data-item-id="<?= h((string)($item['sortOrder'] ?? 0)) ?>"
                        data-item-link-url="<?= $linkUrl ?>"
                        data-item-link-text="<?= $linkText ?>">
                    <div class="stamp-image">
                        <?php if (!empty($item['imageUrl'])): ?>
                            <img src="<?= h($item['imageUrl']) ?>" alt="" loading="lazy">
                        <?php endif; ?>
                        <div class="stamp-check hidden<?= $checkedMarkerUrl ? ' has-image' : '' ?>" aria-hidden="true">
                            <?php if ($checkedMarkerUrl): ?>
                                <img src="<?= h($checkedMarkerUrl) ?>" alt="">
                            <?php else: ?>
                                &#10003;
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="stamp-info">
                        <h3 class="stamp-title"><?= $title ?></h3>
                    </div>
                    <span class="stamp-slot-chevron" aria-hidden="true">&#8250;</span>
                </button>
                <template id="item-desc-<?= h((string)($item['sortOrder'] ?? 0)) ?>"><?= $c['description'] ?? '' ?></template>
            </article>
            <?php endforeach; ?>
        </section>

        <!-- Progress bar -->
        <section class="passport-progress" aria-label="Progress">
            <div class="progress-bar"
                 role="progressbar"
                 aria-valuenow="0"
                 aria-valuemin="0"
                 aria-valuemax="<?= count($enabledItems) ?>"
                 aria-describedby="progressText">
                <div class="progress-fill" id="progressFill" style="width:0%"></div>
            </div>
            <p class="progress-text" id="progressText" aria-live="polite"></p>
        </section>

        <!-- Contest rules strip (optional) -->
        <?php if (!empty($contestRules['linkText'])): ?>
        <div class="passport-contest-rules-strip">
            <button type="button"
                    class="passport-rules-trigger"
                    id="contestRulesTrigger"
                    aria-haspopup="dialog"
                    aria-controls="contestRulesModal"
                    data-open-modal="contestRulesModal">
                <?= h($contestRules['linkText']) ?>
            </button>
        </div>
        <?php endif; ?>

        <!-- Item detail modal (shared, populated by JS) -->
        <div id="itemDetailModal" class="passport-modal hidden"
             role="dialog"
             aria-modal="true"
             aria-labelledby="itemDetailModalTitle">
            <div class="passport-modal-backdrop"></div>
            <div class="passport-modal-content">
                <button class="passport-modal-close" aria-label="Close">&times;</button>
                <h2 id="itemDetailModalTitle" class="item-detail-title"></h2>
                <div class="item-detail-body"></div>
                <div class="item-detail-footer">
                    <a class="passport-btn passport-btn-secondary item-detail-link hidden"
                       target="_blank"
                       rel="noopener noreferrer"></a>
                </div>
            </div>
        </div>

        <!-- Contest rules modal (optional) -->
        <?php if (!empty($contestRules['linkText'])): ?>
        <div id="contestRulesModal"
             class="passport-modal hidden"
             role="dialog"
             aria-modal="true"
             aria-labelledby="contestRulesModalTitle">
            <div class="passport-modal-backdrop"></div>
            <div class="passport-modal-content">
                <button class="passport-modal-close" aria-label="Close">&times;</button>
                <h2 id="contestRulesModalTitle"><?= h($contestRules['linkText']) ?></h2>
                <div class="contest-rules-body"><?= $contestRules['modalContent'] ?? '' ?></div>
                <?php if (!empty($contestRules['fullRulesUrl'])): ?>
                <div class="contest-rules-footer">
                    <a href="<?= h($contestRules['fullRulesUrl']) ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="passport-btn passport-btn-secondary">
                        <?= h(!empty($contestRules['fullRulesText']) ? $contestRules['fullRulesText'] : 'Read the full rules') ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Disclaimer modal (shown once) -->
        <div id="disclaimerModal" class="passport-modal hidden" role="dialog" aria-labelledby="disclaimerTitle">
            <div class="passport-modal-backdrop"></div>
            <div class="passport-modal-content">
                <h2 id="disclaimerTitle"><?= h(t($settings, 'disclaimerTitle', $currentLang)) ?></h2>
                <p><?= h(t($settings, 'disclaimerBody', $currentLang)) ?></p>
                <button id="disclaimerAccept" class="passport-btn passport-btn-primary">
                    <?= h(t($settings, 'disclaimerButton', $currentLang)) ?>
                </button>
            </div>
        </div>

    </main>

    <!-- Passport configuration for passport.js -->
    <script>
        window.__PASSPORT_CONFIG__ = {
            locationsUrl:        '<?= js($locationsUrl) ?>',
            collectUrl:          '<?= js($collectUrl) ?>',
            resolveUrl:          '<?= js($resolveUrl) ?>',
            contestProgressUrl:  '<?= js($contestProgressUrl) ?>',
            drawThreshold:       <?= $drawThreshold ?>,
            maxStickers:         <?= $maxStickers ?>,
            enableGeofence:      <?= $enableGeofence ? 'true' : 'false' ?>,
            contestVersion:      '<?= js($contestVersion) ?>',
            showLanguageSwitcher: <?= $showLangSwitcher ? 'true' : 'false' ?>,
            requireDisclaimerAck: <?= $requireDisclaimer ? 'true' : 'false' ?>,
            lang:  '<?= js($currentLang) ?>',
            sites: <?= $sitesJson ?>,
            text: {
                alreadyCheckedIn: '<?= js(t($settings, 'alreadyCheckedIn', $currentLang)) ?>',
                checkingLocation: '<?= js(t($settings, 'checkingLocation', $currentLang)) ?>',
                locationError:    '<?= js(t($settings, 'locationError',    $currentLang)) ?>',
                checkinFailed:    '<?= js(t($settings, 'checkinFailed',    $currentLang)) ?>',
                checkedIn:        '<?= js(t($settings, 'checkedIn',        $currentLang)) ?>',
                qrNotRecognized:  '<?= js(t($settings, 'qrNotRecognized',  $currentLang)) ?>',
                loadError:        '<?= js(t($settings, 'loadError',        $currentLang)) ?>',
                learnMore:        'Learn more',
                errorPrefix:      'Error: ',
            },
        };
    </script>
    <script src="passport.js"></script>
</body>
</html>
