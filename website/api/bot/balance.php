<?php
// GET balance + recent transactions + aggregated token usage
// Body: { telegram_id }
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../cbr_rate.php';

bot_auth_or_die();
$in = bot_input();
$tgId = (int)($in['telegram_id'] ?? 0);
if (!$tgId) bot_err('telegram_id required');

$stmt = $pdo->prepare('SELECT id, public_id, balance FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);

$stmt = $pdo->prepare('SELECT type, amount, description, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
$stmt->execute([$u['id']]);
$txs = $stmt->fetchAll();

// Aggregated across user's keys
$stmt = $pdo->prepare("SELECT COALESCE(SUM(tokens_limit), 0) as total_tokens, COALESCE(SUM(used_tokens), 0) as used_tokens
                       FROM api_keys WHERE user_id = ? AND tag = 'occupied'");
$stmt->execute([$u['id']]);
$k = $stmt->fetch();

// Chat requests count
$stmt = $pdo->prepare('SELECT COUNT(*) as requests FROM chat_transactions WHERE user_id = ?');
$stmt->execute([$u['id']]);
$ct = $stmt->fetch();

$rate = get_usd_rub_rate();
$balanceRub = (float)$u['balance'];
$balanceUsd = $rate['rate'] > 0 ? round($balanceRub / $rate['rate'], 2) : 0;

bot_ok([
    'user_id' => (int)$u['id'],
    'public_id' => $u['public_id'],
    'balance_rub' => $balanceRub,
    'balance_usd' => $balanceUsd,
    'tokens_total' => (int)$k['total_tokens'],
    'tokens_used' => (int)$k['used_tokens'],
    'tokens_left' => max(0, (int)$k['total_tokens'] - (int)$k['used_tokens']),
    'requests' => (int)$ct['requests'],
    'transactions' => $txs,
    'rate' => $rate,
]);

