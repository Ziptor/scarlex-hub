<?php
// Authenticate / register a Telegram user.
// Body: { telegram_id, username?, first_name? }
// - If user with this telegram_id exists → return user
// - Else create new user (placeholder email + generated public_id)
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

bot_auth_or_die();
$in = bot_input();

$tgId = (int)($in['telegram_id'] ?? 0);
$tgUsername = trim((string)($in['username'] ?? ''));
$firstName = trim((string)($in['first_name'] ?? ''));

if (!$tgId) bot_err('telegram_id required');

// Existing user
$stmt = $pdo->prepare('SELECT id, public_id, email, username, telegram_username, balance, created_at FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$user = $stmt->fetch();

if ($user) {
    // Refresh username if changed
    if ($tgUsername && $tgUsername !== $user['telegram_username']) {
        $pdo->prepare('UPDATE users SET telegram_username = ? WHERE id = ?')->execute([$tgUsername, $user['id']]);
        $user['telegram_username'] = $tgUsername;
    }
    bot_ok(['user' => $user, 'created' => false]);
}

// Create new user
$publicId = generate_public_id($pdo);

// Generate unique placeholder email + username
$baseUsername = $tgUsername ?: ($firstName ?: ('tg' . $tgId));
$baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $baseUsername);
if (strlen($baseUsername) < 3) $baseUsername = 'tg' . $tgId;

$username = $baseUsername;
$suffix = 0;
while (true) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if (!$stmt->fetch()) break;
    $suffix++;
    $username = $baseUsername . $suffix;
}

$email = 'tg' . $tgId . '@telegram.scarlex.ru';

try {
    $stmt = $pdo->prepare('INSERT INTO users (public_id, email, username, telegram_id, telegram_username, balance) VALUES (?, ?, ?, ?, ?, 0)');
    $stmt->execute([$publicId, $email, $username, $tgId, $tgUsername ?: null]);
    $userId = (int)$pdo->lastInsertId();
} catch (Exception $e) {
    error_log('bot/auth.php user creation failed: ' . $e->getMessage());
    bot_err('User creation failed', 500);
}

bot_ok([
    'user' => [
        'id' => $userId,
        'public_id' => $publicId,
        'email' => $email,
        'username' => $username,
        'telegram_username' => $tgUsername,
        'balance' => '0.00',
    ],
    'created' => true,
]);
