<?php
// Step 1 of "Login via Telegram" deep-link flow.
// Creates a pending session and returns a deep-link the user can open in @scarlex_bot.
//
// POST (no body required, but accepted as JSON).
// Returns: { ok:true, token, deep_link, ttl }

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

// Anonymous endpoint: caller does NOT need to be logged in.
$sid = session_id();
if (!$sid) {
    echo json_encode(['ok' => false, 'error' => 'No session']);
    exit;
}

// Best-effort cleanup of stale rows
try { $pdo->exec("DELETE FROM tg_login_sessions WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"); } catch (\Throwable $e) {}

$token = bin2hex(random_bytes(24));
$ttl   = 300; // 5 min
$pdo->prepare(
    "INSERT INTO tg_login_sessions (token, php_session_id, status, expires_at)
     VALUES (?, ?, 'pending', DATE_ADD(NOW(), INTERVAL ? SECOND))"
)->execute([$token, $sid, $ttl]);

echo json_encode([
    'ok'        => true,
    'token'     => $token,
    'deep_link' => 'https://t.me/scarlex_bot?start=tglogin_' . $token,
    'ttl'       => $ttl,
]);
