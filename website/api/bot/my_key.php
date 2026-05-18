<?php
// GET active API key(s) for TG user
// Body: { telegram_id }
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';

bot_auth_or_die();
$in = bot_input();
$tgId = (int)($in['telegram_id'] ?? 0);
if (!$tgId) bot_err('telegram_id required');

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);

$stmt = $pdo->prepare("SELECT id, key_value, label, tokens_limit, used_tokens, is_active, created_at
                       FROM api_keys
                       WHERE user_id = ? AND tag = 'occupied'
                       ORDER BY created_at DESC");
$stmt->execute([$u['id']]);
$keys = $stmt->fetchAll();

bot_ok(['keys' => $keys]);
