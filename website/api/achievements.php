<?php
// Achievements / badges engine.
// - GET ?action=list  → all achievements + user's unlock state + progress
// - GET ?action=check → recompute & unlock any newly-eligible achievements (returns newly unlocked)
// Achievements are checked on-demand (no cron). The check is also fired automatically by `list`.
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_tg_notify.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ── Catalog ──
// Each: id, name, description, icon (emoji), tier (bronze/silver/gold/platinum), category, threshold
$ACHIEVEMENTS = [
    // ── Onboarding ──
    ['id' => 'first_key',         'name' => 'Первый ключ',         'description' => 'Создайте свой первый API-ключ',                         'icon' => '🔑', 'tier' => 'bronze',   'category' => 'onboarding'],
    ['id' => 'first_request',     'name' => 'Первый запрос',       'description' => 'Отправьте первый chat completion через API',           'icon' => '🚀', 'tier' => 'bronze',   'category' => 'onboarding'],
    ['id' => 'first_topup',       'name' => 'Первое пополнение',   'description' => 'Пополните баланс впервые',                              'icon' => '💸', 'tier' => 'bronze',   'category' => 'onboarding'],
    ['id' => 'free_test',         'name' => 'Тест-драйв',          'description' => 'Получите бесплатный тестовый ключ',                    'icon' => '🎁', 'tier' => 'bronze',   'category' => 'onboarding'],

    // ── Requests ──
    ['id' => 'req_100',           'name' => 'Сотка',               'description' => 'Сделайте 100 запросов через API',                      'icon' => '💯', 'tier' => 'bronze',   'category' => 'requests', 'threshold' => 100],
    ['id' => 'req_1k',            'name' => 'Тысячник',            'description' => '1 000 запросов через API',                              'icon' => '⚡', 'tier' => 'silver',   'category' => 'requests', 'threshold' => 1000],
    ['id' => 'req_10k',           'name' => 'Промпт-машина',       'description' => '10 000 запросов — вы в деле',                          'icon' => '🔥', 'tier' => 'gold',     'category' => 'requests', 'threshold' => 10000],
    ['id' => 'req_100k',          'name' => 'Легенда API',         'description' => '100 000 запросов — это уровень',                       'icon' => '👑', 'tier' => 'platinum', 'category' => 'requests', 'threshold' => 100000],

    // ── Tokens ──
    ['id' => 'tok_1m',            'name' => 'Миллион токенов',     'description' => 'Использовали 1M токенов суммарно',                     'icon' => '🧠', 'tier' => 'silver',   'category' => 'tokens',   'threshold' => 1000000],
    ['id' => 'tok_10m',           'name' => 'Десять миллионов',    'description' => 'Использовали 10M токенов',                              'icon' => '🌌', 'tier' => 'gold',     'category' => 'tokens',   'threshold' => 10000000],
    ['id' => 'tok_100m',          'name' => 'Гига-промпт',         'description' => 'Использовали 100M токенов',                             'icon' => '💫', 'tier' => 'platinum', 'category' => 'tokens',   'threshold' => 100000000],

    // ── Spending ──
    ['id' => 'spent_1k',          'name' => 'Первая тысяча',       'description' => 'Потратили 1 000 ₽ на пакеты и пополнения ключей',     'icon' => '💰', 'tier' => 'bronze',   'category' => 'spending', 'threshold' => 1000],
    ['id' => 'spent_10k',         'name' => 'Десять тысяч',        'description' => 'Потратили 10 000 ₽',                                    'icon' => '💎', 'tier' => 'silver',   'category' => 'spending', 'threshold' => 10000],
    ['id' => 'spent_50k',         'name' => 'Серьёзный игрок',     'description' => 'Потратили 50 000 ₽',                                    'icon' => '🏆', 'tier' => 'gold',     'category' => 'spending', 'threshold' => 50000],
    ['id' => 'spent_100k',        'name' => 'VIP-клиент',          'description' => 'Потратили 100 000 ₽',                                   'icon' => '👑', 'tier' => 'platinum', 'category' => 'spending', 'threshold' => 100000],

    // ── Keys ──
    ['id' => 'keys_3',            'name' => 'Коллекционер',        'description' => 'Имеете 3 активных ключа одновременно',                 'icon' => '🗝',  'tier' => 'bronze',   'category' => 'keys',     'threshold' => 3],
    ['id' => 'keys_10',           'name' => 'Связка ключей',       'description' => '10 активных ключей одновременно',                      'icon' => '🔐', 'tier' => 'silver',   'category' => 'keys',     'threshold' => 10],

    // ── Streak ──
    ['id' => 'streak_3',          'name' => 'Три дня подряд',      'description' => 'Используйте API 3 дня подряд',                         'icon' => '🔥', 'tier' => 'bronze',   'category' => 'streak',   'threshold' => 3],
    ['id' => 'streak_7',          'name' => 'Неделя огня',         'description' => '7 дней активности подряд',                              'icon' => '🔥', 'tier' => 'silver',   'category' => 'streak',   'threshold' => 7],
    ['id' => 'streak_30',         'name' => 'Месячный марафон',    'description' => '30 дней активности подряд',                             'icon' => '🏔', 'tier' => 'gold',     'category' => 'streak',   'threshold' => 30],
    ['id' => 'streak_100',        'name' => 'Сто дней',            'description' => '100 дней подряд — вы непреклонны',                     'icon' => '⚜', 'tier' => 'platinum', 'category' => 'streak',   'threshold' => 100],

    // ── Models / variety ──
    ['id' => 'multi_model',       'name' => 'Полиглот',            'description' => 'Использовали 3+ разных модели',                        'icon' => '🎨', 'tier' => 'silver',   'category' => 'models',   'threshold' => 3],
    ['id' => 'all_families',      'name' => 'Все семейства',       'description' => 'Попробовали Claude, GPT и Gemini',                     'icon' => '🌟', 'tier' => 'gold',     'category' => 'models',   'threshold' => 3],

    // ── Referrals ──
    ['id' => 'ref_1',             'name' => 'Привёл друга',        'description' => 'Один пользователь зарегистрировался по вашей ссылке', 'icon' => '🤝', 'tier' => 'bronze',   'category' => 'referrals','threshold' => 1],
    ['id' => 'ref_5',             'name' => 'Амбассадор',          'description' => 'Привели 5 друзей',                                      'icon' => '🌐', 'tier' => 'silver',   'category' => 'referrals','threshold' => 5],
    ['id' => 'ref_25',            'name' => 'Армия',                'description' => 'Привели 25 друзей',                                     'icon' => '🛡',  'tier' => 'gold',     'category' => 'referrals','threshold' => 25],
];

