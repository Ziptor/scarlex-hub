<?php
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? 'overview';

// Spend types (money leaving the user's balance / spent on packages):
// purchase, topup_key (top-up of an existing key from balance)
// Income types: topup, refund, merge
$SPEND_TYPES = ['purchase', 'topup_key'];
$INCOME_TYPES = ['topup', 'refund', 'merge'];

function in_clause(array $items): string {
    return "'" . implode("','", array_map('addslashes', $items)) . "'";
}

// ── Overview: stats for dashboard home + analytics page ──
if ($action === 'overview') {
    // Total requests (success) all-time
    $r = $pdo->prepare("SELECT COUNT(*) FROM chat_transactions WHERE user_id = ? AND status='success'");
    $r->execute([$userId]);
    $totalReq = (int)$r->fetchColumn();

    // Last 30d aggregates
    $r = $pdo->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_tokens),0) AS tok
                        FROM chat_transactions
                        WHERE user_id = ? AND status='success'
                          AND created_at >= (NOW() - INTERVAL 30 DAY)");
    $r->execute([$userId]);
    $row = $r->fetch();
    $req30 = (int)($row['cnt'] ?? 0);
    $tok30 = (int)($row['tok'] ?? 0);

    // Spent last 30d (purchase + topup_key)
    $r = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions
                        WHERE user_id = ? AND type IN (" . in_clause($SPEND_TYPES) . ")
                          AND created_at >= (NOW() - INTERVAL 30 DAY)");
    $r->execute([$userId]);
    $spent30 = (float)$r->fetchColumn();

    // By model 30d
    $r = $pdo->prepare("SELECT model, COUNT(*) AS cnt
                        FROM chat_transactions
                        WHERE user_id = ? AND status='success'
                          AND created_at >= (NOW() - INTERVAL 30 DAY)
                        GROUP BY model ORDER BY cnt DESC");
    $r->execute([$userId]);
    $byModel = $r->fetchAll(PDO::FETCH_ASSOC);

    // Daily 30d
    $r = $pdo->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS cnt
                        FROM chat_transactions
                        WHERE user_id = ? AND status='success'
                          AND created_at >= (NOW() - INTERVAL 30 DAY)
                        GROUP BY DATE(created_at) ORDER BY d ASC");
    $r->execute([$userId]);
    $byDate = [];
    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $row) $byDate[$row['d']] = (int)$row['cnt'];
    $daily = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $daily[] = ['date' => $d, 'cnt' => (int)($byDate[$d] ?? 0)];
    }

    echo json_encode([
        'ok' => true,
        'total_requests' => $totalReq,
        'requests_30d'   => $req30,
        'tokens_30d'     => $tok30,
        'spent_30d'      => round($spent30, 2),
        'by_model'       => $byModel,
        'daily'          => $daily,
    ]);
    exit;
}

// ── Burn rate ──
// Average daily spend over last N days; predict days until balance = 0.
if ($action === 'burn_rate') {
    $days = max(3, min(60, (int)($_GET['days'] ?? 14)));

    // Sum of spends over last N days. Spend rows are stored with negative
    // `amount` (money leaving balance), so we ABS() to get a positive figure.
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(ABS(amount)), 0) AS total
         FROM transactions
         WHERE user_id = ?
           AND type IN (" . in_clause($SPEND_TYPES) . ")
           AND created_at >= (NOW() - INTERVAL ? DAY)"
    );
    $stmt->execute([$userId, $days]);
    $totalSpent = (float)$stmt->fetchColumn();

    // Distinct active days (days where at least one spend happened)
    $stmtDays = $pdo->prepare(
        "SELECT COUNT(DISTINCT DATE(created_at)) AS d
         FROM transactions
         WHERE user_id = ?
           AND type IN (" . in_clause($SPEND_TYPES) . ")
           AND created_at >= (NOW() - INTERVAL ? DAY)"
    );
    $stmtDays->execute([$userId, $days]);
    $activeDays = (int)$stmtDays->fetchColumn();

    // Use full window for rate (not just active days) — more conservative estimate
    $dailyAvg = $totalSpent / max(1, $days);

    // Current balance
    $bs = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $bs->execute([$userId]);
    $balance = (float)$bs->fetchColumn();

    $daysLeft = null;
    if ($dailyAvg > 0.001) {
        $daysLeft = floor($balance / $dailyAvg);
    }

    // Trend: compare last half vs first half of window. Use ABS() because
    // spend amounts are negative in storage.
    $half = (int)floor($days / 2);
    $stmt2 = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN created_at >= (NOW() - INTERVAL ? DAY) THEN ABS(amount) ELSE 0 END), 0) AS recent,
            COALESCE(SUM(CASE WHEN created_at <  (NOW() - INTERVAL ? DAY) THEN ABS(amount) ELSE 0 END), 0) AS older
         FROM transactions
         WHERE user_id = ?
           AND type IN (" . in_clause($SPEND_TYPES) . ")
           AND created_at >= (NOW() - INTERVAL ? DAY)"
    );
    $stmt2->execute([$half, $half, $userId, $days]);
    $row = $stmt2->fetch();
    $recent = (float)$row['recent'];
    $older  = (float)$row['older'];
    $trend = 'flat';
    if ($older > 0.01) {
        $delta = ($recent - $older) / $older;
        if ($delta > 0.15) $trend = 'up';
        else if ($delta < -0.15) $trend = 'down';
    } else if ($recent > 0.01) {
        $trend = 'up';
    }

    echo json_encode([
        'ok' => true,
        'window_days' => $days,
        'total_spent' => round($totalSpent, 2),
        'daily_avg' => round($dailyAvg, 2),
        'active_days' => $activeDays,
        'balance' => round($balance, 2),
        'days_left' => $daysLeft,
        'trend' => $trend,
        'recent_half' => round($recent, 2),
        'older_half' => round($older, 2),
    ]);
    exit;
}

