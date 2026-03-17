<?php
//settings_page
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function setting_get(string $key, ?string $default = null): ?string {
    $row = db_one('SELECT `value` FROM settings WHERE `key`=?', [$key]);
    if (!$row) return $default;
    $v = $row['value'];
    return is_string($v) ? $v : $default;
}

function setting_set(string $key, ?string $value): void {
    db_exec(
        'INSERT INTO settings (`key`,`value`,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_at=NOW()',
        [$key, $value]
    );
}

function setting_get_json(string $key, array $default = []): array {
    $raw = setting_get($key, null);
    if (!is_string($raw) || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function setting_set_json(string $key, array $value): void {
    setting_set($key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function pro_packages_default(): array {
    return [
        ['id' => 'starter', 'name' => 'PRO Starter', 'price_usd' => 9, 'credits' => 50, 'period' => 'month'],
        ['id' => 'plus', 'name' => 'PRO Plus', 'price_usd' => 19, 'credits' => 150, 'period' => 'month'],
        ['id' => 'max', 'name' => 'PRO Max', 'price_usd' => 39, 'credits' => 400, 'period' => 'month'],
    ];
}

function pro_packages_get(): array {
    $pkgs = setting_get_json('pro.packages', []);
    if (!$pkgs) $pkgs = pro_packages_default();
    // Normalize/sanitize
    $out = [];
    foreach ($pkgs as $p) {
        if (!is_array($p)) continue;
        $id = preg_replace('/[^a-z0-9_\-]/i', '', (string)($p['id'] ?? ''));
        if ($id === '') continue;
        $out[] = [
            'id' => $id,
            'name' => (string)($p['name'] ?? $id),
            'price_usd' => (int)($p['price_usd'] ?? 0),
            'credits' => (int)($p['credits'] ?? 0),
            'period' => (string)($p['period'] ?? 'month'),
        ];

        //end
    }
    return $out ?: pro_packages_default();
}

