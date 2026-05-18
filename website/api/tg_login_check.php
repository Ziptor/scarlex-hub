<?php
// Step 2: poll. Frontend calls this once per ~2s with the same token.
// When status='confirmed' AND the request comes from the SAME PHPSESSID that issued
// the token, we set $_SESSION['user_id'] and mark the row 'consumed'.
//
// GET ?token=...
// Returns: { ok:true, status: 'pending' | 'confirmed' | 'expired' | 'consumed' | 'logged_in' }

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') { echo json_encode(['ok' => false, 'error' => 'token required']); exit; }

$stmt = $pdo->prepare("SELECT token, php_session_id, user_id, status, expires_at FROM tg_login_sessions WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$row = $stmt->fetch();
if (!$row) { echo json_encode(['ok' => true, 'status' => 'expired']); exit; }

if ($row['status'] === 'consumed') {
    echo json_encode(['ok' => true, 'status' => 'consumed']);
    exit;
}

if (strtotime((string)$row['expires_at']) < time()) {
    echo json_encode(['ok' => true, 'status' => 'expired']);
    exit;
}

if ($row['status'] !== 'confirmed') {
    echo json_encode(['ok' => true, 'status' => 'pending']);
    exit;
}

// status === 'confirmed' → bind session. Must come from same browser that issued token.
if ($row['php_session_id'] !== session_id()) {
    echo json_encode(['ok' => false, 'status' => 'pending', 'error' => 'session mismatch']);
    exit;
}

$userId = (int)$row['user_id'];
if (!$userId) { echo json_encode(['ok' => true, 'status' => 'pending']); exit; }

$u = $pdo->prepare('SELECT id, username, is_banned, ban_reason FROM users WHERE id = ? LIMIT 1');
$u->execute([$userId]);
$user = $u->fetch();
if (!$user) { echo json_encode(['ok' => false, 'error' => 'user_gone']); exit; }
if (!empty($user['is_banned'])) {
    echo json_encode(['ok' => false, 'error' => 'banned', 'reason' => $user['ban_reason'] ?: '']);
    exit;
}

$_SESSION['user_id']  = (int)$user['id'];
$_SESSION['username'] = $user['username'];
$pdo->prepare("UPDATE tg_login_sessions SET status = 'consumed' WHERE token = ?")->execute([$token]);

if (function_exists('record_session')) {
    try { record_session($pdo, (int)$user['id']); } catch (\Throwable $e) {}
}

echo json_encode(['ok' => true, 'status' => 'logged_in']);
