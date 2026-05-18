<?php
// api/admin_kiro.php — отдельный эндпоинт для Kiro Proxy (api.scarlex.ru)
// Авторизация: только админ (по сессии, email = ADMIN_EMAIL)
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!defined('ADMIN_EMAIL'))    define('ADMIN_EMAIL', 'Luckyanencko.andrej2017@yandex.ru');
if (!defined('KIRO_API_URL'))   define('KIRO_API_URL', 'http://62.109.20.160:8000');
if (!defined('KIRO_ADMIN_KEY')) define('KIRO_ADMIN_KEY', ''); // real value lives in secrets.php

// secrets.php (через db.php) уже мог установить KIRO_API_URL=https://api.scarlex.ru —
// для прямых админских вызовов лучше использовать внутренний 127.0.0.1, но https тоже работает.

// ── Auth: сессия + админский email ──
$uid = (int)($_SESSION['user_id'] ?? 0);
if (!$uid) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}
$stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
$stmt->execute([$uid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || strtolower($row['email']) !== strtolower(ADMIN_EMAIL)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// ── Helper: HTTP request to Kiro VPS ──
function kiro_call(string $method, string $path, ?array $body = null): array {
    $ch = curl_init(KIRO_API_URL . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . KIRO_ADMIN_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [
        'code' => $code,
        'data' => json_decode($resp ?: 'null', true),
        'raw'  => $resp,
        'err'  => $err,
    ];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: [];
if (!$action) $action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'status': {
            $r = kiro_call('GET', '/status');
            if ($r['code'] !== 200) throw new RuntimeException('Kiro VPS HTTP ' . $r['code'] . ' ' . $r['err']);
            echo json_encode(['ok' => true, 'status' => $r['data']]);
            exit;
        }
        case 'health': {
            $r = kiro_call('GET', '/health');
            echo json_encode(['ok' => $r['code'] === 200, 'health' => $r['data']]);
            exit;
        }
        case 'keys': {
            $r = kiro_call('GET', '/admin/keys');
            if ($r['code'] !== 200) throw new RuntimeException('HTTP ' . $r['code']);
            echo json_encode(['ok' => true, 'keys' => $r['data']['keys'] ?? []]);
            exit;
        }
        case 'add_key': {
            $key  = trim((string)($input['key'] ?? ''));
            $name = trim((string)($input['name'] ?? ''));
            $rpm  = (int)($input['rpm'] ?? 60);
            $credits_limit = (float)($input['credits_limit'] ?? 0);
            if (!$key || !$name) throw new RuntimeException('key и name обязательны');
            $r = kiro_call('POST', '/admin/keys', [
                'key' => $key, 'name' => $name, 'rpm' => $rpm, 'credits_limit' => $credits_limit,
            ]);
            if ($r['code'] !== 200) throw new RuntimeException($r['data']['detail'] ?? ('HTTP ' . $r['code']));
            echo json_encode(['ok' => true, 'result' => $r['data']]);
            exit;
        }
        case 'update_key': {
            $key = trim((string)($input['key'] ?? ''));
            if (!$key) throw new RuntimeException('key обязателен');
            $body = ['key' => $key];
            foreach (['name','rpm','enabled','credits_limit','credits_used'] as $f) {
                if (array_key_exists($f, $input)) $body[$f] = $input[$f];
            }
            $r = kiro_call('PUT', '/admin/keys', $body);
            if ($r['code'] !== 200) throw new RuntimeException($r['data']['detail'] ?? ('HTTP ' . $r['code']));
            echo json_encode(['ok' => true, 'result' => $r['data']]);
            exit;
        }
        case 'delete_key': {
            $key = trim((string)($input['key'] ?? ''));
            if (!$key) throw new RuntimeException('key обязателен');
            $r = kiro_call('DELETE', '/admin/keys/' . urlencode($key));
            if ($r['code'] !== 200) throw new RuntimeException($r['data']['detail'] ?? ('HTTP ' . $r['code']));
            echo json_encode(['ok' => true]);
            exit;
        }
        case 'topup': {
            $key    = trim((string)($input['key'] ?? ''));
            $amount = (float)($input['amount'] ?? 0);
            if (!$key || $amount <= 0) throw new RuntimeException('key и amount > 0 обязательны');
            $r = kiro_call('POST', '/admin/keys/topup', ['key' => $key, 'amount' => $amount]);
            if ($r['code'] !== 200) throw new RuntimeException($r['data']['detail'] ?? ('HTTP ' . $r['code']));
            echo json_encode(['ok' => true, 'result' => $r['data']]);
            exit;
        }
        case 'accounts': {
            $r = kiro_call('GET', '/admin/accounts');
            echo json_encode(['ok' => $r['code'] === 200, 'accounts' => $r['data']['accounts'] ?? $r['data']]);
            exit;
        }
        case 'logs': {
            $limit = max(1, min(500, (int)($_GET['limit'] ?? 100)));
            $r = kiro_call('GET', '/admin/logs?limit=' . $limit);
            echo json_encode(['ok' => $r['code'] === 200, 'logs' => $r['data']['logs'] ?? $r['data']]);
            exit;
        }
        case 'key_logs': {
            $preview = (string)($_GET['preview'] ?? '');
            $limit   = max(1, min(500, (int)($_GET['limit'] ?? 50)));
            if (!$preview) throw new RuntimeException('preview обязателен');
            $r = kiro_call('GET', '/admin/logs/key/' . urlencode($preview) . '?limit=' . $limit);
            echo json_encode(['ok' => $r['code'] === 200, 'logs' => $r['data']['logs'] ?? $r['data']]);
            exit;
        }
        case 'resolve_key': {
            $preview = (string)($_GET['preview'] ?? '');
            if (!$preview) throw new RuntimeException('preview обязателен');
            $r = kiro_call('GET', '/admin/key-resolve/' . urlencode($preview));
            echo json_encode(['ok' => $r['code'] === 200, 'data' => $r['data']]);
            exit;
        }
        case 'multipliers_get': {
            $r = kiro_call('GET', '/admin/multipliers');
            echo json_encode(['ok' => $r['code'] === 200, 'multipliers' => $r['data']['multipliers'] ?? $r['data']]);
            exit;
        }
        case 'multipliers_set': {
            $m = $input['multipliers'] ?? null;
            if (!is_array($m)) throw new RuntimeException('multipliers обязательны (object)');
            $r = kiro_call('POST', '/admin/multipliers', ['multipliers' => $m]);
            if ($r['code'] !== 200) throw new RuntimeException($r['data']['detail'] ?? ('HTTP ' . $r['code']));
            echo json_encode(['ok' => true, 'result' => $r['data']]);
            exit;
        }
        case 'metrics': {
            $hours = max(1, min(168, (int)($_GET['hours'] ?? 24)));
            $r = kiro_call('GET', '/admin/metrics?hours=' . $hours);
            echo json_encode(['ok' => $r['code'] === 200, 'metrics' => $r['data']]);
            exit;
        }
        default:
            throw new RuntimeException('Unknown action: ' . $action);
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