// ── Spending chart ──
// Group spends by day / week / month for last N periods.
if ($action === 'spending') {
    $period = $_GET['period'] ?? 'day'; // day | week | month
    $count = max(1, min(60, (int)($_GET['count'] ?? 14)));

    if ($period === 'day') {
        $intervalUnit = 'DAY';
        $groupExpr = "DATE(created_at)";
        $labelFmt = '%Y-%m-%d';
        $sinceMod = $count - 1;
    } else if ($period === 'week') {
        $intervalUnit = 'WEEK';
        $groupExpr = "YEARWEEK(created_at, 1)";
        $labelFmt = null;
        $sinceMod = $count - 1;
    } else { // month
        $intervalUnit = 'MONTH';
        $groupExpr = "DATE_FORMAT(created_at, '%Y-%m-01')";
        $labelFmt = '%Y-%m';
        $sinceMod = $count - 1;
    }

    $sql = "SELECT $groupExpr AS bucket,
                   COALESCE(SUM(amount), 0) AS total,
                   COUNT(*) AS tx_count
            FROM transactions
            WHERE user_id = ?
              AND type IN (" . in_clause($SPEND_TYPES) . ")
              AND created_at >= (NOW() - INTERVAL ? $intervalUnit)
            GROUP BY bucket
            ORDER BY bucket ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $sinceMod]);
    $rows = $stmt->fetchAll();

    // Build complete series (fill zero-buckets)
    $series = [];
    if ($period === 'day') {
        for ($i = $count - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $series[$d] = 0;
        }
        foreach ($rows as $r) {
            $key = $r['bucket'];
            if (isset($series[$key])) $series[$key] = (float)$r['total'];
        }
        $labels = array_keys($series);
        $labelsHuman = array_map(function($d){ return date('d.m', strtotime($d)); }, $labels);
    } else if ($period === 'week') {
        // Build last N weeks (yearweek mode 1, ISO Mon-start)
        $weeks = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $ts = strtotime("-$i weeks");
            $yw = (int)date('o', $ts) * 100 + (int)date('W', $ts);
            $weeks[$yw] = ['ts' => $ts, 'val' => 0];
        }
        foreach ($rows as $r) {
            $yw = (int)$r['bucket'];
            if (isset($weeks[$yw])) $weeks[$yw]['val'] = (float)$r['total'];
        }
        $labels = [];
        $labelsHuman = [];
        $vals = [];
        foreach ($weeks as $yw => $w) {
            $labels[] = (string)$yw;
            $labelsHuman[] = 'нед. ' . date('W', $w['ts']);
            $vals[] = round($w['val'], 2);
        }
        echo json_encode([
            'ok' => true,
            'period' => $period,
            'labels' => $labels,
            'labels_human' => $labelsHuman,
            'values' => $vals,
        ]);
        exit;
    } else { // month
        for ($i = $count - 1; $i >= 0; $i--) {
            $key = date('Y-m-01', strtotime("first day of -$i months"));
            $series[$key] = 0;
        }
        foreach ($rows as $r) {
            $key = $r['bucket'];
            if (isset($series[$key])) $series[$key] = (float)$r['total'];
        }
        $labels = array_keys($series);
        $months = ['','янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'];
        $labelsHuman = array_map(function($d) use ($months) {
            $m = (int)date('n', strtotime($d));
            return $months[$m] . ' ' . date('y', strtotime($d));
        }, $labels);
    }

    echo json_encode([
        'ok' => true,
        'period' => $period,
        'labels' => array_values($labels),
        'labels_human' => array_values($labelsHuman),
        'values' => array_map(function($v){ return round((float)$v, 2); }, array_values($series)),
    ]);
    exit;
}