// ── Stats collector ──
function collect_user_stats(PDO $pdo, int $userId): array {
    $s = [];

    // Keys
    $r = $pdo->prepare('SELECT COUNT(*) AS total, SUM(CASE WHEN is_active=1 AND is_frozen=0 THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN is_test=1 THEN 1 ELSE 0 END) AS test_keys FROM api_keys WHERE user_id = ?');
    $r->execute([$userId]);
    $row = $r->fetch();
    $s['total_keys']  = (int)($row['total'] ?? 0);
    $s['active_keys'] = (int)($row['active'] ?? 0);
    $s['test_keys']   = (int)($row['test_keys'] ?? 0);

    // Requests + tokens + models
    $r = $pdo->prepare("SELECT COUNT(*) AS req_total, COALESCE(SUM(total_tokens),0) AS tok_total FROM chat_transactions WHERE user_id = ? AND status='success'");
    $r->execute([$userId]);
    $row = $r->fetch();
    $s['total_requests'] = (int)($row['req_total'] ?? 0);
    $s['total_tokens']   = (int)($row['tok_total'] ?? 0);

    // Distinct models + families
    $r = $pdo->prepare("SELECT DISTINCT model FROM chat_transactions WHERE user_id = ? AND status='success'");
    $r->execute([$userId]);
    $models = array_column($r->fetchAll(), 'model');
    $s['distinct_models'] = count($models);
    $families = [];
    foreach ($models as $m) {
        $ml = strtolower($m);
        if (str_contains($ml, 'claude'))  $families['claude']  = true;
        if (str_contains($ml, 'gpt'))     $families['gpt']     = true;
        if (str_contains($ml, 'gemini'))  $families['gemini']  = true;
    }
    $s['distinct_families'] = count($families);

    // Spending (purchase + topup_key)
    $r = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE user_id = ? AND type IN ('purchase','topup_key')");
    $r->execute([$userId]);
    $s['total_spent'] = (float)$r->fetchColumn();

    // Topups
    $r = $pdo->prepare("SELECT COUNT(*) AS cnt FROM transactions WHERE user_id = ? AND type='topup'");
    $r->execute([$userId]);
    $s['total_topups'] = (int)$r->fetchColumn();

    // Streak (current — re-uses analytics logic)
    $r = $pdo->prepare(
        "SELECT DISTINCT DATE(d) AS active_date FROM (
            SELECT created_at AS d FROM chat_transactions WHERE user_id = ? AND created_at >= (NOW() - INTERVAL 200 DAY)
            UNION
            SELECT created_at AS d FROM transactions      WHERE user_id = ? AND created_at >= (NOW() - INTERVAL 200 DAY)
         ) AS x
         ORDER BY active_date DESC"
    );
    $r->execute([$userId, $userId]);
    $activeDates = [];
    foreach ($r->fetchAll() as $row2) $activeDates[$row2['active_date']] = true;
    $today = new DateTime('today');
    $todayKey = $today->format('Y-m-d');
    $yesterdayKey = (clone $today)->modify('-1 day')->format('Y-m-d');
    $current = 0;
    if (isset($activeDates[$todayKey])) $cursor = clone $today;
    elseif (isset($activeDates[$yesterdayKey])) $cursor = (clone $today)->modify('-1 day');
    else $cursor = null;
    if ($cursor) {
        while (isset($activeDates[$cursor->format('Y-m-d')])) {
            $current++;
            $cursor->modify('-1 day');
        }
    }
    $s['current_streak'] = $current;

    // Referrals (count of users that signed up by my code)
    $r = $pdo->prepare("SELECT COUNT(*) AS cnt FROM users WHERE referrer_id = ?");
    $r->execute([$userId]);
    $s['referrals_count'] = (int)$r->fetchColumn();

    return $s;
}

