<?php
// Атомарная покупка тарифа из бота через общую функцию purchase_product().
// Body: { telegram_id, product_id, promo_code? }
// Trial-тарифы блокируются (block_trial=true) — выдаются только на сайте.
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../_purchase.php';

bot_auth_or_die();
$in = bot_input();

$tgId      = (int)($in['telegram_id'] ?? 0);
$productId = (int)($in['product_id']   ?? 0);
$promoCode = (string)($in['promo_code'] ?? '');
if (!$tgId || !$productId) bot_err('telegram_id and product_id required');

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);
$userId = (int)$u['id'];

$res = purchase_product($pdo, $userId, $productId, $promoCode, [
    'block_trial' => true,
    'tg_notify'   => true,
]);

if (!$res['ok']) {
    bot_err($res['error'] ?? 'Ошибка покупки', $res['http'] ?? 400);
}

bot_ok([
    'key'            => $res['key'],
    'key_id'         => $res['key_id'],
    'product'        => $res['product'],
    'tokens'         => $res['tokens'],
    'price_rub'      => $res['price_rub'],
    'original_price' => $res['original_price'],
    'promo_discount' => $res['promo_discount'],
    'promo_code'     => $res['promo_code'],
    'new_balance'    => $res['new_balance'],
    'source'         => 'scarlex_proxy',
]);
