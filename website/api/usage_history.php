<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = $_SESSION['user_id'];
$keyId = (int)($_GET['key_id'] ?? 0);
$period = $_GET['period'] ?? '24h';

if (!$keyId) {
    echo json_encode(['ok' => false, 'error' => 'key_id required']);
    exit;
}

// Verify key belongs to user
$stmt = $pdo->prepare('SELECT id FROM api_keys WHERE id = ? AND user_id = ?');
$stmt->execute([$keyId, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Key not found']);
    exit;
}

// Period filter
switch ($period) {
    case '7d':  $interval = 7; break;
    case '30d': $interval = 30; break;
    default:    $interval = 1; break;
}

$stmt = $pdo->prepare("
    SELECT used_tokens, max_tokens, recorded_at 
    FROM usage_history 
    WHERE api_key_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ORDER BY recorded_at ASC
");
$stmt->execute([$keyId, $interval]);
$history = $stmt->fetchAll();

echo json_encode(['ok' => true, 'history' => $history]);
