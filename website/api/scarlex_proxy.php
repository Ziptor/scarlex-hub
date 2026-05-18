<?php
// Scarlex proxy client — обёртка над https://api.scarlex.ru/admin/keys
// Использует KIRO_API_URL + KIRO_ADMIN_KEY из secrets.php.
require_once __DIR__ . '/secrets.php';

if (!defined('SCARLEX_API_URL'))   define('SCARLEX_API_URL', defined('KIRO_API_URL') ? KIRO_API_URL : 'https://api.scarlex.ru');
if (!defined('SCARLEX_ADMIN_KEY')) define('SCARLEX_ADMIN_KEY', defined('KIRO_ADMIN_KEY') ? KIRO_ADMIN_KEY : '');

/**
 * Низкоуровневый запрос к api.scarlex.ru.
 * Возвращает ['ok' => bool, 'http' => int, 'data' => array|null, 'error' => string|null]
 */
function scarlex_request(string $method, string $path, array $body = null): array {
    $url = rtrim(SCARLEX_API_URL, '/') . $path;
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . SCARLEX_ADMIN_KEY,
        'Accept: application/json',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return ['ok' => false, 'http' => 0, 'data' => null, 'error' => 'cURL: ' . $err];
    }
    $data = json_decode($resp, true);
    $ok = ($http >= 200 && $http < 300);
    return [
        'ok'    => $ok,
        'http'  => $http,
        'data'  => $data,
        'error' => $ok ? null : (is_array($data) ? ($data['detail'] ?? $data['error'] ?? 'HTTP ' . $http) : 'HTTP ' . $http),
    ];
}

/** Сгенерировать новый api-ключ для пользователя (формат sk-scarlex-...). */
function scarlex_gen_key(): string {
    return 'sk-scarlex-' . bin2hex(random_bytes(16));
}

/**
 * Создать ключ на api.scarlex.ru.
 * $tokens — лимит в токенах (направляется в tokens_limit). hard_limit=true.
 * Возвращает ['ok'=>bool, 'key'=>string|null, 'error'=>?string]
 */
function scarlex_create_key(string $name, int $tokens, int $rpm = 60): array {
    $key = scarlex_gen_key();
    $r = scarlex_request('POST', '/admin/keys', [
        'key'           => $key,
        'name'          => $name,
        'rpm'           => $rpm,
        'credits_limit' => 0,
        'tokens_limit'  => $tokens,
        'hard_limit'    => true,
    ]);
    if (!$r['ok']) {
        return ['ok' => false, 'key' => null, 'error' => $r['error']];
    }
    return ['ok' => true, 'key' => $key, 'data' => $r['data']];
}

/** Пополнить ключ дополнительными токенами. */
function scarlex_topup_key(string $key, int $tokens): array {
    $r = scarlex_request('POST', '/admin/keys/topup-tokens', [
        'key'    => $key,
        'amount' => $tokens,
    ]);
    return $r;
}

/** Получить инфо о ключе. */
function scarlex_get_key(string $key): array {
    // Используем list + фильтр (нет одиночного GET по ключу).
    $r = scarlex_request('GET', '/admin/keys', null);
    if (!$r['ok']) return $r;
    foreach ((array)($r['data']['keys'] ?? []) as $row) {
        if (($row['key_preview'] ?? '') === substr($key, 0, 8) . '...' . substr($key, -4)) {
            return ['ok' => true, 'data' => $row];
        }
    }
    return ['ok' => false, 'error' => 'key not found on proxy'];
}

/** Удалить ключ. */
function scarlex_delete_key(string $key): array {
    return scarlex_request('DELETE', '/admin/keys/' . urlencode($key), null);
}

/** Обновить произвольные поля. */
function scarlex_update_key(string $key, array $patch): array {
    $patch['key'] = $key;
    return scarlex_request('PUT', '/admin/keys', $patch);
}
