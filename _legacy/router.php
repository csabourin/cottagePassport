<?php
/**
 * Router for PHP's built-in web server (php -S <host:port> router.php).
 *
 * Routes:
 *   ?action=…          → index.php  (all API endpoints)
 *   /                  → index.php  (renders frontend HTML)
 *   /generator/        → generator/index.php
 *   /passport.css      → passport.css  (static, served directly)
 *   /passport.js       → passport.js   (static, served directly)
 *   other static files → served as-is by the built-in server
 *
 * Devtools routes are blocked in production (REPLIT_DEPLOYMENT).
 */

declare(strict_types=1);

$uriPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$fullPath = __DIR__ . $uriPath;

// Block devtools in production
$isProduction          = getenv('REPLIT_DEPLOYMENT') !== false;
$blockedInProduction   = ['/devtools', '/devtools.html', '/devtools.js'];
if ($isProduction && in_array($uriPath, $blockedInProduction, true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found";
    return true;
}

// API endpoints
if (isset($_GET['action'])) {
    require __DIR__ . '/index.php';
    return true;
}

// Static file pass-through for any existing file that is not a directory
if ($uriPath !== '/' && is_file($fullPath)) {
    // Let the built-in server serve it natively (return false)
    return false;
}

// Named routes
switch ($uriPath) {
    case '/':
        header('Cache-Control: no-cache');
        require __DIR__ . '/index.php';
        break;

    case '/generator':
    case '/generator/':
    case '/generator/index.php':
        require __DIR__ . '/generator/index.php';
        break;

    case '/devtools':
    case '/devtools.html':
        if (!$isProduction) {
            require __DIR__ . '/devtools.html';
        } else {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Not Found";
        }
        break;

    default:
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Not Found";
        break;
}
