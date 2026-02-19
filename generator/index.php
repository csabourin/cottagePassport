<?php
$validUuids = require __DIR__ . '/../config/valid-qr-uuids.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$defaultBase = $scheme . '://' . $host . '/index.html';

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

$rows = array_map(static function (string $uuid) use ($baseUrl): array {
    $scanUrl = build_scan_url($baseUrl, $uuid);
    $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($scanUrl);

    return [
        'uuid' => $uuid,
        'scan_url' => $scanUrl,
        'qr_image_url' => $qrImageUrl,
    ];
}, $validUuids);
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
        <th>UUID</th>
        <th>Encoded scan URL</th>
        <th>QR preview</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $index => $row): ?>
        <tr>
          <td><?= $index + 1 ?></td>
          <td><code><?= htmlspecialchars($row['uuid'], ENT_QUOTES, 'UTF-8') ?></code></td>
          <td><code><?= htmlspecialchars($row['scan_url'], ENT_QUOTES, 'UTF-8') ?></code></td>
          <td>
            <a href="<?= htmlspecialchars($row['qr_image_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
              <img src="<?= htmlspecialchars($row['qr_image_url'], ENT_QUOTES, 'UTF-8') ?>" alt="QR code for <?= htmlspecialchars($row['uuid'], ENT_QUOTES, 'UTF-8') ?>">
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