// ── Activity heatmap ──
// Count chat_transactions grouped by day-of-week and hour-of-day over last N days.
if ($action === 'activity') {
    $days = max(7, min(180, (int)($_GET['days'] ?? 30)));

    // MySQL DAYOFWEEK: 1=Sun, 2=Mon, ..., 7=Sat. We re-map to 0=Mon ... 6=Sun.
    $stmt = $pdo->prepare(
        "SELECT DAYOFWEEK(created_at) AS dow,
                HOUR(created_at) AS h,
                COUNT(*) AS cnt
         FROM chat_transactions
         WHERE user_id = ?
           AND created_at >= (NOW() - INTERVAL ? DAY)
         GROUP BY dow, h"
    );
    $stmt->execute([$userId, $days]);

    // matrix[7][24] init zeros. 0=Mon, 6=Sun
    $matrix = [];
    for ($d = 0; $d < 7; $d++) {
        $matrix[$d] = array_fill(0, 24, 0);
    }
    $maxCount = 0;
    $totalReq = 0;
    foreach ($stmt->fetchAll() as $r) {
        $mysqlDow = (int)$r['dow']; // 1..7
        // 1 (Sun) -> 6, 2 (Mon) -> 0, 3 -> 1, ..., 7 (Sat) -> 5
        $idx = ($mysqlDow + 5) % 7;
        $h = (int)$r['h'];
        $c = (int)$r['cnt'];
        $matrix[$idx][$h] = $c;
        if ($c > $maxCount) $maxCount = $c;
        $totalReq += $c;
    }

    // Find peak hour
    $peakDay = 0; $peakHour = 0;
    for ($d = 0; $d < 7; $d++) {
        for ($h = 0; $h < 24; $h++) {
            if ($matrix[$d][$h] === $maxCount && $maxCount > 0) {
                $peakDay = $d; $peakHour = $h;
                break 2;
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'days' => $days,
        'matrix' => $matrix,
        'max' => $maxCount,
        'total_requests' => $totalReq,
        'peak_day' => $peakDay,
        'peak_hour' => $peakHour,
    ]);
    exit;
}

// ── Streak ──
// Active day = day with any chat_transaction OR financial transaction
// (chat запрос ИЛИ покупка ИЛИ пополнение ИЛИ расход токенов через keys top-up).
if ($action === 'streak') {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT DATE(d) AS active_date FROM (
            SELECT created_at AS d FROM chat_transactions WHERE user_id = ? AND created_at >= (NOW() - INTERVAL 365 DAY)
            UNION
            SELECT created_at AS d FROM transactions      WHERE user_id = ? AND created_at >= (NOW() - INTERVAL 365 DAY)
         ) AS x
         ORDER BY active_date DESC"
    );
    $stmt->execute([$userId, $userId]);
    $rows = $stmt->fetchAll();

    $activeDates = [];
    foreach ($rows as $r) $activeDates[$r['active_date']] = true;

    $today = new DateTime('today');
    $todayKey = $today->format('Y-m-d');
    $yesterdayKey = (clone $today)->modify('-1 day')->format('Y-m-d');

    // Current streak: count back from today (or yesterday if today not yet active)
    $current = 0;
    if (isset($activeDates[$todayKey])) {
        $cursor = clone $today;
    } else if (isset($activeDates[$yesterdayKey])) {
        $cursor = (clone $today)->modify('-1 day');
    } else {
        $cursor = null;
    }
    if ($cursor) {
        while (isset($activeDates[$cursor->format('Y-m-d')])) {
            $current++;
            $cursor->modify('-1 day');
        }
    }

    // Longest streak (scan ascending)
    $longest = 0;
    $run = 0;
    $prev = null;
    $sortedKeys = array_keys($activeDates);
    sort($sortedKeys);
    foreach ($sortedKeys as $k) {
        if ($prev === null) {
            $run = 1;
        } else {
            $diff = (int)(new DateTime($k))->diff(new DateTime($prev))->days;
            $run = ($diff === 1) ? $run + 1 : 1;
        }
        if ($run > $longest) $longest = $run;
        $prev = $k;
    }

    // Last 30 days timeline (oldest -> newest, for dotted bar)
    $timeline = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = (clone $today)->modify("-$i days");
        $key = $d->format('Y-m-d');
        $timeline[] = [
            'date' => $key,
            'active' => isset($activeDates[$key]) ? 1 : 0,
        ];
    }

    echo json_encode([
        'ok' => true,
        'current' => $current,
        'longest' => $longest,
        'total_active_days' => count($activeDates),
        'today_active' => isset($activeDates[$todayKey]),
        'timeline' => $timeline,
    ]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
