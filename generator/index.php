<?php
$validUuids = require __DIR__ . '/../config/valid-qr-uuids.php';
$locationRows = require __DIR__ . '/../config/locations.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$defaultBase = $scheme . '://' . $host . '/';

$baseUrlInput = trim((string)($_GET['base'] ?? $defaultBase));
$baseUrl = filter_var($baseUrlInput, FILTER_VALIDATE_URL) ? $baseUrlInput : $defaultBase;

function build_scan_url(string $baseUrl, string $uuid): string
{
    $parts = parse_url($baseUrl);
    $query = [];

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $query['q'] = $uuid;

    $rebuilt = '';
    if (!empty($parts['scheme'])) {
        $rebuilt .= $parts['scheme'] . '://';
    }
    if (!empty($parts['user'])) {
        $rebuilt .= $parts['user'];
        if (!empty($parts['pass'])) {
            $rebuilt .= ':' . $parts['pass'];
        }
        $rebuilt .= '@';
    }
    if (!empty($parts['host'])) {
        $rebuilt .= $parts['host'];
    }
    if (!empty($parts['port'])) {
        $rebuilt .= ':' . $parts['port'];
    }

    $rebuilt .= $parts['path'] ?? '/';
    $rebuilt .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    if (!empty($parts['fragment'])) {
        $rebuilt .= '#' . $parts['fragment'];
    }

    return $rebuilt;
}

$rows = array_map(static function (string $uuid, int $index) use ($baseUrl, $locationRows): array {
    $scanUrl = build_scan_url($baseUrl, $uuid);
    $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($scanUrl);
    $locationName = $locationRows[$index]['name'] ?? ('Location ' . ($index + 1));

    return [
        'uuid' => $uuid,
        'location_name' => $locationName,
        'scan_url' => $scanUrl,
        'qr_image_url' => $qrImageUrl,
    ];
}, $validUuids, array_keys($validUuids));
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cottage Passport QR Generator</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 2rem; color: #1f2937; }
    h1 { margin-bottom: 0.5rem; }
    form { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
    input[type="url"] { min-width: 24rem; flex: 1; padding: 0.45rem; }
    button { padding: 0.5rem 0.9rem; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #d1d5db; padding: 0.5rem; vertical-align: top; }
    th { background: #f3f4f6; text-align: left; }
    code { word-break: break-all; }
    img { width: 130px; height: 130px; }
    .print-grid { display: none; }

    @media print {
      @page { size: Letter portrait; margin: 0.3in; }
      body { margin: 0; font-size: 9pt; }
      h1, p, form, table { display: none; }
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
      .print-card-title {
        font-size: 8pt;
        line-height: 1.1;
      }
    }
  </style>
</head>
<body>
  <h1>Cottage Passport QR Generator</h1>
  <p>Generates QR images for all allowlisted UUIDs in <code>config/valid-qr-uuids.php</code>.</p>

  <form method="get">
    <label for="base">Scan destination base URL:</label>
    <input id="base" name="base" type="url" value="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">Update</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Encoded scan URL</th>
        <th>QR preview</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $index => $row): ?>
        <tr>
          <td><?= $index + 1 ?></td>
          <td><?= htmlspecialchars($row['location_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><code><?= htmlspecialchars($row['uuid'], ENT_QUOTES, 'UTF-8') ?></code></td>
          <td><code><?= htmlspecialchars($row['scan_url'], ENT_QUOTES, 'UTF-8') ?></code></td>
          <td>
            <a href="<?= htmlspecialchars($row['qr_image_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" title="<?= htmlspecialchars($row['location_name'], ENT_QUOTES, 'UTF-8') ?>">
              <img src="<?= htmlspecialchars($row['qr_image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="QR code for <?= htmlspecialchars($row['location_name'], ENT_QUOTES, 'UTF-8') ?>">
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <section class="print-grid" aria-label="Printable QR code sheet">
    <?php foreach ($rows as $row): ?>
      <article class="print-card">
        <img src="<?= htmlspecialchars($row['qr_image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="QR code for <?= htmlspecialchars($row['location_name'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="print-card-title"><?= htmlspecialchars($row['location_name'], ENT_QUOTES, 'UTF-8') ?></div>
      </article>
    <?php endforeach; ?>
  </section>
</body>
</html>
