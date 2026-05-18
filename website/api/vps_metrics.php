<?php
// api/vps_metrics.php — приёмник метрик с VPS + раздача истории в kiro.php.
// POST  — collector с VPS, авторизация по CRON_TOKEN (Bearer). Пишет точку в vps_metrics_raw
//         + дозапускает агрегации (5min, 1h) + чистку retention.
// GET   — для UI kiro.php: ?range=1h|6h|24h|7d|30d|1y → labels + ряды.
//         Авторизация по KIRO_ADMIN_KEY (Bearer), т.к. данные чувствительные.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/secrets.php';

if (!defined('CRON_TOKEN'))      define('CRON_TOKEN', '');
if (!defined('KIRO_ADMIN_KEY'))  define('KIRO_ADMIN_KEY', '');

function bearer(): string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($h === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) if (strcasecmp($k, 'Authorization') === 0) { $h = $v; break; }
    }
    if ($h === '') {
        // Fallback: query/POST token (Apache may strip Authorization on shared hosting)
        $h = (string)($_GET['token'] ?? $_POST['token'] ?? '');
    }
    return trim(str_replace('Bearer ', '', $h));
}

// ──────────────────────────────────────────────────────────
// POST: collector
// ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (CRON_TOKEN === '' || bearer() !== CRON_TOKEN) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $now = time();

    $stmt = $pdo->prepare("INSERT INTO vps_metrics_raw
        (ts, cpu_pct, load_1m, load_5m, ram_used_mb, ram_total_mb, swap_used_mb,
         disk_used_gb, disk_total_gb, net_rx_bps, net_tx_bps, uptime_sec, kiro_alive, py_procs)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $now,
        (float)($body['cpu_pct'] ?? 0),
        (float)($body['load_1m'] ?? 0),
        (float)($body['load_5m'] ?? 0),
        (int)($body['ram_used_mb'] ?? 0),
        (int)($body['ram_total_mb'] ?? 0),
        (int)($body['swap_used_mb'] ?? 0),
        (float)($body['disk_used_gb'] ?? 0),
        (float)($body['disk_total_gb'] ?? 0),
        (int)($body['net_rx_bps'] ?? 0),
        (int)($body['net_tx_bps'] ?? 0),
        (int)($body['uptime_sec'] ?? 0),
        !empty($body['kiro_alive']) ? 1 : 0,
        (int)($body['py_procs'] ?? 0),
    ]);

    // Эпизодическая агрегация: раз в 5 минут добиваем 5min/1h из последних raw.
    // Делаем upsert по `ts` (округленному вниз).
    if (($now % 300) < 60) {
        // 5-минутная точка для предыдущего интервала (округление вниз)
        $bucket5 = intdiv($now, 300) * 300 - 300;
        $pdo->prepare("REPLACE INTO vps_metrics_5min
            (ts, cpu_pct, load_1m, ram_used_mb, ram_total_mb, swap_used_mb, disk_used_gb, disk_total_gb, net_rx_bps, net_tx_bps, kiro_alive_pct)
            SELECT ?, AVG(cpu_pct), AVG(load_1m), AVG(ram_used_mb), MAX(ram_total_mb), AVG(swap_used_mb),
                   AVG(disk_used_gb), MAX(disk_total_gb), AVG(net_rx_bps), AVG(net_tx_bps),
                   AVG(kiro_alive) * 100
            FROM vps_metrics_raw WHERE ts >= ? AND ts < ?")
            ->execute([$bucket5, $bucket5, $bucket5 + 300]);
    }
    if (($now % 3600) < 60) {
        $bucket1h = intdiv($now, 3600) * 3600 - 3600;
        $pdo->prepare("REPLACE INTO vps_metrics_1h
            (ts, cpu_pct, load_1m, ram_used_mb, ram_total_mb, swap_used_mb, disk_used_gb, disk_total_gb, net_rx_bps, net_tx_bps, kiro_alive_pct)
            SELECT ?, AVG(cpu_pct), AVG(load_1m), AVG(ram_used_mb), MAX(ram_total_mb), AVG(swap_used_mb),
                   AVG(disk_used_gb), MAX(disk_total_gb), AVG(net_rx_bps), AVG(net_tx_bps),
                   AVG(kiro_alive) * 100
            FROM vps_metrics_raw WHERE ts >= ? AND ts < ?")
            ->execute([$bucket1h, $bucket1h, $bucket1h + 3600]);
    }
    // Retention: raw 7д, 5min 30д, 1h 1 год
    if (($now % 600) < 60) {
        $pdo->prepare("DELETE FROM vps_metrics_raw  WHERE ts < ?")->execute([$now - 7 * 86400]);
        $pdo->prepare("DELETE FROM vps_metrics_5min WHERE ts < ?")->execute([$now - 30 * 86400]);
        $pdo->prepare("DELETE FROM vps_metrics_1h   WHERE ts < ?")->execute([$now - 365 * 86400]);
    }

    echo json_encode(['ok' => true, 'ts' => $now]);
    exit;
}

// ──────────────────────────────────────────────────────────
// GET: история для графика (только admin-key)
// ──────────────────────────────────────────────────────────
if (KIRO_ADMIN_KEY === '' || bearer() !== KIRO_ADMIN_KEY) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$range = $_GET['range'] ?? '6h';
$now = time();
$map = [
    '1h'  => ['table' => 'vps_metrics_raw',  'since' => $now - 3600,           'bucket' => 60],
    '6h'  => ['table' => 'vps_metrics_raw',  'since' => $now - 6 * 3600,       'bucket' => 60],
    '24h' => ['table' => 'vps_metrics_raw',  'since' => $now - 24 * 3600,      'bucket' => 60],
    '7d'  => ['table' => 'vps_metrics_5min', 'since' => $now - 7 * 86400,      'bucket' => 300],
    '30d' => ['table' => 'vps_metrics_5min', 'since' => $now - 30 * 86400,     'bucket' => 300],
    '1y'  => ['table' => 'vps_metrics_1h',   'since' => $now - 365 * 86400,    'bucket' => 3600],
];
$cfg = $map[$range] ?? $map['6h'];

$cols = 'ts, cpu_pct, load_1m, ram_used_mb, ram_total_mb, swap_used_mb, disk_used_gb, disk_total_gb, net_rx_bps, net_tx_bps';
$cols .= ($cfg['table'] === 'vps_metrics_raw') ? ', kiro_alive, py_procs' : ', kiro_alive_pct';
$stmt = $pdo->prepare("SELECT $cols FROM {$cfg['table']} WHERE ts >= ? ORDER BY ts ASC");
$stmt->execute([$cfg['since']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'ok'     => true,
    'range'  => $range,
    'bucket' => $cfg['bucket'],
    'count'  => count($rows),
    'points' => $rows,
]);
