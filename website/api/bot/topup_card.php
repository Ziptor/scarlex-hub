<?php
// Create YooMoney quickpay URL for bot top-up.
// Body: { telegram_id, amount } — RUB only (YooMoney quickpay limit).
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../_yoomoney_config.php';

bot_auth_or_die();
$in = bot_input();

$tgId = (int)($in['telegram_id'] ?? 0);
$amount = (float)($in['amount'] ?? 0);

if (!$tgId) bot_err('telegram_id required');
if ($amount < 100) bot_err('Минимальная сумма пополнения: 100 ₽');
if ($amount > 60000) bot_err('Максимальная сумма: 60 000 ₽');
if (!YOOMONEY_WALLET) bot_err('Платёжный шлюз картой временно недоступен. Используйте крипту.', 500);

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('Аккаунт не найден. Нажмите /start', 404);
$userId = (int)$u['id'];

$amountRub = round($amount, 2);
$label = 'sx_' . $userId . '_' . bin2hex(random_bytes(8));

$pdo->prepare("INSERT INTO pending_topups (user_id, provider, invoice_id, amount_rub, amount_provider, currency, status, payload)
              VALUES (?, 'yoomoney', ?, ?, ?, 'RUB', 'pending', ?)")
    ->execute([$userId, $label, $amountRub, $amountRub, json_encode(['label' => $label, 'tg_id' => $tgId, 'source' => 'bot'])]);

$params = [
    'receiver' => YOOMONEY_WALLET,
    'quickpay-form' => 'shop',
    'targets' => 'Пополнение баланса Scarlex (' . $amountRub . ' ₽)',
    'paymentType' => 'AC',
    'sum' => number_format($amountRub, 2, '.', ''),
    'label' => $label,
    'successURL' => 'https://t.me/scarlex_bot',
];
$payUrl = YOOMONEY_QUICKPAY_URL . '?' . http_build_query($params);

bot_ok([
    'invoice_id' => $label,
    'pay_url' => $payUrl,
    'amount_rub' => $amountRub,
    'currency' => 'RUB',
]);
