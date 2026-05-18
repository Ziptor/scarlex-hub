<?php
// Session-protected: issue a one-time token for "attach my Telegram to this site account".
// Returns:
//   { ok: true, token, deep_link: "https://t.me/scarlex_bot?start=link_<token>", ttl: 600 }

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_link_tokens.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Prevent issuing a link token if user is already linked
$stmt = $pdo->prepare('SELECT telegram_id FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$row = $stmt->fetch();
if ($row && !empty($row['telegram_id'])) {
    echo json_encode([
        'ok' => false,
        'error' => 'Telegram уже привязан',
        'telegram_id' => (int)$row['telegram_id'],
    ]);
    exit;
}

$token = issue_link_token($pdo, $userId, 'tg_link', 600);

echo json_encode([
    'ok'        => true,
    'token'     => $token,
    'deep_link' => 'https://t.me/scarlex_bot?start=link_' . $token,
    'ttl'       => 600,
]);
