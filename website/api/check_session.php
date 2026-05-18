<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$sid = session_id();
// Release session file lock so parallel fetch() calls from admin.html/
// dashboard.html don't serialize behind this script. We've copied user_id
// and session_id into locals — nothing below writes to $_SESSION.
session_write_close();

require_once __DIR__ . '/db.php';

// Enforce soft-revoke: if this PHP session was revoked from another device,
// drop it server-side and force re-login.
$sessRow = null;
if ($sid) {
    $stmt = $pdo->prepare('SELECT id, revoked_at FROM user_sessions WHERE id = ? AND user_id = ?');
    $stmt->execute([$sid, $user_id]);
    $sessRow = $stmt->fetch();
    if ($sessRow && !empty($sessRow['revoked_at'])) {
        // Re-open just to destroy
        session_start();
        session_destroy();
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'session_revoked']);
        exit;
    }
    if ($sessRow) {
        // Touch last_seen so the dashboard sessions list shows recent activity.
        $pdo->prepare('UPDATE user_sessions SET last_seen = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$sid]);
    }
}

$stmt = $pdo->prepare('SELECT id, public_id, email, username, balance, is_banned, ban_reason, telegram_id, telegram_username, created_at FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_start();
    session_destroy();
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true, 'user' => $user, 'session_id' => $sid]);
