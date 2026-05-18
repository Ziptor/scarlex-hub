<?php
// Bot-only: issue one-time site auto-login URL for a Telegram user.
// Request:  POST { telegram_id }
// Response: { ok:true, url:"https://scarlex.ru/api/auto_login.php?t=...", ttl:600 }

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../_link_tokens.php';

bot_auth_or_die();

$in = bot_input();
$tgId = (int)($in['telegram_id'] ?? 0);
if (!$tgId) bot_err('telegram_id required');

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ? LIMIT 1');
$stmt->execute([$tgId]);
$row = $stmt->fetch();
if (!$row) bot_err('User not found. Link your Telegram first.', 404);

$userId = (int)$row['id'];
$token  = issue_link_token($pdo, $userId, 'site_login', 600);

bot_ok([
    'url' => 'https://scarlex.ru/api/auto_login.php?t=' . $token,
    'ttl' => 600,
]);