// ── Eligibility check per achievement ──
function check_achievement(array $a, array $stats): array {
    $id = $a['id'];
    $threshold = $a['threshold'] ?? 1;
    $progress = 0;
    $unlocked = false;

    switch ($id) {
        case 'first_key':       $progress = $stats['total_keys'];       $unlocked = $stats['total_keys'] >= 1; break;
        case 'first_request':   $progress = min(1, $stats['total_requests']); $unlocked = $stats['total_requests'] >= 1; break;
        case 'first_topup':     $progress = min(1, $stats['total_topups']);   $unlocked = $stats['total_topups'] >= 1; break;
        case 'free_test':       $progress = $stats['test_keys'];        $unlocked = $stats['test_keys'] >= 1; break;
        case 'req_100': case 'req_1k': case 'req_10k': case 'req_100k':
            $progress = $stats['total_requests']; $unlocked = $stats['total_requests'] >= $threshold; break;
        case 'tok_1m': case 'tok_10m': case 'tok_100m':
            $progress = $stats['total_tokens']; $unlocked = $stats['total_tokens'] >= $threshold; break;
        case 'spent_1k': case 'spent_10k': case 'spent_50k': case 'spent_100k':
            $progress = (int)$stats['total_spent']; $unlocked = $stats['total_spent'] >= $threshold; break;
        case 'keys_3': case 'keys_10':
            $progress = $stats['active_keys']; $unlocked = $stats['active_keys'] >= $threshold; break;
        case 'streak_3': case 'streak_7': case 'streak_30': case 'streak_100':
            $progress = $stats['current_streak']; $unlocked = $stats['current_streak'] >= $threshold; break;
        case 'multi_model':     $progress = $stats['distinct_models'];   $unlocked = $stats['distinct_models']   >= $threshold; break;
        case 'all_families':    $progress = $stats['distinct_families']; $unlocked = $stats['distinct_families'] >= 3; break;
        case 'ref_1': case 'ref_5': case 'ref_25':
            $progress = $stats['referrals_count']; $unlocked = $stats['referrals_count'] >= $threshold; break;
    }
    return ['unlocked' => $unlocked, 'progress' => $progress, 'threshold' => $threshold];
}

