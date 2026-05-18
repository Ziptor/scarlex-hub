<?php
// Create CryptoBot invoice for topup.
// Body: { telegram_id, amount, currency } where currency is 'RUB' or 'USD'
// Back-compat: also accepts { telegram_id, amount_rub } (old bot).
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../cbr_rate.php';

bot_auth_or_die();
$in = bot_input();

$tgId = (int)($in['telegram_id'] ?? 0);
$rawCurrency = $in['currency'] ?? null;
$currency = strtoupper(trim((string)($rawCurrency ?? 'RUB')));
$amount = (float)($in['amount'] ?? $in['amount_rub'] ?? 0);

if (!in_array($currency, ['RUB', 'USD'], true)) $currency = 'RUB';

if (!$tgId) bot_err('telegram_id required');
if ($amount <= 0) bot_err('Сумма не может быть 0. Попробуйте ещё раз.');

// Validate amount per currency
$rate = get_usd_rub_rate();
if (!$rate || empty($rate['rate'])) bot_err('Курс ЦБ временно недоступен, попробуйте через минуту');

if ($currency === 'RUB') {
    if ($amount < 100) bot_err('Минимальная сумма пополнения: 100 ₽');
    if ($amount > 500000) bot_err('Максимальная сумма: 500 000 ₽');
    $amountRub = round($amount, 2);
    $amountUsd = round($amount / $rate['rate'], 2);
} else {
    if ($amount < 1) bot_err('Минимальная сумма: 1 $');
    if ($amount > 5000) bot_err('Максимальная сумма: 5000 $');
    $amountUsd = round($amount, 2);
    $amountRub = round($amount * $rate['rate'], 2);
}

// CryptoBot requires fiat amount >= 0.1 USD in practice
if ($amountUsd < 0.1) bot_err('Сумма слишком мала для CryptoBot (минимум $0.1)');

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('Аккаунт не найден. Нажмите /start', 404);
$userId = (int)$u['id'];

if (!defined('CRYPTOBOT_TOKEN')) {
    require_once __DIR__ . '/_cryptobot_config.php';
}

$payload = json_encode([
    'user_id' => $userId,
    'amount_rub' => $amountRub,
    'amount_usd' => $amountUsd,
    'currency' => $currency,
    'tg_id' => $tgId,
]);

$ch = curl_init('https://pay.crypt.bot/api/createInvoice');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Crypto-Pay-API-Token: ' . CRYPTOBOT_TOKEN,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'currency_type' => 'fiat',
        'fiat' => 'USD',
        'amount' => (string)$amountUsd,
        'accepted_assets' => 'USDT,TON,BTC,ETH,LTC,BNB,TRX,USDC',
        'description' => 'Scarlex top-up: ' . $amountRub . ' ₽ ($' . $amountUsd . ')',
        'hidden_message' => 'Платёж зачислен. Возвращайтесь в @scarlex_bot',
        'paid_btn_name' => 'callback',
        'paid_btn_url' => 'https://t.me/scarlex_bot',
        'payload' => $payload,
        'allow_comments' => false,
        'allow_anonymous' => true,
        'expires_in' => 3600,
    ]),
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($resp['ok'])) {
    bot_err('CryptoBot error: ' . ($resp['error']['name'] ?? ($resp['error']['code'] ?? json_encode($resp))), 500);
}

$inv = $resp['result'];

$pdo->prepare("INSERT INTO pending_topups (user_id, provider, invoice_id, amount_rub, amount_provider, currency, status, payload)
              VALUES (?, 'cryptobot', ?, ?, ?, ?, 'pending', ?)")
    ->execute([$userId, (string)$inv['invoice_id'], $amountRub, $amountUsd, 'USD', $payload]);

bot_ok([
    'invoice_id' => $inv['invoice_id'],
    'pay_url' => $inv['pay_url'] ?? $inv['bot_invoice_url'] ?? '',
    'mini_app_url' => $inv['mini_app_invoice_url'] ?? $inv['bot_invoice_url'] ?? $inv['pay_url'] ?? '',
    'amount_rub' => $amountRub,
    'amount_usd' => $amountUsd,
    'currency' => $currency,
]);

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);
$userId = (int)$u['id'];

if (!defined('CRYPTOBOT_TOKEN')) {
    require_once __DIR__ . '/_cryptobot_config.php';
}

// Create multi-asset invoice (USDT primary, user picks asset on payment page)
// CryptoBot supports `currency_type: "fiat"` with `accepted_assets` list for multi-asset choice.
$payload = json_encode([
    'user_id' => $userId,
    'amount_rub' => $amountRub,
    'amount_usd' => $amountUsd,
    'currency' => $currency,
    'tg_id' => $tgId,
]);

$ch = curl_init('https://pay.crypt.bot/api/createInvoice');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Crypto-Pay-API-Token: ' . CRYPTOBOT_TOKEN,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'currency_type' => 'fiat',
        'fiat' => 'USD',
        'amount' => (string)$amountUsd,
        'accepted_assets' => 'USDT,TON,BTC,ETH,LTC,BNB,TRX,USDC',
        'description' => 'Scarlex top-up: ' . $amountRub . ' ₽ ($' . $amountUsd . ')',
        'hidden_message' => 'Платёж зачислен. Возвращайтесь в @scarlex_bot',
        'paid_btn_name' => 'callback',
        'paid_btn_url' => 'https://t.me/scarlex_bot',
        'payload' => $payload,
        'allow_comments' => false,
        'allow_anonymous' => true,
        'expires_in' => 3600,
    ]),
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

if (empty($resp['ok'])) {
    bot_err('CryptoBot error: ' . ($resp['error']['name'] ?? json_encode($resp)), 500);
}

$inv = $resp['result'];

$pdo->prepare("INSERT INTO pending_topups (user_id, provider, invoice_id, amount_rub, amount_provider, currency, status, payload)
              VALUES (?, 'cryptobot', ?, ?, ?, ?, 'pending', ?)")
    ->execute([$userId, (string)$inv['invoice_id'], $amountRub, $amountUsd, 'USD', $payload]);

bot_ok([
    'invoice_id' => $inv['invoice_id'],
    'pay_url' => $inv['pay_url'] ?? $inv['bot_invoice_url'] ?? '',
    'mini_app_url' => $inv['mini_app_invoice_url'] ?? $inv['bot_invoice_url'] ?? $inv['pay_url'] ?? '',
    'amount_rub' => $amountRub,
    'amount_usd' => $amountUsd,
    'currency' => $currency,
]);

