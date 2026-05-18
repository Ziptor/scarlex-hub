<?php
/**
 * Public incidents endpoint — recent + ongoing incidents.
 * Used by status.html. Cached client-side via Cache-Control.
 *
 * GET /api/incidents.php?limit=10  → recent incidents (default 10, max 50)
 * GET /api/incidents.php?status=resolved&limit=20
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30');
require_once __DIR__ . '/db.php';

$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
$status = $_GET['status'] ?? '';  // optional filter

try {
    if ($status && in_array($status, ['investigating','identified','monitoring','resolved'], true)) {
        $stmt = $pdo->prepare(
            'SELECT id, title, description, severity, status, component, started_at, resolved_at '
            . 'FROM incidents WHERE status = ? ORDER BY started_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $status);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, title, description, severity, status, component, started_at, resolved_at '
            . 'FROM incidents ORDER BY started_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db error']);
    exit;
}

// Count of currently-open incidents
try {
    $open = (int)$pdo->query(
        "SELECT COUNT(*) FROM incidents WHERE status != 'resolved'"
    )->fetchColumn();
} catch (Exception $e) {
    $open = 0;
}

echo json_encode([
    'ok' => true,
    'incidents' => $rows,
    'open_count' => $open,
    'updated_at' => date('c'),
], JSON_UNESCAPED_UNICODE);
