<?php
/**
 * Stamp Passport QR Generator
 *
 * Reads items from config/items.json (same flat file used by index.php).
 * Generates a printable sheet of QR codes for all enabled items.
 *
 * Usage:
 *   ?base=<scan destination URL>  Override the base URL embedded in each QR code.
 *   ?lang=<en|fr>                 Use per-language item titles in the preview table.
 *   ?fg=<hex>                     QR foreground colour (default from settings or #000000).
 *   ?bg=<hex>                     QR background colour (default from settings or #ffffff).
 *   ?center=<url>                 Centre image URL embedded in QR codes (optional).
 *
 * The scan URL format is: <base>?q=<shortCode>
 * This matches what index.php's ?action=resolve endpoint expects.
 */

declare(strict_types=1);

$configDir = __DIR__ . '/../config';
$settings  = json_decode(file_get_contents($configDir . '/settings.json'), true) ?? [];
$allItems  = json_decode(file_get_contents($configDir . '/items.json'),    true) ?? [];

// Filter to enabled items in sort order
$items = array_filter($allItems, fn($i) => !empty($i['enabled']));
usort($items, fn($a, $b) => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
$items = array_values($items);

// Language
$availableLangs = array_column($settings['sites'] ?? [['lang' => 'en']], 'lang');
$lang = strtolower(substr(trim($_GET['lang'] ?? 'en'), 0, 2));
if (!in_array($lang, $availableLangs, true)) {
    $lang = $availableLangs[0] ?? 'en';
}

// Base URL for scan links (defaults to app root one level above /generator/)
$scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptPath  = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$rootPath    = rtrim(dirname(dirname($scriptPath)), '/') . '/';
$defaultBase = $scheme . '://' . $host . $rootPath;

$baseUrlInput = trim((string)($_GET['base'] ?? $defaultBase));
$baseUrl = filter_var($baseUrlInput, FILTER_VALIDATE_URL) ? $baseUrlInput : $defaultBase;

// QR colours (from settings or query params)
$defaultFg = $settings['qrForegroundColor'] ?? '#000000';
$defaultBg = $settings['qrBackgroundColor'] ?? '#ffffff';

$fgInput = trim($_GET['fg'] ?? $defaultFg);
$bgInput = trim($_GET['bg'] ?? $defaultBg);
$fg = preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $fgInput) ? $fgInput : $defaultFg;
$bg = preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $bgInput) ? $bgInput : $defaultBg;

// Centre image (from settings or query param)
$qrCenterImageUrl = trim($_GET['center'] ?? ($settings['qrCenterImageUrl'] ?? ''));

// ── Helpers ───────────────────────────────────────────────────────────────────

function build_scan_url(string $baseUrl, string $shortCode): string
{
    $parts = parse_url($baseUrl);
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['q'] = $shortCode;

    $rebuilt = '';
    if (!empty($parts['scheme'])) $rebuilt .= $parts['scheme'] . '://';
    if (!empty($parts['user'])) {
        $rebuilt .= $parts['user'];
        if (!empty($parts['pass'])) $rebuilt .= ':' . $parts['pass'];
        $rebuilt .= '@';
    }
    if (!empty($parts['host'])) $rebuilt .= $parts['host'];
    if (!empty($parts['port'])) $rebuilt .= ':' . $parts['port'];
    $rebuilt .= $parts['path'] ?? '/';
    $rebuilt .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    if (!empty($parts['fragment'])) $rebuilt .= '#' . $parts['fragment'];

    return $rebuilt;
}