// ── Get user's already-unlocked achievement IDs ──
function get_unlocked(PDO $pdo, int $userId): array {
    $r = $pdo->prepare('SELECT achievement_id, unlocked_at FROM user_achievements WHERE user_id = ?');
    $r->execute([$userId]);
    $map = [];
    foreach ($r->fetchAll() as $row) $map[$row['achievement_id']] = $row['unlocked_at'];
    return $map;
}

// ── Sync: insert any newly-eligible achievements ──
function sync_unlocks(PDO $pdo, int $userId, array $catalog, array $stats): array {
    $existing = get_unlocked($pdo, $userId);
    $newlyUnlocked = [];
    $ins = $pdo->prepare('INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)');
    foreach ($catalog as $a) {
        if (isset($existing[$a['id']])) continue;
        $check = check_achievement($a, $stats);
        if ($check['unlocked']) {
            $ins->execute([$userId, $a['id']]);
            if ($ins->rowCount() > 0) $newlyUnlocked[] = $a;
        }
    }
    return $newlyUnlocked;
}

// ── Endpoints ──
$action = $_GET['action'] ?? 'list';

$stats = collect_user_stats($pdo, $userId);
$newlyUnlocked = sync_unlocks($pdo, $userId, $ACHIEVEMENTS, $stats);

// TG alerts for newly unlocked achievements (best effort)
foreach ($newlyUnlocked as $a) {
    try {
        tg_alert($pdo, $userId, 'achievement_unlocked',
            "🏅 <b>Новое достижение!</b>\n\n" . $a['icon'] . " <b>" . $a['name'] . "</b>\n" . $a['description'] . "\n\n🎖 Уровень: " . $a['tier'],
            $a['id'], 0);
    } catch (Exception $e) { /* best-effort */ }
}

$existing = get_unlocked($pdo, $userId);

if ($action === 'check') {
    echo json_encode([
        'ok' => true,
        'newly_unlocked' => array_map(function($a){
            return ['id' => $a['id'], 'name' => $a['name'], 'icon' => $a['icon'], 'tier' => $a['tier']];
        }, $newlyUnlocked),
    ]);
    exit;
}

// list (default)
$items = [];
$totalUnlocked = 0;
foreach ($ACHIEVEMENTS as $a) {
    $check = check_achievement($a, $stats);
    $isUnlocked = isset($existing[$a['id']]);
    if ($isUnlocked) $totalUnlocked++;
    $items[] = [
        'id'           => $a['id'],
        'name'         => $a['name'],
        'description'  => $a['description'],
        'icon'         => $a['icon'],
        'tier'         => $a['tier'],
        'category'     => $a['category'],
        'unlocked'     => $isUnlocked,
        'unlocked_at'  => $existing[$a['id']] ?? null,
        'progress'     => $check['progress'],
        'threshold'    => $check['threshold'],
        'percent'      => $check['threshold'] > 0 ? min(100, (int)round($check['progress'] / $check['threshold'] * 100)) : 0,
    ];
}

echo json_encode([
    'ok' => true,
    'achievements' => $items,
    'total' => count($items),
    'unlocked' => $totalUnlocked,
    'newly_unlocked' => array_map(function($a){
        return ['id' => $a['id'], 'name' => $a['name'], 'icon' => $a['icon'], 'tier' => $a['tier']];
    }, $newlyUnlocked),
    'stats' => $stats,
]);
