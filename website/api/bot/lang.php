<?php
// Get / set user language preference
// Body: { telegram_id, lang? } - if lang provided → set, otherwise → get
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';

bot_auth_or_die();
$in = bot_input();
$tgId = (int)($in['telegram_id'] ?? 0);
$lang = trim((string)($in['lang'] ?? ''));
if (!$tgId) bot_err('telegram_id required');

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);
$userId = (int)$u['id'];

if ($lang) {
    if (!in_array($lang, ['ru', 'en'])) bot_err('Unsupported lang');
    $pdo->prepare("INSERT INTO user_lang (user_id, lang) VALUES (?, ?) ON DUPLICATE KEY UPDATE lang = VALUES(lang)")
        ->execute([$userId, $lang]);
    bot_ok(['lang' => $lang]);
}

$stmt = $pdo->prepare('SELECT lang FROM user_lang WHERE user_id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();
bot_ok(['lang' => $row['lang'] ?? 'ru']);
