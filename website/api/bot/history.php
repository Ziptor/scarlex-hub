<?php
// Paginated transaction history for bot
// Body: { telegram_id, page?, filter? }
// filter: 'all' (default), 'chat', 'financial'
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';

bot_auth_or_die();
$in = bot_input();
$tgId = (int)($in['telegram_id'] ?? 0);
$page = max(1, (int)($in['page'] ?? 1));
$filter = $in['filter'] ?? 'all';
if (!$tgId) bot_err('telegram_id required');

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);
$userId = (int)$u['id'];

$perPage = 15;

// Count total rows for pagination
$totalChat = 0;
$totalFin = 0;
if ($filter === 'all' || $filter === 'chat') {
    $s = $pdo->prepare('SELECT COUNT(*) FROM chat_transactions WHERE user_id = ?');
    $s->execute([$userId]);
    $totalChat = (int)$s->fetchColumn();
}
if ($filter === 'all' || $filter === 'financial') {
    $s = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ?');
    $s->execute([$userId]);
    $totalFin = (int)$s->fetchColumn();
}
$total = $totalChat + $totalFin;
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) $page = $pages;
$offset = ($page - 1) * $perPage;

// Fetch enough rows from each table (up to offset+perPage) then merge & slice
$fetchLimit = $offset + $perPage;
$rows = [];

if ($filter === 'all' || $filter === 'chat') {
    $sql = "SELECT ct.id, 'chat' AS tx_type, ct.model, ct.prompt_tokens, ct.completion_tokens,
                   ct.total_tokens, ct.status, ct.created_at,
                   ak.label AS key_label, ak.key_value
            FROM chat_transactions ct
            LEFT JOIN api_keys ak ON ak.id = ct.api_key_id
            WHERE ct.user_id = ?
            ORDER BY ct.created_at DESC LIMIT ?";
    $s = $pdo->prepare($sql);
    $s->bindValue(1, $userId, PDO::PARAM_INT);
    $s->bindValue(2, $fetchLimit, PDO::PARAM_INT);
    $s->execute();
    foreach ($s->fetchAll() as $r) {
        $r['key_masked'] = $r['key_value'] ? (substr($r['key_value'], 0, 8) . '...' . substr($r['key_value'], -4)) : '';
        unset($r['key_value']);
        $rows[] = $r;
    }
}

if ($filter === 'all' || $filter === 'financial') {
    $sql = "SELECT t.id, 'financial' AS tx_type, t.type, t.amount, t.description, t.created_at,
                   ak.label AS key_label, ak.key_value
            FROM transactions t
            LEFT JOIN api_keys ak ON ak.id = t.api_key_id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC LIMIT ?";
    $s = $pdo->prepare($sql);
    $s->bindValue(1, $userId, PDO::PARAM_INT);
    $s->bindValue(2, $fetchLimit, PDO::PARAM_INT);
    $s->execute();
    foreach ($s->fetchAll() as $r) {
        $r['key_masked'] = $r['key_value'] ? (substr($r['key_value'], 0, 8) . '...' . substr($r['key_value'], -4)) : '';
        unset($r['key_value']);
        $rows[] = $r;
    }
}

usort($rows, function ($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
$slice = array_slice($rows, $offset, $perPage);

bot_ok([
    'page' => $page,
    'pages' => $pages,
    'total' => $total,
    'transactions' => $slice,
]);
