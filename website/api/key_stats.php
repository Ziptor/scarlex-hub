<?php
// api/key_stats.php — user-side статистика для НАШИХ ключей (source='scarlex_proxy').
// Тянет данные напрямую из Kiro proxy через серверный ADMIN_KEY.
// Промпт-превью пользователю НЕ отдаём, аккаунт пула — отдаём.
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!defined('KIRO_API_URL'))   define('KIRO_API_URL', 'https://api.scarlex.ru');
if (!defined('KIRO_ADMIN_KEY')) define('KIRO_ADMIN_KEY', '');

$uid = (int)($_SESSION['user_id'] ?? 0);
if (!$uid) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? 'summary';
$keyId  = (int)($_GET['key_id'] ?? 0);
if (!$keyId) {
    echo json_encode(['ok' => false, 'error' => 'key_id required']);
    exit;
}

// Verify key ownership AND that it is a kiro/scarlex_proxy key
$stmt = $pdo->prepare("SELECT id, key_value, label, source FROM api_keys WHERE id = ? AND user_id = ?");
$stmt->execute([$keyId, $uid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Ключ не найден']);
    exit;
}
if (($row['source'] ?? '') !== 'scarlex_proxy') {
    echo json_encode(['ok' => false, 'error' => 'Этот ключ обслуживается reseller-API. Используйте старый источник.', 'source' => $row['source']]);
    exit;
}

$kv = (string)$row['key_value'];
// Match Kiro's preview formatting: key[:8] + "..." + key[-4:]
$preview = strlen($kv) > 12 ? substr($kv, 0, 8) . '...' . substr($kv, -4) : $kv;

function kiro_get(string $path): array {
    $ch = curl_init(KIRO_API_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . KIRO_ADMIN_KEY,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode((string)$resp, true)];
}

/** Удалить чувствительные поля из лога перед отдачей пользователю. */
function strip_log(array $l): array {
    unset($l['prompt_preview'], $l['key_full'], $l['error_traceback']);
    return [
        'ts'                => $l['ts'] ?? null,
        '_ts'               => $l['_ts'] ?? null,
        'model'             => $l['model'] ?? null,
        'stream'            => (bool)($l['stream'] ?? false),
        'account'           => $l['account'] ?? null,
        'prompt_tokens'     => (int)($l['prompt_tokens'] ?? 0),
        'completion_tokens' => (int)($l['completion_tokens'] ?? 0),
        'total_tokens'      => (int)($l['total_tokens'] ?? 0),
        'duration_ms'       => (int)($l['duration_ms'] ?? 0),
        'status'            => $l['status'] ?? null,
    ];
}

if ($action === 'summary') {
    // Inspect /admin/keys to find live limits/used by preview
    $r = kiro_get('/admin/keys');
    $found = null;
    foreach ((array)($r['data']['keys'] ?? []) as $k) {
        if (($k['key_preview'] ?? '') === $preview) { $found = $k; break; }
    }
    if (!$found) {
        echo json_encode(['ok' => false, 'error' => 'Ключ не найден в Kiro-пуле']);
        exit;
    }

    // Choose primary axis: credits if set, else tokens
    $cl = (int)($found['credits_limit'] ?? 0);
    $cu = (int)($found['credits_used'] ?? 0);
    $tl = (int)($found['tokens_limit'] ?? 0);
    $tu = (int)($found['tokens_used'] ?? 0);
    if ($cl > 0)        { $axisLimit = $cl; $axisUsed = $cu; $axisType = 'credits'; }
    elseif ($tl > 0)    { $axisLimit = $tl; $axisUsed = $tu; $axisType = 'tokens'; }
    else                { $axisLimit = 0;   $axisUsed = max($cu, $tu); $axisType = 'unlimited'; }

    echo json_encode([
        'ok'                => true,
        'preview'           => $preview,
        'name'              => $found['name'] ?? $row['label'],
        'enabled'           => (bool)($found['enabled'] ?? true),
        'rpm_limit'         => (int)($found['rpm_limit'] ?? 0),
        'rpm_current'       => (int)($found['rpm_current'] ?? 0),
        'axis_type'         => $axisType,
        'tokens_limit'      => $axisLimit,
        'tokens_used'       => $axisUsed,
        'tokens_remaining'  => max(0, $axisLimit - $axisUsed),
        'credits_limit'     => $cl,
        'credits_used'      => $cu,
        'credits_remaining' => $cl > 0 ? max(0, $cl - $cu) : 0,
        'raw_tokens_limit'  => $tl,
        'raw_tokens_used'   => $tu,
        'requests'          => (int)(($found['stats']['requests'] ?? 0)),
        'tokens_prompt'     => (int)(($found['stats']['tokens_prompt'] ?? 0)),
        'tokens_completion' => (int)(($found['stats']['tokens_completion'] ?? 0)),
    ]);
    exit;
}

if ($action === 'stats') {
    $hours = max(1, min(720, (int)($_GET['hours'] ?? 168)));
    $r = kiro_get('/admin/keys/' . urlencode($preview) . '/stats?hours=' . $hours);
    if ($r['code'] !== 200 || !is_array($r['data'])) {
        echo json_encode(['ok' => false, 'error' => 'Kiro недоступен']);
        exit;
    }
    $d = $r['data'];
    echo json_encode([
        'ok'        => true,
        'last_seen' => $d['last_seen'] ?? null,
        'by_model'  => $d['by_model'] ?? [],
        'sparkline' => $d['sparkline'] ?? [],
        'hours'     => $hours,
    ]);
    exit;
}

if ($action === 'logs') {
    $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
    $r = kiro_get('/admin/logs/key/' . urlencode($preview) . '?limit=' . $limit);
    if ($r['code'] !== 200) {
        echo json_encode(['ok' => false, 'error' => 'Kiro недоступен']);
        exit;
    }
    $logs = array_map('strip_log', (array)($r['data']['logs'] ?? []));
    echo json_encode(['ok' => true, 'logs' => $logs, 'total' => $r['data']['total'] ?? count($logs)]);
    exit;
}

if ($action === 'timeseries') {
    // Build per-bucket counts/tokens from /admin/logs?key=&since=
    $hours = max(1, min(720, (int)($_GET['hours'] ?? 24)));
    $since = time() - $hours * 3600;
    $r = kiro_get('/admin/logs?key=' . urlencode($preview) . '&since=' . $since . '&limit=5000');
    if ($r['code'] !== 200) {
        echo json_encode(['ok' => false, 'error' => 'Kiro недоступен']);
        exit;
    }
    $logs = (array)($r['data']['logs'] ?? []);
    // Bucket size: 24h → 30min, 6h → 5min, 1h → 1min, 7d → 1h, 30d → 6h
    if ($hours <= 1)        $bucket = 60;
    elseif ($hours <= 6)    $bucket = 300;
    elseif ($hours <= 24)   $bucket = 1800;
    elseif ($hours <= 168)  $bucket = 3600;
    else                    $bucket = 21600;
    $n = (int)ceil(($hours * 3600) / $bucket);
    $reqs = array_fill(0, $n, 0);
    $toks = array_fill(0, $n, 0);
    foreach ($logs as $l) {
        $ts = (float)($l['_ts'] ?? 0);
        if (!$ts) continue;
        $i = (int)floor(($ts - $since) / $bucket);
        if ($i < 0 || $i >= $n) continue;
        $reqs[$i]++;
        $toks[$i] += (int)($l['total_tokens'] ?? (($l['prompt_tokens'] ?? 0) + ($l['completion_tokens'] ?? 0)));
    }
    $labels = [];
    for ($i = 0; $i < $n; $i++) $labels[] = $since + $i * $bucket;
    echo json_encode([
        'ok'         => true,
        'hours'      => $hours,
        'bucket_sec' => $bucket,
        'labels'     => $labels,    // unix seconds, start of bucket
        'requests'   => $reqs,
        'tokens'     => $toks,
    ]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
