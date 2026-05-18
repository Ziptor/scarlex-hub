<?php
// Bot endpoint: /stats command — returns detailed user statistics with burn rate.
// Body: { telegram_id }
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';

bot_auth_or_die();
$in = bot_input();
$tgId = (int)($in['telegram_id'] ?? 0);
if (!$tgId) bot_err('telegram_id required');

$stmt = $pdo->prepare('SELECT id, username, balance, created_at FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);
$uid = (int)$u['id'];

// Keys
$r = $pdo->prepare('SELECT COUNT(*) AS total,
                           SUM(CASE WHEN is_active=1 AND is_frozen=0 THEN 1 ELSE 0 END) AS active,
                           COALESCE(SUM(tokens_limit),0) AS tok_total,
                           COALESCE(SUM(used_tokens),0) AS tok_used
                    FROM api_keys WHERE user_id = ?');
$r->execute([$uid]);
$keys = $r->fetch();

// Today requests
$r = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_tokens),0) AS tok
                    FROM chat_transactions
                    WHERE user_id = ? AND status='success' AND DATE(created_at) = CURDATE()");
$r->execute([$uid]);
$today = $r->fetch();

// All-time requests
$r = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_tokens),0) AS tok
                    FROM chat_transactions
                    WHERE user_id = ? AND status='success'");
$r->execute([$uid]);
$allTime = $r->fetch();

// Spend all-time + 30d
$r = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)),0)
                    FROM transactions WHERE user_id = ? AND type IN ('purchase','topup_key')");
$r->execute([$uid]);
$spentTotal = (float)$r->fetchColumn();

$r = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)),0)
                    FROM transactions
                    WHERE user_id = ? AND type IN ('purchase','topup_key')
                      AND created_at >= (NOW() - INTERVAL 30 DAY)");
$r->execute([$uid]);
$spent30 = (float)$r->fetchColumn();

// Burn rate (window 14d)
$window = 14;
$r = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)),0)
                    FROM transactions
                    WHERE user_id = ? AND type IN ('purchase','topup_key')
                      AND created_at >= (NOW() - INTERVAL ? DAY)");
$r->execute([$uid, $window]);
$spent14 = (float)$r->fetchColumn();
$dailyAvg = $spent14 / max(1, $window);
$balance = (float)$u['balance'];
$daysLeft = $dailyAvg > 0.001 ? (int)floor($balance / $dailyAvg) : null;

// Trend (recent half vs older half)
$half = (int)floor($window / 2);
$r = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN created_at >= (NOW() - INTERVAL ? DAY) THEN ABS(amount) ELSE 0 END), 0) AS recent,
        COALESCE(SUM(CASE WHEN created_at <  (NOW() - INTERVAL ? DAY) THEN ABS(amount) ELSE 0 END), 0) AS older
     FROM transactions
     WHERE user_id = ? AND type IN ('purchase','topup_key')
       AND created_at >= (NOW() - INTERVAL ? DAY)"
);
$r->execute([$half, $half, $uid, $window]);
$row = $r->fetch();
$recent = (float)$row['recent']; $older = (float)$row['older'];
$trend = 'flat';
if ($older > 0.01) {
    $delta = ($recent - $older) / $older;
    if ($delta > 0.15) $trend = 'up';
    else if ($delta < -0.15) $trend = 'down';
} else if ($recent > 0.01) {
    $trend = 'up';
}

// Top models 30d
$r = $pdo->prepare("SELECT model, COUNT(*) AS cnt
                    FROM chat_transactions
                    WHERE user_id = ? AND status='success'
                      AND created_at >= (NOW() - INTERVAL 30 DAY)
                    GROUP BY model ORDER BY cnt DESC LIMIT 5");
$r->execute([$uid]);
$topModels = $r->fetchAll();

$days = max(1, (int)((time() - strtotime($u['created_at'])) / 86400));

bot_ok([
    'username' => $u['username'],
    'balance_rub' => round($balance, 2),
    'account_age_days' => $days,
    'keys_total' => (int)$keys['total'],
    'keys_active' => (int)$keys['active'],
    'tokens_total' => (int)$keys['tok_total'],
    'tokens_used' => (int)$keys['tok_used'],
    'tokens_left' => max(0, (int)$keys['tok_total'] - (int)$keys['tok_used']),
    'requests_today' => (int)$today['cnt'],
    'tokens_today' => (int)$today['tok'],
    'requests_all_time' => (int)$allTime['cnt'],
    'tokens_all_time' => (int)$allTime['tok'],
    'total_spent_rub' => round($spentTotal, 2),
    'spent_30d' => round($spent30, 2),
    'daily_avg' => round($dailyAvg, 2),
    'days_left' => $daysLeft,
    'trend' => $trend,
    'top_models_30d' => array_map(function ($m) {
        return ['model' => $m['model'], 'count' => (int)$m['cnt']];
    }, $topModels),
]);
