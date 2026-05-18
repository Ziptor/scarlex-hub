<?php
session_start();
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = $_SESSION['user_id'];
$rawAction = $_GET['action'] ?? null;
$input = $rawAction === 'export_csv' ? [] : (json_decode(file_get_contents('php://input'), true) ?: []);
$action = $input['action'] ?? $_GET['action'] ?? 'list';

if ($action !== 'export_csv') {
    header('Content-Type: application/json');
}

// ── log_chat / log_financial were removed for security ──
// User-writable transaction logs allowed pollution of admin analytics
// and inflation of achievement progress. Server-side code (chat_proxy.php,
// buy_key.php, wallet.php, yoomoney_callback.php, etc.) already records
// authoritative transactions. Reject these actions.
if ($action === 'log_chat' || $action === 'log_financial') {
    http_response_code(410);
    echo json_encode(['ok' => false, 'error' => 'Endpoint removed. Logging happens server-side.']);
    exit;
}

// ── List all transactions (both types merged) ──
if ($action === 'list') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 50);
    if ($limit < 1) $limit = 50;
    if ($limit > 500) $limit = 500;
    $offset = ($page - 1) * $limit;
    $filter = $_GET['filter'] ?? 'all'; // all, chat, financial
    $keyId = isset($_GET['key_id']) ? (int)$_GET['key_id'] : 0;

    $rows = [];

    if ($filter === 'all' || $filter === 'chat') {
        $sql = "SELECT ct.id, 'chat' as tx_type, ct.model, ct.prompt_tokens, ct.completion_tokens, ct.total_tokens, ct.status, ct.error_message, ct.created_at, ak.label as key_label, ak.key_value FROM chat_transactions ct LEFT JOIN api_keys ak ON ak.id = ct.api_key_id WHERE ct.user_id = ?";
        $params = [$userId];
        if ($keyId) { $sql .= " AND ct.api_key_id = ?"; $params[] = $keyId; }
        $sql .= " ORDER BY ct.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $r['key_masked'] = substr($r['key_value'], 0, 8) . '...' . substr($r['key_value'], -4);
            unset($r['key_value']);
            $rows[] = $r;
        }
    }

    if ($filter === 'all' || $filter === 'financial') {
        $sql = "SELECT t.id, 'financial' as tx_type, t.type, t.amount, t.description, t.created_at, ak.label as key_label, ak.key_value FROM transactions t LEFT JOIN api_keys ak ON ak.id = t.api_key_id WHERE t.user_id = ?";
        $params = [$userId];
        if ($keyId) { $sql .= " AND t.api_key_id = ?"; $params[] = $keyId; }
        $sql .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit; $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            if ($r['key_value']) {
                $r['key_masked'] = substr($r['key_value'], 0, 8) . '...' . substr($r['key_value'], -4);
            }
            unset($r['key_value']);
            $rows[] = $r;
        }
    }

    // Sort merged by date desc
    usort($rows, function($a, $b) {
        return strcmp($b['created_at'], $a['created_at']);
    });

    echo json_encode(['ok' => true, 'transactions' => array_slice($rows, 0, $limit)]);
    exit;
}

if ($action === 'export_csv') {
    $type = $_GET['type'] ?? 'financial'; // financial | chat | all
    $filename = 'scarlex_' . $type . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // BOM for Excel
    fwrite($out, "\xEF\xBB\xBF");

    if ($type === 'financial' || $type === 'all') {
        fputcsv($out, ['Тип', 'Сумма (₽)', 'Описание', 'Ключ', 'Дата']);
        $stmt = $pdo->prepare("SELECT t.type, t.amount, t.description, t.created_at, ak.label, ak.key_value FROM transactions t LEFT JOIN api_keys ak ON ak.id = t.api_key_id WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 5000");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $r) {
            $keyMasked = $r['key_value'] ? (substr($r['key_value'], 0, 8) . '...' . substr($r['key_value'], -4)) : '';
            fputcsv($out, [
                $r['type'],
                number_format((float)$r['amount'], 2, '.', ''),
                $r['description'],
                $r['label'] ? ($r['label'] . ' (' . $keyMasked . ')') : $keyMasked,
                $r['created_at'],
            ]);
        }
    }
    if ($type === 'chat' || $type === 'all') {
        if ($type === 'all') fputcsv($out, []);
        fputcsv($out, ['Модель', 'Prompt токенов', 'Completion токенов', 'Всего', 'Статус', 'Ключ', 'Дата']);
        $stmt = $pdo->prepare("SELECT ct.model, ct.prompt_tokens, ct.completion_tokens, ct.total_tokens, ct.status, ct.created_at, ak.label, ak.key_value FROM chat_transactions ct LEFT JOIN api_keys ak ON ak.id = ct.api_key_id WHERE ct.user_id = ? ORDER BY ct.created_at DESC LIMIT 10000");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $r) {
            $keyMasked = $r['key_value'] ? (substr($r['key_value'], 0, 8) . '...' . substr($r['key_value'], -4)) : '';
            fputcsv($out, [
                $r['model'],
                $r['prompt_tokens'],
                $r['completion_tokens'],
                $r['total_tokens'],
                $r['status'],
                $r['label'] ? ($r['label'] . ' (' . $keyMasked . ')') : $keyMasked,
                $r['created_at'],
            ]);
        }
    }
    fclose($out);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);