function item_title(array $item, string $lang): string
{
    return $item['content'][$lang]['title']
        ?? $item['content']['en']['title']
        ?? $item['shortCode'];
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ── Build rows ────────────────────────────────────────────────────────────────

$rows = [];
foreach ($items as $index => $item) {
    $shortCode = $item['shortCode'];
    $scanUrl   = build_scan_url($baseUrl, $shortCode);

    $qrParams = [
        'size'  => '260',
        'text'  => $scanUrl,
        'dark'  => ltrim($fg, '#'),
        'light' => ltrim($bg, '#'),
    ];
    if ($qrCenterImageUrl !== '') {
        $qrParams['centerImageSizeRatio'] = '0.44';
        $qrParams['centerImageUrl']       = $qrCenterImageUrl;
    }
    $qrImageUrl = 'https://quickchart.io/qr?' . http_build_query($qrParams, '', '&', PHP_QUERY_RFC3986);

    $rows[] = [
        'shortCode'     => $shortCode,
        'location_name' => item_title($item, $lang),
        'scan_url'      => $scanUrl,
        'qr_image_url'  => $qrImageUrl,
    ];
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($settings['pluginName'] ?? 'Stamp Passport') ?> — QR Generator</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 2rem; color: #1f2937; }
    h1 { margin-bottom: 0.25rem; }
    p.subtitle { margin: 0 0 1.25rem; color: #6b7280; font-size: 0.9rem; }
    fieldset { border: 1px solid #d1d5db; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1.25rem; }
    legend { font-weight: 600; padding: 0 0.25rem; }
    form { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-end; }
    .field { display: flex; flex-direction: column; gap: 0.2rem; }
    label { font-size: 0.82rem; color: #374151; }
    input[type="url"] { min-width: 22rem; flex: 1; padding: 0.45rem; }
    input[type="text"] { width: 6rem; padding: 0.45rem; }
    input[type="color"] { width: 3rem; height: 2rem; padding: 0.1rem; cursor: pointer; }
    button { padding: 0.5rem 0.9rem; align-self: flex-end; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #d1d5db; padding: 0.5rem; vertical-align: top; }
    th { background: #f3f4f6; text-align: left; }
    code { word-break: break-all; font-size: 0.8rem; }
    img.qr-preview { width: 130px; height: 130px; }
    .print-grid { display: none; }

    @media print {
      @page { size: Letter portrait; margin: 0.3in; }
      body { margin: 0; font-size: 9pt; }
      h1, p, form, fieldset, table { display: none; }
      .print-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.08in;
      }
      .print-card {
        border: 1px solid #d1d5db;
        padding: 0.05in;
        text-align: center;
        page-break-inside: avoid;
      }
      .print-card img {
        width: 1.35in;
        height: 1.35in;
        display: block;
        margin: 0 auto 0.03in;
      }
      .print-card-title { font-size: 8pt; line-height: 1.1; }
    }
  </style>
</head>
<body>
  <h1><?= h($settings['pluginName'] ?? 'Stamp Passport') ?> — QR Generator</h1>
  <p class="subtitle">
    Reads enabled items from <code>config/items.json</code>.
    Each QR encodes a scan URL: <code>&lt;base&gt;?q=&lt;shortCode&gt;</code>.
    Press <strong>Print sheet</strong> (or Ctrl+P) for a 4-column letter-size sheet.
  </p>

  <fieldset>
    <legend>Options</legend>
    <form method="get">
      <div class="field">
        <label for="base">Scan destination base URL</label>
        <input id="base" name="base" type="url" value="<?= h($baseUrl) ?>" style="min-width:22rem">
      </div>
      <div class="field">
        <label for="lang">Language</label>
        <input id="lang" name="lang" type="text" value="<?= h($lang) ?>" placeholder="en">
      </div>
      <div class="field">
        <label for="fg">QR foreground</label>
        <input id="fg" name="fg" type="color" value="<?= h(strlen($fg) === 7 ? $fg : '#000000') ?>">
      </div>
      <div class="field">
        <label for="bg">QR background</label>
        <input id="bg" name="bg" type="color" value="<?= h(strlen($bg) === 7 ? $bg : '#ffffff') ?>">
      </div>
      <div class="field">
        <label for="center">Centre image URL (optional)</label>
        <input id="center" name="center" type="url" value="<?= h($qrCenterImageUrl) ?>" style="min-width:14rem">
      </div>
      <button type="submit">Update</button>
      <button type="button" onclick="window.print()">Print sheet</button>
    </form>
  </fieldset>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Short code</th>
        <th>Location</th>
        <th>Scan URL</th>
        <th>QR preview</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $index => $row): ?>
        <tr>
          <td><?= $index + 1 ?></td>
          <td><code><?= h($row['shortCode']) ?></code></td>
          <td><?= h($row['location_name']) ?></td>
          <td>
            <a href="<?= h($row['scan_url']) ?>" target="_blank" rel="noopener noreferrer">
              <code><?= h($row['scan_url']) ?></code>
            </a>
          </td>
          <td>
            <a href="<?= h($row['qr_image_url']) ?>" target="_blank" rel="noopener noreferrer"
               title="<?= h($row['location_name']) ?>">
              <img class="qr-preview"
                   src="<?= h($row['qr_image_url']) ?>"
                   alt="QR code for <?= h($row['location_name']) ?>">
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <section class="print-grid" aria-label="Printable QR code sheet">
    <?php foreach ($rows as $row): ?>
      <article class="print-card">
        <img src="<?= h($row['qr_image_url']) ?>"
             alt="QR code for <?= h($row['location_name']) ?>">
        <div class="print-card-title"><?= h($row['location_name']) ?></div>
      </article>
    <?php endforeach; ?>
  </section>
</body>
</html>
