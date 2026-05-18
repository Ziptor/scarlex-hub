<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

define('ADMIN_EMAIL', 'Luckyanencko.andrej2017@yandex.ru');
if (!defined('RESELLER_API')) define('RESELLER_API', RESELLER_API_URL);
if (!defined('RESELLER_TOKEN')) define('RESELLER_TOKEN', RESELLER_API_TOKEN);

// Auth check
if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user || strtolower($user['email']) !== strtolower(ADMIN_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Доступ запрещён']);
    exit;
}

// Release session file lock so parallel admin requests don't queue.
// PHP by default holds exclusive lock on session file for the whole script
// lifetime — causes all concurrent fetch() to admin.php to serialize and
// each one wait up to max_execution_time (60s). We've already read user_id;
// nothing below writes to $_SESSION.
session_write_close();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// ── Reseller API helper ──
function reseller_call($method, $path, $body = null) {
    $ch = curl_init(RESELLER_API . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESELLER_TOKEN,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($resp, true);
    return ['code' => $code, 'data' => $data, 'raw' => $resp];
}

// ── Stats ──
if ($action === 'stats') {
    $users = $pdo->query('SELECT COUNT(*) as c FROM users')->fetch()['c'];
    $keys = $pdo->query('SELECT COUNT(*) as c FROM api_keys')->fetch()['c'];
    $activeKeys = $pdo->query('SELECT COUNT(*) as c FROM api_keys WHERE is_active = 1')->fetch()['c'];
    $chatTx = $pdo->query('SELECT COUNT(*) as c FROM chat_transactions')->fetch()['c'];
    $chatTxToday = $pdo->query("SELECT COUNT(*) as c FROM chat_transactions WHERE created_at >= CURDATE()")->fetch()['c'];
    $totalTokens = $pdo->query('SELECT COALESCE(SUM(total_tokens),0) as c FROM chat_transactions WHERE status = \'success\'')->fetch()['c'];
    $financialTx = $pdo->query('SELECT COUNT(*) as c FROM transactions')->fetch()['c'];
    $revenue = $pdo->query('SELECT COALESCE(SUM(amount),0) as c FROM transactions WHERE amount > 0')->fetch()['c'];
    $newUsers = $pdo->query("SELECT COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch()['c'];
    $totalBalance = $pdo->query('SELECT COALESCE(SUM(balance),0) as c FROM users')->fetch()['c'];

    echo json_encode(['ok' => true, 'stats' => [
        'users' => (int)$users,
        'new_users_7d' => (int)$newUsers,
        'keys' => (int)$keys,
        'active_keys' => (int)$activeKeys,
        'chat_requests' => (int)$chatTx,
        'chat_requests_today' => (int)$chatTxToday,
        'total_tokens' => (int)$totalTokens,
        'financial_tx' => (int)$financialTx,
        'revenue' => (float)$revenue,
        'users_total_balance' => (float)$totalBalance,
    ]]);
    exit;
}

// ── Reseller: balance ──
if ($action === 'reseller_balance') {
    $r = reseller_call('GET', '/balance');
    echo json_encode(['ok' => true, 'balance' => $r['data']['data']['balance'] ?? 0]);
    exit;
}

// ── Reseller: products ──
if ($action === 'reseller_products') {
    $r = reseller_call('GET', '/products');
    echo json_encode(['ok' => true, 'products' => $r['data']['data']['products'] ?? []]);
    exit;
}

// ── Reseller: stats ──
if ($action === 'reseller_stats') {
    $days = (int)($_GET['days'] ?? 30);
    $r = reseller_call('GET', '/stats?days=' . $days);
    echo json_encode(['ok' => true, 'stats' => $r['data']['data'] ?? []]);
    exit;
}

// ── Reseller: keys (all from reseller, for admin view) ──
if ($action === 'reseller_keys') {
    $r = reseller_call('GET', '/keys');
    echo json_encode(['ok' => true, 'keys' => $r['data']['data']['keys'] ?? []]);
    exit;
}

// ── Users list ──
if ($action === 'users') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';

    $sql = "SELECT u.id, u.public_id, u.email, u.username, u.telegram_id, u.telegram_username, u.balance, u.is_banned, u.created_at, (SELECT COUNT(*) FROM api_keys WHERE user_id = u.id) as keys_count, (SELECT COUNT(*) FROM chat_transactions WHERE user_id = u.id) as requests_count, (SELECT MAX(last_seen) FROM user_sessions WHERE user_id = u.id) as last_seen FROM users u";
    $params = [];

    if ($search) {
        $sql .= " WHERE u.email LIKE ? OR u.username LIKE ? OR CAST(u.id AS CHAR) = ? OR u.public_id = ?";
        $params = ["%$search%", "%$search%", $search, $search];
    }

    $sql .= " ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['ok' => true, 'users' => $stmt->fetchAll(), 'page' => $page]);
    exit;
}

// ── User details (full) ──
if ($action === 'user_details') {
    $targetId = (int)($_GET['user_id'] ?? $input['user_id'] ?? 0);
    if (!$targetId) { echo json_encode(['ok' => false, 'error' => 'user_id required']); exit; }

    $stmt = $pdo->prepare('SELECT id, public_id, email, username, telegram_id, telegram_username, balance, is_banned, ban_reason, created_at, password_hash IS NOT NULL as has_password FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $u = $stmt->fetch();
    if (!$u) { echo json_encode(['ok' => false, 'error' => 'User not found']); exit; }

    // Sessions
    $stmt = $pdo->prepare('SELECT id, ip, user_agent, created_at, last_seen FROM user_sessions WHERE user_id = ? ORDER BY last_seen DESC LIMIT 10');
    $stmt->execute([$targetId]);
    $sessions = $stmt->fetchAll();

    // Keys (with full key_value, label, tag, limit/used, is_active)
    $stmt = $pdo->prepare('SELECT id, key_value, label, tag, tokens_limit, used_tokens, is_active, source, created_at FROM api_keys WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$targetId]);
    $keys = $stmt->fetchAll();
    // Sync scarlex_proxy keys with Kiro pool (axis-based)
    $kiroMap = null;
    foreach ($keys as &$k) {
        if (($k['source'] ?? '') === 'scarlex_proxy' && defined('KIRO_ADMIN_KEY') && KIRO_ADMIN_KEY !== '') {
            if ($kiroMap === null) {
                $kiroMap = [];
                $r = kiro_request('GET', '/admin/keys');
                if ($r['code'] === 200 && !empty($r['data']['keys'])) {
                    foreach ($r['data']['keys'] as $kk) {
                        if (!empty($kk['key_preview'])) $kiroMap[$kk['key_preview']] = $kk;
                    }
                }
            }
            $kv = (string)$k['key_value'];
            $prev = strlen($kv) > 12 ? substr($kv, 0, 8) . '...' . substr($kv, -4) : $kv;
            if (isset($kiroMap[$prev])) {
                $kk = $kiroMap[$prev];
                $cl = (int)($kk['credits_limit'] ?? 0);
                $cu = (int)($kk['credits_used'] ?? 0);
                $tl = (int)($kk['tokens_limit'] ?? 0);
                $tu = (int)($kk['tokens_used'] ?? 0);
                if ($cl > 0)      { $k['tokens_limit'] = $cl; $k['used_tokens'] = $cu; $k['axis'] = 'credits'; }
                elseif ($tl > 0)  { $k['tokens_limit'] = $tl; $k['used_tokens'] = $tu; $k['axis'] = 'tokens'; }
                else              { $k['tokens_limit'] = 0;   $k['used_tokens'] = max($cu, $tu); $k['axis'] = 'unlimited'; }
                $k['is_active'] = (int)(!empty($kk['enabled']));
                $pdo->prepare('UPDATE api_keys SET tokens_limit = ?, used_tokens = ?, is_active = ? WHERE id = ?')
                    ->execute([(int)$k['tokens_limit'], (int)$k['used_tokens'], (int)$k['is_active'], (int)$k['id']]);
            }
        }
        $k['masked'] = substr($k['key_value'], 0, 10) . '...' . substr($k['key_value'], -6);
        $k['tokens_left'] = max(0, (int)$k['tokens_limit'] - (int)$k['used_tokens']);
        $k['usage_pct'] = $k['tokens_limit'] > 0 ? round($k['used_tokens'] / $k['tokens_limit'] * 100, 1) : 0;
    }
    unset($k);

    // Recent transactions
    $stmt = $pdo->prepare('SELECT type, amount, description, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
    $stmt->execute([$targetId]);
    $txs = $stmt->fetchAll();

    echo json_encode(['ok' => true, 'user' => $u, 'sessions' => $sessions, 'keys' => $keys, 'transactions' => $txs]);
    exit;
}

// ── Change user password ──
if ($action === 'change_password') {
    $targetId = (int)($input['user_id'] ?? 0);
    $newPassword = $input['password'] ?? '';

    if (!$targetId || strlen($newPassword) < 6) {
        echo json_encode(['ok' => false, 'error' => 'user_id и пароль (мин. 6 символов) required']);
        exit;
    }
    if (strlen($newPassword) > 72) {
        echo json_encode(['ok' => false, 'error' => 'Пароль максимум 72 символа']);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $targetId]);
    // Invalidate all sessions
    $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$targetId]);

    echo json_encode(['ok' => true]);
    exit;
}

// ── Assign existing key (by id) to user ──
if ($action === 'assign_key') {
    $keyId = (int)($input['key_id'] ?? 0);
    $targetUserId = (int)($input['user_id'] ?? 0);
    if (!$keyId || !$targetUserId) { echo json_encode(['ok' => false, 'error' => 'key_id и user_id required']); exit; }

    $pdo->prepare("UPDATE api_keys SET user_id = ?, tag = 'occupied' WHERE id = ?")->execute([$targetUserId, $keyId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Release key — set user_id to NULL (make orphan for re-claim) ──
if ($action === 'release_key') {
    $keyId = (int)($input['key_id'] ?? 0);
    if (!$keyId) { echo json_encode(['ok' => false, 'error' => 'key_id required']); exit; }

    $pdo->prepare("UPDATE api_keys SET user_id = NULL, tag = 'reserve' WHERE id = ?")->execute([$keyId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Sync Kiro key — insert orphan record after key creation in kiro.html ──
// Called from kiro.html submitKey() after VPS proxy key is created.
// Inserts into api_keys with user_id=NULL so users can claim it via dashboard.
if ($action === 'sync_kiro_key') {
    $key   = trim((string)($input['key'] ?? ''));
    $name  = trim((string)($input['name'] ?? ''));
    $limit = max(0, (int)($input['tokens_limit'] ?? 0));

    if (!$key || !$name) { echo json_encode(['ok' => false, 'error' => 'key and name required']); exit; }

    // Check if already exists
    $st = $pdo->prepare('SELECT id FROM api_keys WHERE key_value = ?');
    $st->execute([$key]);
    if ($st->fetch()) {
        echo json_encode(['ok' => true, 'exists' => true]);
        exit;
    }

    $pdo->prepare('INSERT INTO api_keys (user_id, key_value, label, tag, tokens_limit, used_tokens, source) VALUES (NULL, ?, ?, ?, ?, 0, ?)')
        ->execute([$key, $name, 'reserve', $limit, 'scarlex_proxy']);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

// ── Give / set user balance ──
if ($action === 'give_balance') {
    $targetId = (int)($input['user_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $description = trim($input['description'] ?? 'Пополнение администратором');

    if (!$targetId || $amount == 0) {
        echo json_encode(['ok' => false, 'error' => 'user_id и amount required']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, balance, telegram_id, telegram_username FROM users WHERE id = ?');
    $stmt->execute([$targetId]);
    $u = $stmt->fetch();
    if (!$u) { echo json_encode(['ok' => false, 'error' => 'User not found']); exit; }

    $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$amount, $targetId]);
    $pdo->prepare('INSERT INTO transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)')
        ->execute([$targetId, $amount > 0 ? 'topup' : 'deduct', $amount, $description]);

    $newBalance = (float)$u['balance'] + $amount;

    // Send dashboard notification + Telegram message (best-effort)
    require_once __DIR__ . '/_tg_notify.php';
    $sign = $amount > 0 ? '+' : '';
    $isCredit = $amount > 0;
    $title = $isCredit ? '💰 Баланс пополнен администратором' : '⚠️ С баланса списано';
    $bodyDash = ($isCredit ? 'Администратор пополнил ваш баланс на ' : 'Администратор списал с баланса ')
              . $sign . number_format($amount, 2, '.', ' ') . ' ₽.'
              . "\nПричина: " . $description
              . "\nТекущий баланс: " . number_format($newBalance, 2, '.', ' ') . ' ₽';
    notify_user($pdo, $targetId, $bodyDash, $title, $isCredit ? 'success' : 'warning');

    if (!empty($u['telegram_id'])) {
        $tgText = ($isCredit ? "💰 <b>Баланс пополнен</b>\n\n" : "⚠️ <b>Списание с баланса</b>\n\n")
                . "Сумма: <b>" . $sign . number_format($amount, 2, '.', ' ') . " ₽</b>\n"
                . "Причина: <i>" . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . "</i>\n"
                . "Текущий баланс: <b>" . number_format($newBalance, 2, '.', ' ') . " ₽</b>\n\n"
                . "<i>Изменение выполнено администратором.</i>";
        tg_notify_user((int)$u['telegram_id'], $tgText);
    }

    echo json_encode(['ok' => true, 'new_balance' => $newBalance]);
    exit;
}

// ── Ban/Unban user ──
if ($action === 'ban_user' || $action === 'unban_user') {
    $targetId = (int)($input['user_id'] ?? 0);
    if (!$targetId) { echo json_encode(['ok' => false, 'error' => 'user_id required']); exit; }

    $banned = $action === 'ban_user' ? 1 : 0;
    $reason = trim($input['reason'] ?? '');
    if ($banned && !$reason) $reason = 'Нарушение правил сервиса';

    if ($banned) {
        $pdo->prepare('UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?')->execute([$reason, $targetId]);
    } else {
        $pdo->prepare('UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?')->execute([$targetId]);
    }
    // Also freeze their keys so API stops working
    $pdo->prepare('UPDATE api_keys SET is_active = ? WHERE user_id = ?')->execute([$banned ? 0 : 1, $targetId]);

    if ($banned) {
        $pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$targetId]);
    }

    echo json_encode(['ok' => true, 'banned' => $banned, 'reason' => $banned ? $reason : null]);
    exit;
}

// ── Delete user ──
if ($action === 'delete_user') {
    $targetId = (int)($input['user_id'] ?? 0);
    if (!$targetId) { echo json_encode(['ok' => false, 'error' => 'user_id required']); exit; }
    if ($targetId == $userId) { echo json_encode(['ok' => false, 'error' => 'Нельзя удалить себя']); exit; }

    try {
        $pdo->beginTransaction();
        // Free up keys (keep them as orphans, not delete — they may belong to reseller)
        $pdo->prepare("UPDATE api_keys SET user_id = NULL, tag = 'reserve' WHERE user_id = ?")->execute([$targetId]);
        // Cleanup user-related rows
        $tables = ['chat_transactions', 'transactions', 'user_sessions', 'user_prefs', 'usage_history',
                   'bot_link_codes', 'pending_topups', 'user_lang', 'email_codes'];
        foreach ($tables as $tbl) {
            try {
                if ($tbl === 'usage_history') {
                    // usage_history is keyed by api_key_id, not user_id — skip
                    continue;
                }
                if ($tbl === 'bot_link_codes') {
                    $u = $pdo->prepare('SELECT telegram_id FROM users WHERE id = ?');
                    $u->execute([$targetId]);
                    $tg = $u->fetchColumn();
                    if ($tg) $pdo->prepare('DELETE FROM bot_link_codes WHERE telegram_id = ?')->execute([$tg]);
                    continue;
                }
                if ($tbl === 'email_codes') {
                    $u = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                    $u->execute([$targetId]);
                    $em = $u->fetchColumn();
                    if ($em) $pdo->prepare('DELETE FROM email_codes WHERE email = ?')->execute([$em]);
                    continue;
                }
                $pdo->prepare("DELETE FROM $tbl WHERE user_id = ?")->execute([$targetId]);
            } catch (Exception $e) { /* table may not exist */ }
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
        $pdo->commit();
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('admin delete_user failed: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Ошибка удаления']);
    }
    exit;
}

// ── Change key tag ──
if ($action === 'change_tag') {
    $keyId = (int)($input['key_id'] ?? 0);
    $tag = trim($input['tag'] ?? '');
    $allowed = ['active', 'reserve', 'occupied'];
    if (!$keyId || !in_array($tag, $allowed)) {
        echo json_encode(['ok' => false, 'error' => 'invalid params']);
        exit;
    }
    $pdo->prepare('UPDATE api_keys SET tag = ? WHERE id = ?')->execute([$tag, $keyId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── All keys (local DB) ──
if ($action === 'keys') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $tagFilter = $_GET['tag'] ?? '';

    $where = [];
    $params = [];
    if ($search) {
        $where[] = "(ak.key_value LIKE ? OR ak.label LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
    }
    if ($tagFilter) {
        $where[] = "ak.tag = ?";
        $params[] = $tagFilter;
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM api_keys ak LEFT JOIN users u ON u.id = ak.user_id" . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sortBy = $_GET['sort_by'] ?? 'created_at';
    $sortDir = strtolower($_GET['sort_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $sortMap = [
        'created_at' => 'ak.created_at',
        'tokens_limit' => 'ak.tokens_limit',
        'used_tokens' => 'ak.used_tokens',
        'usage_percent' => '(CASE WHEN ak.tokens_limit>0 THEN ak.used_tokens/ak.tokens_limit ELSE 0 END)',
        'id' => 'ak.id',
    ];
    $sortCol = $sortMap[$sortBy] ?? 'ak.created_at';

    $sql = "SELECT ak.id, ak.key_value, ak.label, ak.tag, ak.tokens_limit, ak.used_tokens, ak.is_active, ak.source, ak.created_at, ak.user_id, u.username, u.email FROM api_keys ak LEFT JOIN users u ON u.id = ak.user_id" . $whereSql . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['masked'] = substr($r['key_value'], 0, 8) . '...' . substr($r['key_value'], -4);
        $pct = $r['tokens_limit'] > 0 ? round($r['used_tokens'] / $r['tokens_limit'] * 100, 1) : 0;
        $r['usage_percent'] = $pct;
    }

    echo json_encode([
        'ok' => true,
        'keys' => $rows,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $limit)),
        ],
    ]);
    exit;
}

// ── Toggle key active (local only) ──
if ($action === 'toggle_key') {
    $keyId = (int)($input['key_id'] ?? 0);
    if (!$keyId) { echo json_encode(['ok' => false, 'error' => 'key_id required']); exit; }

    $stmt = $pdo->prepare('SELECT is_active FROM api_keys WHERE id = ?');
    $stmt->execute([$keyId]);
    $key = $stmt->fetch();
    if (!$key) { echo json_encode(['ok' => false, 'error' => 'Key not found']); exit; }

    $newActive = $key['is_active'] ? 0 : 1;
    $pdo->prepare('UPDATE api_keys SET is_active = ? WHERE id = ?')->execute([$newActive, $keyId]);

    echo json_encode(['ok' => true, 'is_active' => $newActive]);
    exit;
}

// ── Freeze/Unfreeze key via reseller API ──
if ($action === 'freeze_key' || $action === 'unfreeze_key') {
    $keyId = (int)($input['key_id'] ?? 0);
    $resellerKeyId = (int)($input['reseller_key_id'] ?? 0);
    if (!$resellerKeyId) {
        // Try find reseller id from local key_value
        $stmt = $pdo->prepare('SELECT key_value FROM api_keys WHERE id = ?');
        $stmt->execute([$keyId]);
        $k = $stmt->fetch();
        if (!$k) { echo json_encode(['ok' => false, 'error' => 'Key not found']); exit; }
        // Get all keys from reseller and match
        $list = reseller_call('GET', '/keys');
        foreach (($list['data']['data']['keys'] ?? []) as $rk) {
            if ($rk['api_key'] === $k['key_value']) { $resellerKeyId = $rk['id']; break; }
        }
        if (!$resellerKeyId) { echo json_encode(['ok' => false, 'error' => 'Ключ не найден у reseller']); exit; }
    }

    $endpoint = $action === 'freeze_key' ? '/freeze' : '/unfreeze';
    $r = reseller_call('POST', '/keys/' . $resellerKeyId . $endpoint);

    if (!empty($r['data']['ok']) && $keyId) {
        $pdo->prepare('UPDATE api_keys SET is_active = ? WHERE id = ?')
            ->execute([$action === 'unfreeze_key' ? 1 : 0, $keyId]);
    }

    echo json_encode(['ok' => $r['data']['ok'] ?? false, 'error' => $r['data']['message'] ?? null]);
    exit;
}

// ── Refund key via reseller API ──
if ($action === 'refund_key') {
    $keyId = (int)($input['key_id'] ?? 0);
    $resellerKeyId = (int)($input['reseller_key_id'] ?? 0);
    if (!$resellerKeyId && $keyId) {
        $stmt = $pdo->prepare('SELECT key_value FROM api_keys WHERE id = ?');
        $stmt->execute([$keyId]);
        $k = $stmt->fetch();
        if ($k) {
            $list = reseller_call('GET', '/keys');
            foreach (($list['data']['data']['keys'] ?? []) as $rk) {
                if ($rk['api_key'] === $k['key_value']) { $resellerKeyId = $rk['id']; break; }
            }
        }
    }
    if (!$resellerKeyId) { echo json_encode(['ok' => false, 'error' => 'Reseller key not found']); exit; }

    $r = reseller_call('POST', '/keys/' . $resellerKeyId . '/refund');
    if (!empty($r['data']['ok']) && $keyId) {
        $pdo->prepare('DELETE FROM api_keys WHERE id = ?')->execute([$keyId]);
    }
    echo json_encode($r['data'] ?: ['ok' => false, 'error' => 'Refund failed']);
    exit;
}

// ── Delete key (local + reseller) ──
if ($action === 'delete_key') {
    $keyId = (int)($input['key_id'] ?? 0);
    if (!$keyId) { echo json_encode(['ok' => false, 'error' => 'key_id required']); exit; }

    // Get key_value for reseller lookup
    $stmt = $pdo->prepare('SELECT key_value FROM api_keys WHERE id = ?');
    $stmt->execute([$keyId]);
    $k = $stmt->fetch();

    if ($k) {
        $list = reseller_call('GET', '/keys');
        foreach (($list['data']['data']['keys'] ?? []) as $rk) {
            if ($rk['api_key'] === $k['key_value']) {
                reseller_call('DELETE', '/keys/' . $rk['id']);
                break;
            }
        }
    }

    $pdo->prepare('DELETE FROM chat_transactions WHERE api_key_id = ?')->execute([$keyId]);
    $pdo->prepare('DELETE FROM api_keys WHERE id = ?')->execute([$keyId]);

    echo json_encode(['ok' => true]);
    exit;
}

// ── All transactions ──
if ($action === 'transactions') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $filter = $_GET['filter'] ?? 'all';

    $rows = [];

    if ($filter === 'all' || $filter === 'chat') {
        $stmt = $pdo->prepare("SELECT ct.id, 'chat' as tx_type, ct.model, ct.prompt_tokens, ct.completion_tokens, ct.total_tokens, ct.status, ct.error_message, ct.created_at, ct.user_id, u.username, ak.label as key_label FROM chat_transactions ct LEFT JOIN users u ON u.id = ct.user_id LEFT JOIN api_keys ak ON ak.id = ct.api_key_id ORDER BY ct.created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        $rows = array_merge($rows, $stmt->fetchAll());
    }

    if ($filter === 'all' || $filter === 'financial') {
        $stmt = $pdo->prepare("SELECT t.id, 'financial' as tx_type, t.type, t.amount, t.description, t.created_at, t.user_id, u.username, ak.label as key_label FROM transactions t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN api_keys ak ON ak.id = t.api_key_id ORDER BY t.created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        $rows = array_merge($rows, $stmt->fetchAll());
    }

    usort($rows, function($a, $b) { return strcmp($b['created_at'], $a['created_at']); });

    echo json_encode(['ok' => true, 'transactions' => array_slice($rows, 0, $limit)]);
    exit;
}

// ── Purchase key for user via reseller API ──
if ($action === 'purchase_key') {
    $targetUserId = (int)($input['user_id'] ?? 0);
    $productId = (int)($input['product_id'] ?? 0);
    $label = trim($input['label'] ?? '');
    $asReserve = !empty($input['reserve']);

    if (!$productId) {
        echo json_encode(['ok' => false, 'error' => 'product_id required']);
        exit;
    }
    if (!$asReserve && !$targetUserId) {
        echo json_encode(['ok' => false, 'error' => 'user_id required (or pass reserve=true)']);
        exit;
    }

    if ($targetUserId) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$targetUserId]);
        if (!$stmt->fetch()) { echo json_encode(['ok' => false, 'error' => 'User not found']); exit; }
    }

    $r = reseller_call('POST', '/keys/purchase', ['product_id' => $productId]);
    if (empty($r['data']['ok'])) {
        echo json_encode(['ok' => false, 'error' => $r['data']['message'] ?? $r['data']['error'] ?? 'Reseller error', 'code' => $r['code']]);
        exit;
    }

    $d = $r['data']['data'];
    $apiKey = $d['api_key'];
    $tokens = $d['tokens'];
    $price = $d['price'];
    $product = $d['product'];

    $tag = $asReserve ? 'reserve' : 'occupied';
    $stmt = $pdo->prepare('INSERT INTO api_keys (user_id, key_value, label, tag, tokens_limit, source) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$targetUserId ?: null, $apiKey, $label ?: $product, $tag, $tokens, 'admin_purchase']);

    echo json_encode(['ok' => true, 'key' => $apiKey, 'product' => $product, 'tokens' => $tokens, 'price' => $price, 'new_balance' => $d['new_balance'], 'tag' => $tag]);
    exit;
}

// ── Create test key ──
if ($action === 'test_key') {
    $targetUserId = (int)($input['user_id'] ?? 0);
    $asReserve = !$targetUserId;

    if ($targetUserId) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$targetUserId]);
        if (!$stmt->fetch()) { echo json_encode(['ok' => false, 'error' => 'User not found']); exit; }
    }

    $r = reseller_call('POST', '/keys/test');
    if (empty($r['data']['ok'])) {
        echo json_encode(['ok' => false, 'error' => $r['data']['message'] ?? $r['data']['error'] ?? 'Reseller error', 'code' => $r['code']]);
        exit;
    }
    $d = $r['data']['data'];
    $tag = $asReserve ? 'reserve' : 'occupied';
    $stmt = $pdo->prepare('INSERT INTO api_keys (user_id, key_value, label, tag, tokens_limit, source) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$targetUserId ?: null, $d['api_key'], 'Test', $tag, $d['tokens'], 'admin_test']);

    echo json_encode(['ok' => true, 'key' => $d['api_key'], 'tokens' => $d['tokens'], 'product' => 'Test (1M)', 'price' => 0, 'tag' => $tag, 'new_balance' => $d['new_balance'] ?? null]);
    exit;
}

// ── Topup existing key ──
if ($action === 'topup_key') {
    $keyId = (int)($input['key_id'] ?? 0);
    $productId = (int)($input['product_id'] ?? 0);
    if (!$keyId || !$productId) { echo json_encode(['ok' => false, 'error' => 'key_id и product_id required']); exit; }

    $stmt = $pdo->prepare('SELECT key_value FROM api_keys WHERE id = ?');
    $stmt->execute([$keyId]);
    $k = $stmt->fetch();
    if (!$k) { echo json_encode(['ok' => false, 'error' => 'Key not found']); exit; }

    $list = reseller_call('GET', '/keys');
    $resellerKeyId = 0;
    foreach (($list['data']['data']['keys'] ?? []) as $rk) {
        if ($rk['api_key'] === $k['key_value']) { $resellerKeyId = $rk['id']; break; }
    }
    if (!$resellerKeyId) { echo json_encode(['ok' => false, 'error' => 'Reseller key not found']); exit; }

    $r = reseller_call('POST', '/keys/' . $resellerKeyId . '/topup', ['product_id' => $productId]);
    if (empty($r['data']['ok'])) {
        echo json_encode(['ok' => false, 'error' => $r['data']['message'] ?? 'Topup error']);
        exit;
    }
    $d = $r['data']['data'];
    $pdo->prepare('UPDATE api_keys SET tokens_limit = ? WHERE id = ?')->execute([$d['new_max_tokens'], $keyId]);

    echo json_encode(['ok' => true, 'new_max_tokens' => $d['new_max_tokens'], 'price' => $d['price']]);
    exit;
}

// ── Topup key via Kiro proxy (direct token amount) ──
if ($action === 'topup_key_tokens') {
    $keyId  = (int)($input['key_id'] ?? 0);
    $tokens = (int)($input['tokens'] ?? 0);
    if (!$keyId || $tokens < 1) { echo json_encode(['ok' => false, 'error' => 'key_id и tokens required']); exit; }

    $stmt = $pdo->prepare('SELECT key_value, source FROM api_keys WHERE id = ?');
    $stmt->execute([$keyId]);
    $k = $stmt->fetch();
    if (!$k) { echo json_encode(['ok' => false, 'error' => 'Key not found']); exit; }

    // Topup on Kiro proxy
    $r = kiro_request('POST', '/admin/keys/topup-tokens', [
        'key'    => $k['key_value'],
        'amount' => $tokens,
    ]);
    if ($r['code'] !== 200 || empty($r['data'])) {
        $err = $r['data']['detail'] ?? $r['data']['error'] ?? ('HTTP ' . $r['code']);
        echo json_encode(['ok' => false, 'error' => 'Kiro: ' . (is_string($err) ? $err : json_encode($err))]);
        exit;
    }

    // Update local DB — increment tokens_limit
    $pdo->prepare('UPDATE api_keys SET tokens_limit = tokens_limit + ? WHERE id = ?')->execute([$tokens, $keyId]);

    // Read updated value
    $stmt = $pdo->prepare('SELECT tokens_limit FROM api_keys WHERE id = ?');
    $stmt->execute([$keyId]);
    $updated = $stmt->fetch();

    echo json_encode(['ok' => true, 'added' => $tokens, 'new_tokens_limit' => (int)$updated['tokens_limit']]);
    exit;
}

// ── Product management ──
if ($action === 'products_list') {
    require_once __DIR__ . '/products_loader.php';
    $products = load_products($pdo, false);
    echo json_encode(['ok' => true, 'products' => $products]);
    exit;
}

if ($action === 'product_save') {
    $rid = (int)($input['reseller_id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));
    $desc = trim((string)($input['description'] ?? ''));
    $price = (float)($input['price_rub'] ?? 0);
    $sort = (int)($input['sort_order'] ?? 0);
    $visible = (int)!empty($input['is_visible']);
    $stockCount = isset($input['stock_count']) ? max(0, (int)$input['stock_count']) : null;

    if (!$rid || !$name || $price <= 0) {
        echo json_encode(['ok' => false, 'error' => 'reseller_id, name, price_rub required']);
        exit;
    }

    if ($stockCount === null) {
        // Preserve existing stock if not sent
        $cur = $pdo->prepare('SELECT stock_count FROM product_overrides WHERE reseller_id = ?');
        $cur->execute([$rid]);
        $row = $cur->fetch();
        $stockCount = $row ? (int)$row['stock_count'] : 0;
    }

    $pdo->prepare("INSERT INTO product_overrides (reseller_id, name, description, price_rub, sort_order, is_visible, stock_count)
                   VALUES (?, ?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description),
                   price_rub=VALUES(price_rub), sort_order=VALUES(sort_order), is_visible=VALUES(is_visible), stock_count=VALUES(stock_count)")
        ->execute([$rid, $name, $desc, $price, $sort, $visible, $stockCount]);

    echo json_encode(['ok' => true, 'stock_count' => $stockCount]);
    exit;
}

// ── Adjust stock (+N / -N / set) ──
if ($action === 'product_stock_adjust') {
    $rid = (int)($input['reseller_id'] ?? 0);
    $delta = (int)($input['delta'] ?? 0);
    $set = isset($input['set']) ? max(0, (int)$input['set']) : null;
    if (!$rid) { echo json_encode(['ok' => false, 'error' => 'reseller_id required']); exit; }

    // Ensure override exists (auto-create if missing using reseller defaults)
    $cur = $pdo->prepare('SELECT * FROM product_overrides WHERE reseller_id = ?');
    $cur->execute([$rid]);
    $row = $cur->fetch();

    if (!$row) {
        require_once __DIR__ . '/products_loader.php';
        $p = load_product_by_id($pdo, $rid);
        if (!$p) { echo json_encode(['ok' => false, 'error' => 'Product not found']); exit; }
        $pdo->prepare('INSERT INTO product_overrides (reseller_id, name, description, price_rub, sort_order, is_visible, stock_count) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$rid, $p['name'], $p['description'], $p['price_rub'], $p['sort_order'], $p['is_visible'], 0]);
        $row = ['stock_count' => 0];
    }

    $newStock = $set !== null ? $set : max(0, (int)$row['stock_count'] + $delta);
    $pdo->prepare('UPDATE product_overrides SET stock_count = ? WHERE reseller_id = ?')->execute([$newStock, $rid]);

    echo json_encode(['ok' => true, 'stock_count' => $newStock]);
    exit;
}

if ($action === 'product_delete_override') {
    $rid = (int)($input['reseller_id'] ?? 0);
    if (!$rid) { echo json_encode(['ok' => false, 'error' => 'reseller_id required']); exit; }
    $pdo->prepare('DELETE FROM product_overrides WHERE reseller_id = ?')->execute([$rid]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Per-user custom prices ──
if ($action === 'user_prices_list') {
    $uid = (int)($_GET['user_id'] ?? $input['user_id'] ?? 0);
    if (!$uid) { echo json_encode(['ok' => false, 'error' => 'user_id required']); exit; }
    require_once __DIR__ . '/products_loader.php';
    require_once __DIR__ . '/cbr_rate.php';

    // Build product map (id => default product info)
    $catalog = [];
    foreach (load_products($pdo, false) as $p) {
        $catalog[(int)$p['id']] = $p;
    }

    $stmt = $pdo->prepare("SELECT id, reseller_id, price_value, price_currency, note, created_at, updated_at
                           FROM user_product_prices WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $rate = get_usd_rub_rate();
    $rateValue = (float)($rate['rate'] ?? 0);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $rid = (int)$row['reseller_id'];
        $val = (float)$row['price_value'];
        $cur = $row['price_currency'] === 'USD' ? 'USD' : 'RUB';
        $rub = $cur === 'USD' ? round($val * $rateValue, 2) : round($val, 2);
        $items[] = [
            'id' => (int)$row['id'],
            'reseller_id' => $rid,
            'product_name' => $catalog[$rid]['name'] ?? ('#' . $rid),
            'product_tokens' => $catalog[$rid]['tokens'] ?? null,
            'default_price_rub' => $catalog[$rid]['price_rub'] ?? null,
            'price_value' => $val,
            'price_currency' => $cur,
            'price_rub_effective' => $rub,
            'note' => $row['note'],
            'updated_at' => $row['updated_at'],
        ];
    }

    // Also return full product catalog for the "add" dropdown
    $catalogList = array_map(function($p) {
        return ['id' => (int)$p['id'], 'name' => $p['name'], 'tokens' => (int)$p['tokens'], 'default_price_rub' => (float)$p['price_rub']];
    }, array_values($catalog));

    echo json_encode(['ok' => true, 'items' => $items, 'catalog' => $catalogList, 'rate' => $rate]);
    exit;
}

if ($action === 'user_price_save') {
    $uid = (int)($input['user_id'] ?? 0);
    $rid = (int)($input['reseller_id'] ?? 0);
    $val = (float)($input['price_value'] ?? 0);
    $cur = strtoupper(trim((string)($input['price_currency'] ?? 'RUB')));
    $note = trim((string)($input['note'] ?? ''));
    if (!in_array($cur, ['RUB', 'USD'], true)) $cur = 'RUB';
    if (!$uid || !$rid || $val <= 0) {
        echo json_encode(['ok' => false, 'error' => 'user_id, reseller_id, price_value > 0 required']);
        exit;
    }

    // Validate user exists
    $u = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $u->execute([$uid]);
    if (!$u->fetch()) { echo json_encode(['ok' => false, 'error' => 'User not found']); exit; }

    // Validate product exists
    require_once __DIR__ . '/products_loader.php';
    if (!load_product_by_id($pdo, $rid)) {
        echo json_encode(['ok' => false, 'error' => 'Product not found']);
        exit;
    }

    $pdo->prepare("INSERT INTO user_product_prices (user_id, reseller_id, price_value, price_currency, note)
                   VALUES (?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE price_value=VALUES(price_value), price_currency=VALUES(price_currency), note=VALUES(note)")
        ->execute([$uid, $rid, $val, $cur, $note ?: null]);

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'user_price_delete') {
    $id = (int)($input['id'] ?? 0);
    $uid = (int)($input['user_id'] ?? 0);
    if (!$id || !$uid) { echo json_encode(['ok' => false, 'error' => 'id and user_id required']); exit; }
    $pdo->prepare('DELETE FROM user_product_prices WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Chat sessions list ──
if ($action === 'chat_sessions') {
    $page = max(1, (int)($input['page'] ?? $_GET['page'] ?? 1));
    $search = trim($input['search'] ?? $_GET['search'] ?? '');
    $filterUserId = (int)($input['user_id'] ?? $_GET['user_id'] ?? 0);
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(cs.title LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filterUserId > 0) {
        $where[] = 'cs.user_id = ?';
        $params[] = $filterUserId;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM chat_sessions cs LEFT JOIN users u ON u.id = cs.user_id $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT cs.id, cs.user_id, cs.title, cs.mode, cs.models, cs.system_prompt, cs.temperature, cs.max_tokens, cs.created_at, cs.updated_at, u.username, u.email,
        (SELECT COUNT(*) FROM chat_messages WHERE session_id = cs.id) as msg_count
        FROM chat_sessions cs LEFT JOIN users u ON u.id = cs.user_id
        $whereSql ORDER BY cs.updated_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'sessions' => $sessions, 'total' => $total, 'page' => $page, 'pages' => ceil($total / $limit)]);
    exit;
}

// ── Chat messages for a session ──
if ($action === 'chat_messages') {
    $sessionId = (int)($input['session_id'] ?? $_GET['session_id'] ?? 0);
    if (!$sessionId) { echo json_encode(['ok' => false, 'error' => 'session_id required']); exit; }

    $stmt = $pdo->prepare("SELECT id, role, model, content, tokens, error_message, turn_index, created_at FROM chat_messages WHERE session_id = ? ORDER BY turn_index ASC, created_at ASC");
    $stmt->execute([$sessionId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also get session info
    $sStmt = $pdo->prepare("SELECT cs.*, u.username FROM chat_sessions cs LEFT JOIN users u ON u.id = cs.user_id WHERE cs.id = ?");
    $sStmt->execute([$sessionId]);
    $session = $sStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'messages' => $messages, 'session' => $session]);
    exit;
}

if ($action === 'charts_data') {
    $days = max(7, min(90, (int)($_GET['days'] ?? 7)));
    
    // Generate date labels
    $labels = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $labels[] = date('Y-m-d', strtotime("-{$i} days"));
    }
    
    // New users per day
    $stmt = $pdo->prepare("SELECT DATE(created_at) as d, COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY d");
    $stmt->execute([$days]);
    $usersMap = [];
    while ($r = $stmt->fetch()) $usersMap[$r['d']] = (int)$r['c'];
    
    // API requests per day
    $stmt = $pdo->prepare("SELECT DATE(created_at) as d, COUNT(*) as c FROM chat_transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY d");
    $stmt->execute([$days]);
    $reqsMap = [];
    while ($r = $stmt->fetch()) $reqsMap[$r['d']] = (int)$r['c'];
    
    // Revenue per day (positive amounts only)
    $stmt = $pdo->prepare("SELECT DATE(created_at) as d, COALESCE(SUM(amount),0) as s FROM transactions WHERE amount > 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY d");
    $stmt->execute([$days]);
    $revMap = [];
    while ($r = $stmt->fetch()) $revMap[$r['d']] = round((float)$r['s'], 2);
    
    // Build arrays aligned to labels
    $users = []; $reqs = []; $rev = [];
    foreach ($labels as $date) {
        $users[] = $usersMap[$date] ?? 0;
        $reqs[] = $reqsMap[$date] ?? 0;
        $rev[] = $revMap[$date] ?? 0;
    }
    
    echo json_encode(['ok' => true, 'labels' => $labels, 'users' => $users, 'requests' => $reqs, 'revenue' => $rev]);
    exit;
}

// ── Schema for broadcasts (idempotent, used by both actions and the worker) ──
function broadcast_ensure_tables(\PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS broadcasts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        channel VARCHAR(20) DEFAULT 'all',
        status VARCHAR(20) DEFAULT 'pending',
        recipients_count INT DEFAULT 0,
        site_sent INT DEFAULT 0,
        tg_sent INT DEFAULT 0,
        tg_failed INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_b_status (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Migrations for old broadcasts table without status/site_sent/completed_at
    try { $pdo->exec("ALTER TABLE broadcasts ADD COLUMN status VARCHAR(20) DEFAULT 'pending'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE broadcasts ADD COLUMN site_sent INT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE broadcasts ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE broadcasts ADD INDEX idx_b_status (status, created_at)"); } catch (Exception $e) {}
    // Mark legacy rows as 'done' so the worker doesn't try to reprocess them
    try { $pdo->exec("UPDATE broadcasts SET status = 'done' WHERE status IS NULL OR status = ''"); } catch (Exception $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS broadcast_recipients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        broadcast_id INT NOT NULL,
        user_id INT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        attempts TINYINT DEFAULT 0,
        last_error TEXT DEFAULT NULL,
        site_sent TINYINT(1) DEFAULT 0,
        tg_sent TINYINT(1) DEFAULT 0,
        processed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_br_status (broadcast_id, status),
        INDEX idx_br_pending (status, attempts),
        UNIQUE KEY uniq_br (broadcast_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if ($action === 'broadcast_send') {
    $title = trim($input['title'] ?? '');
    $message = trim($input['message'] ?? '');
    $channel = $input['channel'] ?? 'all';

    if (!$title || !$message) {
        echo json_encode(['ok' => false, 'error' => 'Заголовок и сообщение обязательны']);
        exit;
    }
    if (!in_array($channel, ['all', 'telegram', 'site'], true)) $channel = 'all';

    broadcast_ensure_tables($pdo);

    $pdo->beginTransaction();
    try {
        // Snapshot recipient set under a transaction so concurrent signups
        // during enqueue aren't half-counted.
        $stmt = $pdo->prepare("INSERT INTO broadcasts (title, message, channel, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$title, $message, $channel]);
        $broadcastId = (int)$pdo->lastInsertId();

        // Enqueue: SELECT id (and only telegram_id if channel=telegram) and INSERT into broadcast_recipients.
        // For channel=telegram we restrict to users with a telegram_id; for site/all we enqueue everyone.
        if ($channel === 'telegram') {
            $sql = "INSERT INTO broadcast_recipients (broadcast_id, user_id)
                    SELECT ?, id FROM users WHERE telegram_id IS NOT NULL AND telegram_id <> 0";
        } else {
            $sql = "INSERT INTO broadcast_recipients (broadcast_id, user_id)
                    SELECT ?, id FROM users";
        }
        $ins = $pdo->prepare($sql);
        $ins->execute([$broadcastId]);
        $count = $ins->rowCount();

        $pdo->prepare("UPDATE broadcasts SET recipients_count = ? WHERE id = ?")
            ->execute([$count, $broadcastId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('broadcast_send enqueue failed: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Не удалось поставить рассылку в очередь']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'broadcast_id' => $broadcastId,
        'recipients' => $count,
        'queued' => true,
        'message' => 'Рассылка поставлена в очередь и будет отправлена воркером.',
    ]);
    exit;
}

if ($action === 'broadcasts_list') {
    broadcast_ensure_tables($pdo);

    $rows = $pdo->query(
        "SELECT id, title, message, channel, status, recipients_count, site_sent, tg_sent, tg_failed, created_at, completed_at
         FROM broadcasts ORDER BY created_at DESC LIMIT 30"
    )->fetchAll();
    echo json_encode(['ok' => true, 'broadcasts' => $rows]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════
// Kiro API Management
// ══════════════════════════════════════════════════════════════════════

if (!defined('KIRO_API_URL'))   define('KIRO_API_URL', defined('KIRO_API_URL_OVERRIDE') ? KIRO_API_URL_OVERRIDE : 'https://api.scarlex.ru');
if (!defined('KIRO_ADMIN_KEY')) define('KIRO_ADMIN_KEY', ''); // real value lives in secrets.php

function kiro_request(string $method, string $path, ?array $body = null): array {
    $url = KIRO_API_URL . $path;
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . KIRO_ADMIN_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($resp, true), 'raw' => $resp, 'curl_error' => $err];
}

// ── Kiro: generic proxy (used by kiro.html JS so admin token never leaves shared hosting) ──
if ($action === 'kiro_proxy') {
    $method = strtoupper((string)($input['method'] ?? 'GET'));
    $path   = (string)($input['path'] ?? '');
    $body   = $input['body'] ?? null;
    // Only allow safe methods & path prefixes
    if (!in_array($method, ['GET','POST','PUT','DELETE'], true)) {
        echo json_encode(['ok' => false, 'error' => 'method not allowed']);
        exit;
    }
    if ($path === '' || $path[0] !== '/') {
        echo json_encode(['ok' => false, 'error' => 'bad path']);
        exit;
    }
    // Strip query for prefix check, then re-attach
    $pathOnly = strtok($path, '?');
    $allowed = ['/status','/admin/','/health'];
    $ok = false;
    foreach ($allowed as $prefix) {
        if (strncmp($pathOnly, $prefix, strlen($prefix)) === 0) { $ok = true; break; }
    }
    if (!$ok) {
        echo json_encode(['ok' => false, 'error' => 'path not allowed']);
        exit;
    }
    $r = kiro_request($method, $path, $body);
    // Return raw VPS response transparently so kiro.html JS sees what it expects
    http_response_code($r['code'] ?: 502);
    echo $r['raw'] ?: json_encode(['ok' => false, 'error' => 'no response: ' . $r['curl_error']]);
    exit;
}

// ── Kiro: VPS status ──
if ($action === 'kiro_status') {
    $r = kiro_request('GET', '/status');
    if ($r['code'] !== 200) {
        echo json_encode(['ok' => false, 'error' => 'Kiro VPS недоступен: HTTP ' . $r['code'] . ' ' . $r['curl_error']]);
        exit;
    }
    echo json_encode(['ok' => true, 'status' => $r['data']]);
    exit;
}

// ── Kiro: list API keys ──
if ($action === 'kiro_keys') {
    $r = kiro_request('GET', '/admin/keys');
    if ($r['code'] !== 200) {
        echo json_encode(['ok' => false, 'error' => 'Ошибка: HTTP ' . $r['code']]);
        exit;
    }
    echo json_encode(['ok' => true, 'keys' => $r['data']['keys'] ?? []]);
    exit;
}

// ── Kiro: add key ──
if ($action === 'kiro_add_key') {
    $key = trim($input['key'] ?? '');
    $name = trim($input['name'] ?? '');
    $rpm = (int)($input['rpm'] ?? 60);

    if (!$key || !$name) {
        echo json_encode(['ok' => false, 'error' => 'key и name обязательны']);
        exit;
    }

    $r = kiro_request('POST', '/admin/keys', [
        'key' => $key,
        'name' => $name,
        'rpm' => $rpm,
    ]);
    if ($r['code'] !== 200) {
        echo json_encode(['ok' => false, 'error' => $r['data']['detail'] ?? 'Ошибка: HTTP ' . $r['code']]);
        exit;
    }
    echo json_encode(['ok' => true, 'result' => $r['data']]);
    exit;
}

// ── Kiro: update key (rpm, name, enabled) ──
if ($action === 'kiro_update_key') {
    $key = trim($input['key'] ?? '');
    if (!$key) {
        echo json_encode(['ok' => false, 'error' => 'key обязателен']);
        exit;
    }

    $body = ['key' => $key];
    if (isset($input['name'])) $body['name'] = $input['name'];
    if (isset($input['rpm'])) $body['rpm'] = (int)$input['rpm'];
    if (isset($input['enabled'])) $body['enabled'] = (bool)$input['enabled'];

    $r = kiro_request('PUT', '/admin/keys', $body);
    if ($r['code'] !== 200) {
        echo json_encode(['ok' => false, 'error' => $r['data']['detail'] ?? 'Ошибка: HTTP ' . $r['code']]);
        exit;
    }
    echo json_encode(['ok' => true, 'result' => $r['data']]);
    exit;
}

// ── Kiro: delete key ──
if ($action === 'kiro_delete_key') {
    $key = trim($input['key'] ?? '');
    if (!$key) {
        echo json_encode(['ok' => false, 'error' => 'key обязателен']);
        exit;
    }

    $r = kiro_request('DELETE', '/admin/keys/' . urlencode($key));
    if ($r['code'] !== 200) {
        echo json_encode(['ok' => false, 'error' => $r['data']['detail'] ?? 'Ошибка: HTTP ' . $r['code']]);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── Kiro: health ──
if ($action === 'kiro_health') {
    $r = kiro_request('GET', '/health');
    echo json_encode(['ok' => $r['code'] === 200, 'health' => $r['data']]);
    exit;
}

// ── Kiro: billing summary ──
if ($action === 'kiro_billing') {
    $r = kiro_request('GET', '/admin/billing');
    echo json_encode(['ok' => $r['code'] === 200, 'billing' => $r['data']]);
    exit;
}

// ── Kiro: resolve key by preview ──
if ($action === 'kiro_resolve_key') {
    $preview = $_POST['preview'] ?? '';
    if (!$preview) { echo json_encode(['ok' => false, 'error' => 'Missing preview']); exit; }
    $r = kiro_request('GET', '/admin/key-resolve/' . urlencode($preview));
    echo json_encode(['ok' => $r['code'] === 200, 'data' => $r['data']]);
    exit;
}

// ── Kiro: per-key logs ──
if ($action === 'kiro_key_logs') {
    $preview = $_POST['preview'] ?? '';
    $limit = intval($_POST['limit'] ?? 50);
    if (!$preview) { echo json_encode(['ok' => false, 'error' => 'Missing preview']); exit; }
    $r = kiro_request('GET', '/admin/logs/key/' . urlencode($preview) . '?limit=' . $limit);
    echo json_encode(['ok' => $r['code'] === 200, 'data' => $r['data']]);
    exit;
}

// ── Kiro: all logs ──
if ($action === 'kiro_logs') {
    $limit = intval($_POST['limit'] ?? 100);
    $r = kiro_request('GET', '/admin/logs?limit=' . $limit);
    echo json_encode(['ok' => $r['code'] === 200, 'data' => $r['data']]);
    exit;
}

// ── Kiro: accounts status ──
if ($action === 'kiro_accounts') {
    $r = kiro_request('GET', '/admin/accounts');
    echo json_encode(['ok' => $r['code'] === 200, 'data' => $r['data']]);
    exit;
}

// ── Kiro: server status ──
if ($action === 'kiro_status') {
    $r = kiro_request('GET', '/status');
    echo json_encode(['ok' => $r['code'] === 200, 'data' => $r['data']]);
    exit;
}

// ════════════════════════════════════════════════════════════════
// Scarlex products (tariffs) — own DB
// ════════════════════════════════════════════════════════════════
if ($action === 'scarlex_products_list') {
    $rows = $pdo->query("SELECT * FROM products ORDER BY sort_order ASC, id ASC")->fetchAll();
    foreach ($rows as &$r) {
        $tk = (int)$r['tokens'];
        $r['price_per_1m'] = $tk > 0 ? round((float)$r['price_rub'] / ($tk / 1000000.0), 2) : 0;
    }
    echo json_encode(['ok' => true, 'products' => $rows]);
    exit;
}

if ($action === 'scarlex_product_save') {
    $id           = (int)($input['id'] ?? 0);
    $slug         = trim((string)($input['slug'] ?? ''));
    $name         = trim((string)($input['name'] ?? ''));
    $tokens       = (int)($input['tokens'] ?? 0);
    $price        = (float)($input['price_rub'] ?? 0);
    $rpm          = (int)($input['rpm'] ?? 60);
    $description  = (string)($input['description'] ?? '');
    $badge        = trim((string)($input['badge'] ?? '')) ?: null;
    $sort         = (int)($input['sort_order'] ?? 0);
    $visible      = !empty($input['is_visible']) ? 1 : 0;
    $trial        = !empty($input['is_trial']) ? 1 : 0;
    $allow_custom = !empty($input['allow_custom']) ? 1 : 0;
    $minc         = (int)($input['min_custom_tokens'] ?? 1000000);
    $maxc         = (int)($input['max_custom_tokens'] ?? 500000000);

    if (!$slug || !$name || $tokens <= 0) {
        echo json_encode(['ok' => false, 'error' => 'slug, name, tokens обязательны']); exit;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE products SET slug=?, name=?, tokens=?, price_rub=?, rpm=?, description=?, badge=?, sort_order=?, is_visible=?, is_trial=?, allow_custom=?, min_custom_tokens=?, max_custom_tokens=? WHERE id=?");
        $stmt->execute([$slug,$name,$tokens,$price,$rpm,$description,$badge,$sort,$visible,$trial,$allow_custom,$minc,$maxc,$id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (slug, name, tokens, price_rub, rpm, description, badge, sort_order, is_visible, is_trial, allow_custom, min_custom_tokens, max_custom_tokens) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$slug,$name,$tokens,$price,$rpm,$description,$badge,$sort,$visible,$trial,$allow_custom,$minc,$maxc]);
        $id = (int)$pdo->lastInsertId();
    }
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

if ($action === 'scarlex_product_delete') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'id required']); exit; }
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'scarlex_product_reorder') {
    $items = $input['items'] ?? [];
    if (!is_array($items)) { echo json_encode(['ok' => false, 'error' => 'items required']); exit; }
    $stmt = $pdo->prepare("UPDATE products SET sort_order = ? WHERE id = ?");
    foreach ($items as $it) {
        $stmt->execute([(int)$it['sort_order'], (int)$it['id']]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ════════════════════════════════════════════════════════════════
// Support tickets — admin side
// ════════════════════════════════════════════════════════════════
if ($action === 'tickets_list') {
    $status = trim((string)($_GET['status'] ?? $input['status'] ?? ''));
    $sql = "SELECT t.*, u.email, u.username,
              (SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id = t.id) AS msg_count
            FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id";
    $params = [];
    if (in_array($status, ['open','answered','closed'], true)) {
        $sql .= " WHERE t.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY (t.status='open') DESC, t.updated_at DESC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['ok' => true, 'tickets' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'ticket_get') {
    $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
    $t = $pdo->prepare("SELECT t.*, u.email, u.username FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?");
    $t->execute([$id]);
    $ticket = $t->fetch();
    if (!$ticket) { echo json_encode(['ok' => false, 'error' => 'not found']); exit; }
    $m = $pdo->prepare("SELECT sender, body, created_at FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $m->execute([$id]);
    echo json_encode(['ok' => true, 'ticket' => $ticket, 'messages' => $m->fetchAll()]);
    exit;
}

if ($action === 'ticket_reply') {
    $id   = (int)($input['ticket_id'] ?? 0);
    $body = trim((string)($input['body'] ?? ''));
    if (!$id || $body === '') { echo json_encode(['ok' => false, 'error' => 'Заполните сообщение']); exit; }
    $pdo->prepare("INSERT INTO support_messages (ticket_id, sender, body) VALUES (?, 'admin', ?)")
        ->execute([$id, $body]);
    $pdo->prepare("UPDATE support_tickets SET status='answered', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'ticket_close') {
    $id = (int)($input['ticket_id'] ?? 0);
    $pdo->prepare("UPDATE support_tickets SET status='closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── News (admin posts → dashboard) ──
require_once __DIR__ . '/news_sanitizer.php';

if ($action === 'news_list') {
    $rows = $pdo->query("SELECT id, title, body, kind, is_published, is_pinned, created_at, updated_at
                         FROM news_posts ORDER BY is_pinned DESC, created_at DESC")->fetchAll();
    echo json_encode(['ok' => true, 'posts' => $rows]);
    exit;
}

if ($action === 'news_save') {
    $id      = (int)($input['id'] ?? 0);
    $title   = trim((string)($input['title'] ?? ''));
    $body    = (string)($input['body'] ?? '');
    $kind    = (string)($input['kind'] ?? 'news');
    $pinned  = !empty($input['is_pinned']) ? 1 : 0;
    $pub     = isset($input['is_published']) ? (int)!!$input['is_published'] : 1;
    if ($title === '' || $body === '') { echo json_encode(['ok'=>false, 'error'=>'title и body обязательны']); exit; }
    if (!in_array($kind, ['news','update','maintenance','promo'], true)) $kind = 'news';
    $body = news_sanitize_html($body);
    if ($id > 0) {
        $pdo->prepare("UPDATE news_posts SET title=?, body=?, kind=?, is_published=?, is_pinned=? WHERE id=?")
            ->execute([$title, $body, $kind, $pub, $pinned, $id]);
    } else {
        $pdo->prepare("INSERT INTO news_posts (title, body, kind, is_published, is_pinned) VALUES (?,?,?,?,?)")
            ->execute([$title, $body, $kind, $pub, $pinned]);
        $id = (int)$pdo->lastInsertId();
    }
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

if ($action === 'news_delete') {
    $id = (int)($input['id'] ?? 0);
    $pdo->prepare("DELETE FROM news_posts WHERE id=?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Assign a brand-new Kiro key to a user ──
// Creates the key on api.scarlex.ru/admin/keys, then INSERTs into local api_keys
// (source='scarlex_proxy') linked to the chosen user.
if ($action === 'assign_kiro_key') {
    $targetUserId = (int)($input['user_id'] ?? 0);
    $name         = trim((string)($input['name'] ?? ''));
    $providedKey  = trim((string)($input['key'] ?? ''));
    $rpm          = max(0, (int)($input['rpm'] ?? 60));
    $creditsLimit = max(0, (int)($input['credits_limit'] ?? 0));
    $allowed      = is_array($input['allowed_models'] ?? null) ? array_values(array_filter($input['allowed_models'], 'is_string')) : null;
    $hardLimit    = isset($input['hard_limit']) ? (bool)$input['hard_limit'] : true;

    if (!$targetUserId) { echo json_encode(['ok' => false, 'error' => 'user_id required']); exit; }
    if ($name === '')   { echo json_encode(['ok' => false, 'error' => 'name required']); exit; }

    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
    $stmt->execute([$targetUserId]);
    $usr = $stmt->fetch();
    if (!$usr) { echo json_encode(['ok' => false, 'error' => 'User not found']); exit; }

    // Auto-generate key if not provided: sk-scarlex-<32 hex>
    $key = $providedKey !== '' ? $providedKey : ('sk-scarlex-' . bin2hex(random_bytes(16)));
    if (strlen($key) < 20 || strlen($key) > 255) {
        echo json_encode(['ok' => false, 'error' => 'Bad key length']);
        exit;
    }

    // Ensure the key isn't already in our local DB
    $st = $pdo->prepare('SELECT id FROM api_keys WHERE key_value = ?');
    $st->execute([$key]);
    if ($st->fetch()) { echo json_encode(['ok' => false, 'error' => 'Такой ключ уже есть в БД']); exit; }

    $payload = [
        'key'           => $key,
        'name'          => $name,
        'rpm'           => $rpm,
        'credits_limit' => $creditsLimit,
        'hard_limit'    => $hardLimit,
    ];
    if ($allowed !== null) $payload['allowed_models'] = $allowed;

    $r = kiro_request('POST', '/admin/keys', $payload);
    if ($r['code'] !== 200 || empty($r['data'])) {
        $err = $r['data']['detail'] ?? $r['data']['error'] ?? ('HTTP ' . $r['code'] . ' ' . $r['curl_error']);
        echo json_encode(['ok' => false, 'error' => 'Kiro: ' . (is_string($err) ? $err : json_encode($err))]);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO api_keys (user_id, key_value, label, tag, tokens_limit, used_tokens, source) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$targetUserId, $key, $name, 'occupied', 0, 0, 'scarlex_proxy']);
    $localId = (int)$pdo->lastInsertId();

    echo json_encode([
        'ok'    => true,
        'id'    => $localId,
        'key'   => $key,
        'name'  => $name,
        'rpm'   => $rpm,
        'credits_limit' => $creditsLimit,
        'allowed_models' => $allowed,
        'hard_limit' => $hardLimit,
    ]);
    exit;
}

// ── Incidents CRUD (admin only) ─────────────────────────────────────
if ($action === 'incident_create') {
    $title = trim((string)($input['title'] ?? ''));
    $desc  = trim((string)($input['description'] ?? ''));
    $sev   = $input['severity'] ?? 'minor';
    $stat  = $input['status'] ?? 'investigating';
    $comp  = trim((string)($input['component'] ?? 'api'));
    if (!$title) { echo json_encode(['ok' => false, 'error' => 'title required']); exit; }
    if (!in_array($sev, ['minor','major','critical'], true))   { echo json_encode(['ok' => false, 'error' => 'bad severity']); exit; }
    if (!in_array($stat, ['investigating','identified','monitoring','resolved'], true)) { echo json_encode(['ok' => false, 'error' => 'bad status']); exit; }

    $resolvedAt = ($stat === 'resolved') ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare(
        'INSERT INTO incidents (title, description, severity, status, component, resolved_at, created_by) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $desc ?: null, $sev, $stat, $comp ?: 'api', $resolvedAt, $userId]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($action === 'incident_update') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false, 'error' => 'id required']); exit; }

    $sets = [];
    $vals = [];
    if (isset($input['title']))       { $sets[] = 'title = ?';       $vals[] = trim((string)$input['title']); }
    if (isset($input['description'])) { $sets[] = 'description = ?'; $vals[] = trim((string)$input['description']) ?: null; }
    if (isset($input['severity']) && in_array($input['severity'], ['minor','major','critical'], true)) {
        $sets[] = 'severity = ?'; $vals[] = $input['severity'];
    }
    if (isset($input['status']) && in_array($input['status'], ['investigating','identified','monitoring','resolved'], true)) {
        $sets[] = 'status = ?'; $vals[] = $input['status'];
        // Auto-set resolved_at when moving to resolved (or clear if reopened)
        if ($input['status'] === 'resolved') {
            $sets[] = 'resolved_at = COALESCE(resolved_at, NOW())';
        } else {
            $sets[] = 'resolved_at = NULL';
        }
    }
    if (isset($input['component'])) { $sets[] = 'component = ?'; $vals[] = trim((string)$input['component']) ?: 'api'; }

    if (!$sets) { echo json_encode(['ok' => false, 'error' => 'nothing to update']); exit; }
    $vals[] = $id;
    $pdo->prepare('UPDATE incidents SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'incident_delete') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false, 'error' => 'id required']); exit; }
    $pdo->prepare('DELETE FROM incidents WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'incidents_list') {
    $stmt = $pdo->query(
        'SELECT id, title, description, severity, status, component, started_at, resolved_at '
        . 'FROM incidents ORDER BY started_at DESC LIMIT 100'
    );
    echo json_encode(['ok' => true, 'incidents' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);


