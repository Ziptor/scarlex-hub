<?php
/**
 * Public status endpoint — proxies Kiro proxy /status and strips admin-only details.
 * Cached for 15 seconds to avoid hammering the VPS.
 * GET /api/status_public.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=15');
require_once __DIR__ . '/secrets.php';

if (!defined('KIRO_API_URL'))   define('KIRO_API_URL', 'https://api.scarlex.ru');
if (!defined('KIRO_ADMIN_KEY')) define('KIRO_ADMIN_KEY', '');

$CACHE_FILE = sys_get_temp_dir() . '/scarlex_status_cache.json';
$CACHE_TTL  = 15; // seconds

// ── Cache hit ──
if (is_file($CACHE_FILE) && (time() - filemtime($CACHE_FILE) < $CACHE_TTL)) {
    $cached = @file_get_contents($CACHE_FILE);
    if ($cached !== false) { echo $cached; exit; }
}

// ── Fetch fresh /status from Kiro proxy ──
$t0 = microtime(true);
$ch = curl_init(KIRO_API_URL . '/status');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . KIRO_ADMIN_KEY, 'Accept: application/json'],
    CURLOPT_TIMEOUT        => 6,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$body = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$lat_ms = (int)((microtime(true) - $t0) * 1000);
curl_close($ch);

$out = [
    'ok'         => $http === 200,
    'http'       => $http,
    'latency_ms' => $lat_ms,
    'updated_at' => date('c'),
];

if ($http === 200) {
    $data = json_decode($body, true);
    if (is_array($data)) {
        $pool = $data['pool'] ?? [];
        $out['status']  = $data['status'] ?? 'unknown';
        $out['uptime']  = $data['uptime'] ?? null;
        $out['pool']    = [
            'total'     => (int)($pool['total'] ?? 0),
            'alive'     => (int)($pool['alive'] ?? 0),
            'available' => (int)($pool['available'] ?? 0),
        ];
        // Aggregate health ratio
        $tot = $out['pool']['total'];
        $ali = $out['pool']['alive'];
        $out['health_pct'] = $tot > 0 ? round(($ali / $tot) * 100) : 0;
        // Coarse-grained status
        if ($out['health_pct'] >= 90)      $out['level'] = 'operational';
        elseif ($out['health_pct'] >= 50)  $out['level'] = 'degraded';
        else                               $out['level'] = 'major';

        // Optional stats summary (no per-key sensitive info)
        if (isset($data['stats'])) {
            $out['stats'] = [
                'total_requests' => (int)($data['stats']['total_requests'] ?? 0),
                'total_tokens'   => (int)($data['stats']['total_tokens'] ?? 0),
            ];
        }
    }
} else {
    $out['level'] = 'major';
    $out['status'] = 'unreachable';
}

// ── Site availability check (shared hosting itself) ──
$out['site'] = ['level' => 'operational'];

$json = json_encode($out, JSON_UNESCAPED_UNICODE);
@file_put_contents($CACHE_FILE, $json);
echo $json;
