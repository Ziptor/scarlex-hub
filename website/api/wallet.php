<?php
// Wallet top-up endpoint.
// Actions:
//   POST { action:'topup_crypto', amount, currency }   - create CryptoBot invoice (RUB or USD)
//   POST { action:'topup_card',   amount }             - create YooMoney quickpay redirect (RUB only, card)
//   GET  ?action=balance                              - return current balance
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cbr_rate.php';
require_once __DIR__ . '/bot/_cryptobot_config.php';
require_once __DIR__ . '/_yoomoney_config.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
$action = $input['action'] ?? $_GET['action'] ?? 'balance';

if ($action === 'balance') {
    $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    echo json_encode(['ok' => true, 'balance' => (float)($u['balance'] ?? 0)]);
    exit;
}

if ($action === 'topup_crypto') {
    $rawCur = strtoupper(trim((string)($input['currency'] ?? 'RUB')));
    $amount = (float)($input['amount'] ?? 0);
    if (!in_array($rawCur, ['RUB', 'USD'], true)) $rawCur = 'RUB';
    if ($amount <= 0) { echo json_encode(['ok' => false, 'error' => 'Сумма должна быть больше 0']); exit; }

    $rate = get_usd_rub_rate();
    if (!$rate || empty($rate['rate'])) { echo json_encode(['ok' => false, 'error' => 'Курс ЦБ временно недоступен']); exit; }

    if ($rawCur === 'RUB') {
        if ($amount < 100) { echo json_encode(['ok' => false, 'error' => 'Минимальная сумма: 100 ₽']); exit; }
        if ($amount > 500000) { echo json_encode(['ok' => false, 'error' => 'Максимум: 500 000 ₽']); exit; }
        $amountRub = round($amount, 2);
        $amountUsd = round($amount / $rate['rate'], 2);
    } else {
        if ($amount < 1) { echo json_encode(['ok' => false, 'error' => 'Минимум: 1 $']); exit; }
        if ($amount > 5000) { echo json_encode(['ok' => false, 'error' => 'Максимум: 5000 $']); exit; }
        $amountUsd = round($amount, 2);
        $amountRub = round($amount * $rate['rate'], 2);
    }
    if ($amountUsd < 0.1) { echo json_encode(['ok' => false, 'error' => 'Сумма слишком мала для CryptoBot']); exit; }

    $payload = json_encode([
        'user_id' => $userId,
        'amount_rub' => $amountRub,
        'amount_usd' => $amountUsd,
        'currency' => $rawCur,
        'source' => 'web_dashboard',
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
            'paid_btn_name' => 'callback',
            'paid_btn_url' => 'https://scarlex.ru/dashboard.html?topup=success',
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
        echo json_encode(['ok' => false, 'error' => 'CryptoBot: ' . ($resp['error']['name'] ?? json_encode($resp))]);
        exit;
    }

    $inv = $resp['result'];
    $pdo->prepare("INSERT INTO pending_topups (user_id, provider, invoice_id, amount_rub, amount_provider, currency, status, payload)
                  VALUES (?, 'cryptobot', ?, ?, ?, 'USD', 'pending', ?)")
        ->execute([$userId, (string)$inv['invoice_id'], $amountRub, $amountUsd, $payload]);

    echo json_encode([
        'ok' => true,
        'provider' => 'cryptobot',
        'invoice_id' => $inv['invoice_id'],
        'pay_url' => $inv['pay_url'] ?? $inv['bot_invoice_url'] ?? '',
        'amount_rub' => $amountRub,
        'amount_usd' => $amountUsd,
    ]);
    exit;
}

if ($action === 'topup_card') {
    $amount = (float)($input['amount'] ?? 0);
    if ($amount < 100) { echo json_encode(['ok' => false, 'error' => 'Минимальная сумма: 100 ₽']); exit; }
    if ($amount > 60000) { echo json_encode(['ok' => false, 'error' => 'Максимум: 60 000 ₽']); exit; }
    if (!YOOMONEY_WALLET) { echo json_encode(['ok' => false, 'error' => 'Платёжный шлюз картой временно недоступен. Используйте CryptoBot.']); exit; }

    $amountRub = round($amount, 2);
    // label is our internal invoice id, returned as-is in the webhook
    $label = 'sx_' . $userId . '_' . bin2hex(random_bytes(8));

    // Persist pending invoice
    $pdo->prepare("INSERT INTO pending_topups (user_id, provider, invoice_id, amount_rub, amount_provider, currency, status, payload)
                  VALUES (?, 'yoomoney', ?, ?, ?, 'RUB', 'pending', ?)")
        ->execute([$userId, $label, $amountRub, $amountRub, json_encode(['label' => $label])]);

    // YooMoney quickpay form: GET request to confirm.xml redirects user to payment page.
    // We return that URL with all params; frontend opens it in a new tab.
    $params = [
        'receiver' => YOOMONEY_WALLET,
        'quickpay-form' => 'shop',
        'targets' => 'Пополнение баланса Scarlex (' . $amountRub . ' ₽)',
        'paymentType' => 'AC', // AC = card, PC = YooMoney wallet, MC = mobile
        'sum' => number_format($amountRub, 2, '.', ''),
        'label' => $label,
        'successURL' => YOOMONEY_SUCCESS_URL,
    ];
    $payUrl = YOOMONEY_QUICKPAY_URL . '?' . http_build_query($params);

    echo json_encode([
        'ok' => true,
        'provider' => 'yoomoney',
        'invoice_id' => $label,
        'pay_url' => $payUrl,
        'amount_rub' => $amountRub,
    ]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
