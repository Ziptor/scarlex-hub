<?php
// Возвращает каталог тарифов из таблицы `products` (единый источник правды с сайтом).
// Никакого reseller, никаких product_overrides — только Scarlex-тарифы.
// Body: { telegram_id? }
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../cbr_rate.php';

bot_auth_or_die();
$in = bot_input();
$tgId = (int)($in['telegram_id'] ?? $_GET['telegram_id'] ?? 0);

$rate = get_usd_rub_rate();
$rateValue = (float)($rate['rate'] ?? 0);

// Узнаём состояние пользователя (для trial_claimed)
$trialClaimed = false;
if ($tgId) {
    $st = $pdo->prepare('SELECT free_test_key_claimed FROM users WHERE telegram_id = ?');
    $st->execute([$tgId]);
    $trialClaimed = (int)($st->fetchColumn() ?: 0) === 1;
}

$rows = $pdo->query(
    "SELECT * FROM products
     WHERE is_visible = 1
     ORDER BY sort_order ASC, id ASC"
)->fetchAll();

$products = [];
foreach ($rows as $r) {
    $tokens = (int)$r['tokens'];
    $price  = (float)$r['price_rub'];
    $products[] = [
        'id'                 => (int)$r['id'],
        'slug'               => $r['slug'],
        'name'               => $r['name'],
        'description'        => $r['description'] ?? '',
        'tokens'             => $tokens,
        'price_rub'          => round($price, 2),
        'price_usd_estimate' => $rateValue > 0 ? round($price / $rateValue, 2) : 0,
        'price_per_1m'       => $tokens > 0 ? round($price / ($tokens / 1000000.0), 2) : 0.0,
        'badge'              => $r['badge'] ?? null,
        'rpm'                => (int)$r['rpm'],
        'is_trial'           => (int)$r['is_trial'] === 1,
        'allow_custom'       => (int)$r['allow_custom'] === 1,
        'is_custom_price'    => false,
        'trial_claimed'      => $trialClaimed,
    ];
}

bot_ok(['products' => $products, 'rate' => $rate]);
