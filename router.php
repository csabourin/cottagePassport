<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$query = parse_url($uri, PHP_URL_QUERY) ?? '';

parse_str($query, $params);

if ($path === '/api' || $path === '/api/' || $path === '/index.php' || !empty($params['action'])) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_GET = array_merge($_GET, $params);
    require __DIR__ . '/index.php';
    return true;
}

$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

header('Cache-Control: no-cache');
readfile(__DIR__ . '/index.html');
return true;
