<?php

declare(strict_types=1);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$fullPath = __DIR__ . $uriPath;

// Let PHP serve existing files (assets, html, js, css, generator/index.php, etc.).
if ($uriPath !== '/' && is_file($fullPath)) {
    return false;
}

if (isset($_GET['action'])) {
    require __DIR__ . '/index.php';
    return true;
}

switch ($uriPath) {
    case '/':
        header('Cache-Control: no-cache');
        require __DIR__ . '/index.html';
        break;

    case '/validation':
    case '/validation.html':
        require __DIR__ . '/validation.html';
        break;

    case '/devtools':
    case '/devtools.html':
        require __DIR__ . '/devtools.html';
        break;

    case '/generator':
    case '/generator/':
    case '/generator/index.php':
        require __DIR__ . '/generator/index.php';
        break;

    default:
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not Found";
        break;
}
