<?php
// Referral overview for bot user
// Body: { telegram_id }
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../_referrals_helpers.php';

bot_auth_or_die();
$in = bot_input();
$tgId = (int)($in['telegram_id'] ?? 0);
if (!$tgId) bot_err('telegram_id required');

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);
$userId = (int)$u['id'];

$code = ensure_referral_code($pdo, $userId);
$link = 'https://scarlex.ru/authorization.html?ref=' . urlencode($code);

$r = $pdo->prepare('SELECT COUNT(*) FROM users WHERE referrer_id = ?');
$r->execute([$userId]);
$totalRefs = (int)$r->fetchColumn();

$r = $pdo->prepare(
    "SELECT COUNT(DISTINCT u.id)
     FROM users u
     INNER JOIN transactions t ON t.user_id = u.id AND t.type = 'topup'
     WHERE u.referrer_id = ?"
);
$r->execute([$userId]);
$activeRefs = (int)$r->fetchColumn();

$r = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM referral_bonuses WHERE referrer_id = ?');
$r->execute([$userId]);
$totalEarned = (float)$r->fetchColumn();

$r = $pdo->prepare(
    'SELECT b.amount, b.description, b.created_at, u.username AS referred_username
     FROM referral_bonuses b
     LEFT JOIN users u ON u.id = b.referred_id
     WHERE b.referrer_id = ?
     ORDER BY b.created_at DESC
     LIMIT 5'
);
$r->execute([$userId]);
$bonuses = $r->fetchAll();

bot_ok([
    'code' => $code,
    'link' => $link,
    'total_referrals' => $totalRefs,
    'active_referrals' => $activeRefs,
    'total_earned' => round($totalEarned, 2),
    'bonuses' => $bonuses,
]);
