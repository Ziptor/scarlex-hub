<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? 'list';
$userId = (int)($_SESSION['user_id'] ?? 0);

// ── Public-ish: список последних опубликованных новостей ──
if ($action === 'list') {
    $limit = max(1, min(50, (int)($_GET['limit'] ?? 6)));
    $sql = "SELECT id, title, body, kind, is_pinned, created_at
            FROM news_posts
            WHERE is_published = 1
            ORDER BY is_pinned DESC, created_at DESC
            LIMIT $limit";
    $rows = $pdo->query($sql)->fetchAll();

    $unread = [];
    if ($userId > 0 && $rows) {
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in  = implode(',', $ids);
        $st  = $pdo->prepare("SELECT post_id FROM news_reads WHERE user_id = ? AND post_id IN ($in)");
        $st->execute([$userId]);
        $read = array_map(fn($r) => (int)$r['post_id'], $st->fetchAll());
        foreach ($rows as &$r) {
            $r['is_read'] = in_array((int)$r['id'], $read, true);
        }
    } else {
        foreach ($rows as &$r) $r['is_read'] = false;
    }

    echo json_encode(['ok' => true, 'posts' => $rows]);
    exit;
}

// ── unread_count: сколько непрочитанных у текущего пользователя ──
if ($action === 'unread_count') {
    if (!$userId) { echo json_encode(['ok' => true, 'count' => 0]); exit; }
    $st = $pdo->prepare("SELECT COUNT(*) FROM news_posts p
                         WHERE p.is_published = 1
                           AND NOT EXISTS (SELECT 1 FROM news_reads r WHERE r.user_id = ? AND r.post_id = p.id)");
    $st->execute([$userId]);
    echo json_encode(['ok' => true, 'count' => (int)$st->fetchColumn()]);
    exit;
}

// ── ниже требуем авторизацию ──
if (!$userId) { echo json_encode(['ok' => false, 'error' => 'Не авторизован']); exit; }

if ($action === 'mark_read') {
    $id = (int)($input['post_id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false, 'error' => 'post_id required']); exit; }
    $pdo->prepare("INSERT IGNORE INTO news_reads (user_id, post_id) VALUES (?, ?)")
        ->execute([$userId, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'mark_all_read') {
    $pdo->prepare("INSERT IGNORE INTO news_reads (user_id, post_id)
                   SELECT ?, id FROM news_posts WHERE is_published = 1")
        ->execute([$userId]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
