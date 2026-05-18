<?php
// Bot-only: confirm "Login via Telegram" by binding user_id to a pending tg_login_sessions row.
// Body: { telegram_id, token }
// The user must already exist (bot calls auth.php before this).

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';

bot_auth_or_die();
$in    = bot_input();
$tgId  = (int)($in['telegram_id'] ?? 0);
$token = trim((string)($in['token'] ?? ''));
if (!$tgId || $token === '') bot_err('telegram_id and token required');

$u = $pdo->prepare('SELECT id, email, username FROM users WHERE telegram_id = ? LIMIT 1');
$u->execute([$tgId]);
$user = $u->fetch();
if (!$user) bot_err('User not found', 404);

$stmt = $pdo->prepare("SELECT token, status, expires_at FROM tg_login_sessions WHERE token = ? LIMIT 1");
$stmt->execute([$token]);
$row = $stmt->fetch();
if (!$row) bot_err('Ссылка устарела. Открой её заново на сайте.', 410);
if (strtotime((string)$row['expires_at']) < time()) bot_err('Ссылка истекла', 410);
if ($row['status'] !== 'pending') bot_err('Ссылка уже использована', 409);

$pdo->prepare(
    "UPDATE tg_login_sessions
        SET user_id = ?, status = 'confirmed', confirmed_at = NOW()
      WHERE token = ?"
)->execute([(int)$user['id'], $token]);

bot_ok([
    'user_id'  => (int)$user['id'],
    'email'    => $user['email'],
    'username' => $user['username'],
]);
