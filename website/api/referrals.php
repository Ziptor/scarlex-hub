<?php
// Referral system endpoint.
// Actions:
//  - overview (default): returns user's code, link, stats, bonus history
//  - lookup_by_code:     ?action=lookup_by_code&code=ABC123 → ok + referrer_username (used on signup page)
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_referrals_helpers.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'overview';

// Public lookup (no auth) — used by registration form to validate ref-code
if ($action === 'lookup_by_code') {
    $code = strtoupper(trim($_GET['code'] ?? ''));
    if (!$code) { echo json_encode(['ok' => false, 'error' => 'Code required']); exit; }
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE referral_code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['ok' => false, 'error' => 'Код не найден']); exit; }
    echo json_encode(['ok' => true, 'referrer_username' => $row['username']]);
    exit;
}

// All other actions require auth
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($action === 'overview') {
    $code = ensure_referral_code($pdo, $userId);

    // Build link
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'scarlex.ru';
    $link = $proto . '://' . $host . '/authorization.html?ref=' . urlencode($code);

    // Total / active referrals
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

    // Total earned
    $r = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM referral_bonuses WHERE referrer_id = ?');
    $r->execute([$userId]);
    $totalEarned = (float)$r->fetchColumn();

    // Bonus history (last 50)
    $r = $pdo->prepare(
        'SELECT b.id, b.bonus_type, b.amount, b.description, b.created_at, u.username AS referred_username
         FROM referral_bonuses b
         LEFT JOIN users u ON u.id = b.referred_id
         WHERE b.referrer_id = ?
         ORDER BY b.created_at DESC
         LIMIT 50'
    );
    $r->execute([$userId]);
    $bonuses = $r->fetchAll();

    echo json_encode([
        'ok' => true,
        'code' => $code,
        'link' => $link,
        'total_referrals' => $totalRefs,
        'active_referrals' => $activeRefs,
        'total_earned' => round($totalEarned, 2),
        'bonuses' => $bonuses,
    ]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
