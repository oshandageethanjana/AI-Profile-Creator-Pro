<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/http.php';

function security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // CSP allows our app scripts/styles + CDN fonts/JS.
    // Inline scripts are enabled for bootstrapping (window.__BOOT__); if you want stricter CSP, move boot code into external JS and drop 'unsafe-inline'.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline' https://unpkg.com https://cdnjs.cloudflare.com; connect-src 'self' https://unpkg.com blob:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
}

function cookie_params(): array {
    $c = config();
    $params = [
        'path' => '/',
        'secure' => (bool)$c['security']['cookie_secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (!empty($c['security']['cookie_domain'])) $params['domain'] = $c['security']['cookie_domain'];
    return $params;
}

function set_cookie(string $name, string $value, int $expiresInSeconds, bool $httpOnly = true): void {
    $params = cookie_params();
    $params['expires'] = time() + $expiresInSeconds;
    $params['httponly'] = $httpOnly;
    setcookie($name, $value, $params);
}

function clear_cookie(string $name): void {
    $params = cookie_params();
    $params['expires'] = time() - 3600;
    setcookie($name, '', $params);
}

function csrf_ensure_cookie(): string {
    $token = $_COOKIE['pa_csrf'] ?? '';
    if (!is_string($token) || strlen($token) < 20) {
        $token = bin2hex(random_bytes(24));
        // CSRF cookie must be readable by JS to attach header.
        set_cookie('pa_csrf', $token, 60 * 60 * 24 * 30, false);
    }
    return $token;
}

function csrf_validate_or_fail(): void {
    csrf_ensure_cookie();
    $cookie = $_COOKIE['pa_csrf'] ?? '';
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    // Allow form posts too.
    $post = $_POST['csrf_token'] ?? '';
    $provided = $header !== '' ? $header : $post;

    if (!is_string($cookie) || !is_string($provided) || $cookie === '' || !hash_equals($cookie, $provided)) {
        json_error('Security check failed. Please refresh and try again.', 403);
    }
}

function rate_limit_or_fail(string $bucket, int $limit, int $windowSeconds): void {
    $c = config();
    if (!$c['security']['rate_limit_enabled']) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = hash('sha256', $bucket . '|' . $ip);
    $now = time();
    $windowStart = $now - ($now % $windowSeconds);

    db()->beginTransaction();
    try {
        $row = db_one('SELECT hits, window_start FROM rate_limits WHERE `key`=? FOR UPDATE', [$key]);
        if (!$row) {
            db_exec('INSERT INTO rate_limits (`key`, bucket, ip, window_start, hits, updated_at) VALUES (?,?,?,?,1,NOW())', [$key, $bucket, $ip, date('Y-m-d H:i:s', $windowStart)]);
            db()->commit();
            return;
        }

        $storedWindow = strtotime((string)$row['window_start']) ?: 0;
        if ($storedWindow !== $windowStart) {
            db_exec('UPDATE rate_limits SET window_start=?, hits=1, updated_at=NOW() WHERE `key`=?', [date('Y-m-d H:i:s', $windowStart), $key]);
            db()->commit();
            return;
        }

        $hits = (int)$row['hits'] + 1;
        if ($hits > $limit) {
            db()->rollBack();
            json_error('Too many requests. Please slow down.', 429);
        }

        db_exec('UPDATE rate_limits SET hits=?, updated_at=NOW() WHERE `key`=?', [$hits, $key]);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        // Fail open on limiter errors.
    }
}

