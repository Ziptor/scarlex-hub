<?php
// Support tickets API (user side).
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_tg_notify.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) { echo json_encode(['ok' => false, 'error' => 'Не авторизован']); exit; }

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT id, subject, status, created_at, updated_at,
                            (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id) AS msg_count
                           FROM support_tickets t WHERE user_id = ? ORDER BY updated_at DESC LIMIT 200");
    $stmt->execute([$userId]);
    echo json_encode(['ok' => true, 'tickets' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'id required']); exit; }
    $t = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
    $t->execute([$id, $userId]);
    $ticket = $t->fetch();
    if (!$ticket) { echo json_encode(['ok' => false, 'error' => 'not found']); exit; }
    $m = $pdo->prepare("SELECT sender, body, created_at FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $m->execute([$id]);
    echo json_encode(['ok' => true, 'ticket' => $ticket, 'messages' => $m->fetchAll()]);
    exit;
}

if ($action === 'create') {
    $subject = trim((string)($input['subject'] ?? ''));
    $body    = trim((string)($input['body'] ?? ''));
    if ($subject === '' || $body === '') { echo json_encode(['ok' => false, 'error' => 'Заполните тему и сообщение']); exit; }
    if (mb_strlen($subject) > 200) $subject = mb_substr($subject, 0, 200);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO support_tickets (user_id, subject, status) VALUES (?, ?, 'open')")
            ->execute([$userId, $subject]);
        $tid = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO support_messages (ticket_id, sender, body) VALUES (?, 'user', ?)")
            ->execute([$tid, $body]);
        $pdo->commit();
        try { tg_alert($pdo, $userId, 'support_ticket', "🆘 <b>Новый тикет #{$tid}</b>\n\n📌 " . htmlspecialchars($subject) . "\n\n" . htmlspecialchars(mb_substr($body, 0, 300)), 'ticket_' . $tid, 0); } catch (Exception $e) {}
        echo json_encode(['ok' => true, 'ticket_id' => $tid]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => 'db error']);
        exit;
    }
}

if ($action === 'reply') {
    $id   = (int)($input['ticket_id'] ?? 0);
    $body = trim((string)($input['body'] ?? ''));
    if (!$id || $body === '') { echo json_encode(['ok' => false, 'error' => 'Заполните сообщение']); exit; }
    $chk = $pdo->prepare("SELECT status FROM support_tickets WHERE id = ? AND user_id = ?");
    $chk->execute([$id, $userId]);
    $st = $chk->fetchColumn();
    if ($st === false) { echo json_encode(['ok' => false, 'error' => 'not found']); exit; }
    if ($st === 'closed') { echo json_encode(['ok' => false, 'error' => 'Тикет закрыт']); exit; }
    $pdo->prepare("INSERT INTO support_messages (ticket_id, sender, body) VALUES (?, 'user', ?)")
        ->execute([$id, $body]);
    $pdo->prepare("UPDATE support_tickets SET status = 'open', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'close') {
    $id = (int)($input['ticket_id'] ?? 0);
    $pdo->prepare("UPDATE support_tickets SET status='closed', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?")
        ->execute([$id, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
