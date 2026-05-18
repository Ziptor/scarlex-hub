<?php
// Activate balance promocode from bot
// Body: { telegram_id, code }
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';

bot_auth_or_die();
$in = bot_input();
$tgId = (int)($in['telegram_id'] ?? 0);
$code = trim((string)($in['code'] ?? ''));
if (!$tgId) bot_err('telegram_id required');
if (!$code) bot_err('Введите промокод');

$stmt = $pdo->prepare('SELECT id, balance FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);
$userId = (int)$u['id'];

$stmt = $pdo->prepare('SELECT * FROM promocodes WHERE code = ? AND is_active = 1');
$stmt->execute([$code]);
$promo = $stmt->fetch();
if (!$promo) bot_err('Промокод не найден или неактивен');
if ($promo['type'] !== 'balance') bot_err('Этот промокод не для пополнения баланса');
if ($promo['expires_at'] && strtotime($promo['expires_at']) < time()) bot_err('Промокод истёк');
if ((int)$promo['max_uses'] > 0 && (int)$promo['used_count'] >= (int)$promo['max_uses']) bot_err('Промокод исчерпан');

$usageStmt = $pdo->prepare('SELECT id FROM promocode_usages WHERE promo_id = ? AND user_id = ?');
$usageStmt->execute([$promo['id'], $userId]);
if ($usageStmt->fetch()) bot_err('Вы уже использовали этот промокод');

$bonusAmount = (float)$promo['value'];
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$bonusAmount, $userId]);
    $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'bonus', ?, ?)")
        ->execute([$userId, $bonusAmount, 'Промокод ' . $promo['code'] . ': +' . $bonusAmount . ' ₽']);
    $pdo->prepare('INSERT INTO promocode_usages (promo_id, user_id, discount_amount) VALUES (?, ?, ?)')
        ->execute([$promo['id'], $userId, $bonusAmount]);
    $pdo->prepare('UPDATE promocodes SET used_count = used_count + 1 WHERE id = ?')
        ->execute([$promo['id']]);
    $pdo->commit();

    $bs = $pdo->prepare('SELECT balance FROM users WHERE id = ?');
    $bs->execute([$userId]);
    $newBalance = (float)$bs->fetchColumn();
    bot_ok([
        'bonus' => $bonusAmount,
        'new_balance' => round($newBalance, 2),
        'code' => $promo['code'],
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('bot/promo.php failed: ' . $e->getMessage());
    bot_err('Ошибка применения промокода', 500);
}
