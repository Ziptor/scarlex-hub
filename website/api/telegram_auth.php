<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/secrets.php';

if (!defined('TELEGRAM_BOT_TOKEN')) define('TELEGRAM_BOT_TOKEN', TG_BOT_TOKEN);
if (!defined('AUTH_EXPIRY')) define('AUTH_EXPIRY', 86400); // 24h — reject stale Telegram auth

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok' => false, 'error' => 'Bad request']);
    exit;
}

// 1. Validate hash per https://core.telegram.org/widgets/login#checking-authorization
$checkData = [];
foreach ($input as $key => $value) {
    if ($key === 'hash') continue;
    $checkData[] = "$key=$value";
}
sort($checkData);
$dataCheckString = implode("\n", $checkData);

$secretKey = hash('sha256', TELEGRAM_BOT_TOKEN, true);
$hmac = hash_hmac('sha256', $dataCheckString, $secretKey);

if (!hash_equals($hmac, $input['hash'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'Невалидная подпись Telegram']);
    exit;
}

// 2. Check auth_date freshness
if (time() - (int)($input['auth_date'] ?? 0) > AUTH_EXPIRY) {
    echo json_encode(['ok' => false, 'error' => 'Авторизация устарела, попробуйте снова']);
    exit;
}

$telegramId = (int)$input['id'];
$tgUsername = $input['username'] ?? '';
$firstName = $input['first_name'] ?? '';
$lastName = $input['last_name'] ?? '';
$displayName = trim("$firstName $lastName") ?: ($tgUsername ?: "tg_$telegramId");

// 3. Find existing user by telegram_id
$stmt = $pdo->prepare('SELECT id, username FROM users WHERE telegram_id = ?');
$stmt->execute([$telegramId]);
$user = $stmt->fetch();

if ($user) {
    // Update telegram_username if changed
    if ($tgUsername) {
        $pdo->prepare('UPDATE users SET telegram_username = ? WHERE id = ?')->execute([$tgUsername, $user['id']]);
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    record_session($pdo, $user['id']);
    echo json_encode(['ok' => true]);
    exit;
}

// 4. Auto-register new user
$username = $tgUsername ?: ('tg_' . $telegramId);
$email = 'tg_' . $telegramId . '@telegram.local';

// Ensure unique username
$base = $username;
$suffix = 0;
while (true) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if (!$stmt->fetch()) break;
    $suffix++;
    $username = $base . '_' . $suffix;
}

$stmt = $pdo->prepare('INSERT INTO users (email, username, password_hash, telegram_id, telegram_username, public_id) VALUES (?, ?, NULL, ?, ?, ?)');
$stmt->execute([$email, $username, $telegramId, $tgUsername, generate_public_id($pdo)]);

$userId = $pdo->lastInsertId();
$_SESSION['user_id'] = $userId;
$_SESSION['username'] = $username;

// Record session
function record_session_tg($pdo, $userId) {
    $sid = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $stmt = $pdo->prepare('SELECT id FROM user_sessions WHERE id = ?');
    $stmt->execute([$sid]);
    if ($stmt->fetch()) {
        $pdo->prepare('UPDATE user_sessions SET last_seen = CURRENT_TIMESTAMP WHERE id = ?')->execute([$sid]);
    } else {
        $pdo->prepare('INSERT INTO user_sessions (id, user_id, ip, user_agent) VALUES (?, ?, ?, ?)')->execute([$sid, $userId, $ip, $ua]);
    }
}
record_session_tg($pdo, $userId);

echo json_encode(['ok' => true]);
