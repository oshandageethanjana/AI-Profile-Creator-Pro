<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/credits.php';

function auth_access_cookie_name(): string { return 'pa_access'; }
function auth_refresh_cookie_name(): string { return 'pa_refresh'; }

function auth_current_user(): ?array {
    credits_ensure_schema();
    $jwt = $_COOKIE[auth_access_cookie_name()] ?? '';
    if (!is_string($jwt) || $jwt === '') return null;
    $payload = jwt_verify($jwt);
    if (!$payload) return null;
    $uid = (int)($payload['sub'] ?? 0);
    if ($uid <= 0) return null;

    $user = db_one('SELECT id, name, email, role, plan, pro_ends_at, credits_balance, email_verified_at, created_at FROM users WHERE id=?', [$uid]);
    if (!$user) return null;

    // Auto-downgrade if expiry passed.
    if (($user['plan'] ?? 'free') === 'pro' && !empty($user['pro_ends_at']) && strtotime((string)$user['pro_ends_at']) < time()) {
        db_exec('UPDATE users SET plan=\'free\', pro_ends_at=NULL WHERE id=?', [$uid]);
        $user['plan'] = 'free';
        $user['pro_ends_at'] = null;
    }

    return $user;
}

function auth_is_pro(?array $user): bool {
    return $user && ($user['plan'] ?? 'free') === 'pro';
}

function auth_require_user(): array {
    $u = auth_current_user();
    if (!$u) json_error('Please sign in to continue.', 401);
    return $u;
}

function auth_require_admin(): array {
    $u = auth_require_user();
    if (($u['role'] ?? 'user') !== 'admin') json_error('Forbidden', 403);
    return $u;
}

function auth_issue_tokens(int $userId, string $sessionId): void {
    $access = jwt_sign(['sub' => $userId, 'sid' => $sessionId], 15 * 60, 'access');
    set_cookie(auth_access_cookie_name(), $access, 15 * 60, true);
}

function auth_rotate_refresh_session(int $userId): string {
    $sid = bin2hex(random_bytes(16));
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);

    db_exec(
        'INSERT INTO sessions (id, user_id, refresh_hash, ip, user_agent, expires_at, created_at) VALUES (?,?,?,?,?,?,NOW())',
        [$sid, $userId, $hash, $ip, $ua, $expiresAt]
    );

    set_cookie(auth_refresh_cookie_name(), $sid . '.' . $raw, 60 * 60 * 24 * 30, true);
    return $sid;
}

function auth_clear_tokens(): void {
    clear_cookie(auth_access_cookie_name());
    clear_cookie(auth_refresh_cookie_name());
}

function auth_try_refresh_access(): bool {
    $cookie = $_COOKIE[auth_refresh_cookie_name()] ?? '';
    if (!is_string($cookie) || !str_contains($cookie, '.')) return false;
    [$sid, $raw] = explode('.', $cookie, 2);
    if ($sid === '' || $raw === '') return false;

    $row = db_one('SELECT id, user_id, refresh_hash, expires_at, revoked_at FROM sessions WHERE id=?', [$sid]);
    if (!$row) return false;
    if (!empty($row['revoked_at'])) return false;
    if (strtotime((string)$row['expires_at']) < time()) return false;
    if (!hash_equals((string)$row['refresh_hash'], hash('sha256', $raw))) return false;

    auth_issue_tokens((int)$row['user_id'], (string)$row['id']);
    return true;
}

