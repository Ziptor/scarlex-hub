<?php
// CryptoBot webhook receiver: confirms paid invoices, credits balance, notifies user in TG.
// Webhook URL: https://scarlex.ru/api/bot/cryptobot_webhook.php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/_cryptobot_config.php';
require_once __DIR__ . '/../_referrals_helpers.php';
require_once __DIR__ . '/../_tg_notify.php';
// TG_BOT_TOKEN / TG_PROXY_URL are loaded via _tg_notify.php → secrets.php

$body = file_get_contents('php://input');
$signature = $_SERVER['HTTP_CRYPTO_PAY_API_SIGNATURE'] ?? '';

// Verify signature: HMAC-SHA256 of body using SHA256(token) as key
$secret = hash('sha256', CRYPTOBOT_TOKEN, true);
$expected = hash_hmac('sha256', $body, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid signature']);
    exit;
}

$update = json_decode($body, true);
if (!$update || ($update['update_type'] ?? '') !== 'invoice_paid') {
    echo json_encode(['ok' => true, 'note' => 'ignored']);
    exit;
}

$inv = $update['payload'] ?? [];
$invoiceId = (string)($inv['invoice_id'] ?? '');
$status = $inv['status'] ?? '';
$paidAsset = $inv['asset'] ?? $inv['paid_asset'] ?? '';
$paidAmount = $inv['paid_amount'] ?? $inv['amount'] ?? '';

if ($status !== 'paid' || !$invoiceId) {
    echo json_encode(['ok' => true, 'note' => 'not paid']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, user_id, amount_rub, status FROM pending_topups WHERE provider = 'cryptobot' AND invoice_id = ?");
$stmt->execute([$invoiceId]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Invoice not found in DB']);
    exit;
}
if ($row['status'] === 'paid') {
    echo json_encode(['ok' => true, 'note' => 'already processed']);
    exit;
}

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE pending_topups SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'pending'")
        ->execute([$row['id']]);
    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")
        ->execute([$row['amount_rub'], $row['user_id']]);
    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'topup', ?, ?)")
        ->execute([$row['user_id'], $row['amount_rub'], 'CryptoBot #' . $invoiceId . ($paidAsset ? ' (' . $paidAmount . ' ' . $paidAsset . ')' : '')]);
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('cryptobot_webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal error']);
    exit;
}

// ── Referral 5% lifetime bonus ──
try {
    credit_referral_topup_bonus($pdo, (int)$row['user_id'], (float)$row['amount_rub'], (int)$row['id']);
} catch (Exception $e) { /* best-effort */ }

// Notify user in TG
try {
    $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
    $stmt->execute([$row['user_id']]);
    $u = $stmt->fetch();
    $bal = $u ? (float)$u['balance'] : 0;
    $msg = "✅ <b>Платёж зачислен!</b>\n\n"
        . "💰 Сумма: <b>" . number_format((float)$row['amount_rub'], 2, '.', ' ') . " ₽</b>\n"
        . "🧾 Счёт: <code>#" . $invoiceId . "</code>\n"
        . ($paidAsset ? "🪙 Оплачено: <b>" . $paidAmount . " " . $paidAsset . "</b>\n" : "")
        . "💵 Новый баланс: <b>" . number_format($bal, 2, '.', ' ') . " ₽</b>\n\n"
        . "Теперь можно купить ключ в 🛒 Магазине.";
    tg_alert($pdo, (int)$row['user_id'], 'payment_received', $msg, 'cryptobot_' . $invoiceId, 0);
} catch (Exception $e) {
    error_log('TG notify failed: ' . $e->getMessage());
}

echo json_encode(['ok' => true]);

