<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_tg_notify.php';
require_once __DIR__ . '/products_loader.php';
require_once __DIR__ . '/scarlex_proxy.php';
require_once __DIR__ . '/helpers.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? 'list';

if (!defined('API_BASE_URL')) define('API_BASE_URL', UPSTREAM_API_URL);
if (!defined('RESELLER_API')) define('RESELLER_API', RESELLER_API_URL);
if (!defined('RESELLER_TOKEN')) define('RESELLER_TOKEN', RESELLER_API_TOKEN);
if (!defined('KIRO_API_URL'))   define('KIRO_API_URL', 'https://api.scarlex.ru');
if (!defined('KIRO_ADMIN_KEY')) define('KIRO_ADMIN_KEY', '');

function mask_key($key) {
    if (strlen($key) <= 14) return $key;
    return substr($key, 0, 10) . '...' . substr($key, -4);
}

/**
 * Look up a key in Kiro-pool by full key value.
 * Matches by preview (first 8 + "..." + last 4) against /admin/keys.
 * Returns the full key dict (name, enabled, rpm_limit, credits_limit, credits_used,
 * tokens_limit, tokens_used, allowed_models, hard_limit, stats, key_preview) or null.
 */
function lookup_kiro_key(string $key): ?array {
    if (KIRO_ADMIN_KEY === '') return null;
    $preview = strlen($key) > 12 ? substr($key, 0, 8) . '...' . substr($key, -4) : $key;
    static $cache = null;
    if ($cache === null) {
        $ch = curl_init(KIRO_API_URL . '/admin/keys');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . KIRO_ADMIN_KEY, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $cache = [];
        if ($code === 200 && $body) {
            $data = json_decode($body, true);
            foreach ((array)($data['keys'] ?? []) as $k) {
                if (!empty($k['key_preview'])) $cache[$k['key_preview']] = $k;
            }
        }
    }
    return $cache[$preview] ?? null;
}

/**
 * For Kiro keys, choose the "primary" usage axis.
 * If credits_limit > 0 → return credits. Else if tokens_limit > 0 → return tokens.
 * Else (both 0 = unlimited) → return credits as-is (used > 0, limit = 0).
 * Returns ['limit' => int, 'used' => int].
 */
function kiro_usage_axis(array $kiroKey): array {
    $cl = (int)($kiroKey['credits_limit'] ?? 0);
    $cu = (int)($kiroKey['credits_used'] ?? 0);
    $tl = (int)($kiroKey['tokens_limit'] ?? 0);
    $tu = (int)($kiroKey['tokens_used'] ?? 0);
    if ($cl > 0) return ['limit' => $cl, 'used' => $cu];
    if ($tl > 0) return ['limit' => $tl, 'used' => $tu];
    return ['limit' => 0, 'used' => max($cu, $tu)];
}

function reseller_request(string $method, string $path, ?array $body = null): array {
    $ch = curl_init(RESELLER_API . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESELLER_TOKEN,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
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
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($resp, true)];
}

function validate_key_remote($key) {
    $ch = curl_init(API_BASE_URL . '/v1/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key],
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 && $body && strpos($body, '"data"') !== false;
}

function fetch_reseller_keys() {
    $r = reseller_request('GET', '/keys');
    $data = $r['data'];
    if ($r['code'] !== 200 || !$data || !$data['ok'] || empty($data['data']['keys'])) return [];
    $map = [];
    foreach ($data['data']['keys'] as $k) {
        $map[$k['api_key']] = $k;
    }
    return $map;
}

function sync_key_stats($pdo, $localId, $remote) {
    $stmt = $pdo->prepare('UPDATE api_keys SET tokens_limit = ?, used_tokens = ?, is_active = ?, reseller_key_id = COALESCE(reseller_key_id, ?) WHERE id = ?');
    $stmt->execute([
        (int)$remote['max_tokens'],
        (int)$remote['used_tokens'],
        $remote['is_active'] ? 1 : 0,
        (int)($remote['id'] ?? 0) ?: null,
        $localId
    ]);
}

function require_owned_key(PDO $pdo, int $userId, int $id): array {
    $stmt = $pdo->prepare('SELECT * FROM api_keys WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Ключ не найден']);
        exit;
    }
    return $row;
}

function ensure_reseller_key_id(PDO $pdo, array $row): ?int {
    if (!empty($row['reseller_key_id'])) return (int)$row['reseller_key_id'];
    $map = fetch_reseller_keys();
    if (isset($map[$row['key_value']]) && !empty($map[$row['key_value']]['id'])) {
        $rid = (int)$map[$row['key_value']]['id'];
        $pdo->prepare('UPDATE api_keys SET reseller_key_id = ? WHERE id = ?')->execute([$rid, (int)$row['id']]);
        return $rid;
    }
    return null;
}

if ($action === 'list') {
    $stmt = $pdo->prepare('SELECT id, key_value, label, tokens_limit, used_tokens, is_active, is_frozen, is_test, source, reseller_key_id, created_at FROM api_keys WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    $localKeys = $stmt->fetchAll();

    // Fetch live data from reseller API
    $remoteMap = fetch_reseller_keys();

    $keys = [];
    foreach ($localKeys as $row) {
        $keyVal = $row['key_value'];
        $limit = (int)$row['tokens_limit'];
        $used = (int)$row['used_tokens'];
        $active = (int)$row['is_active'] === 1;

        // Sync with Kiro pool first (our scarlex_proxy keys)
        if (($row['source'] ?? '') === 'scarlex_proxy') {
            $kiroKey = lookup_kiro_key($keyVal);
            if ($kiroKey) {
                $axis = kiro_usage_axis($kiroKey);
                $limit  = $axis['limit'];
                $used   = $axis['used'];
                $active = (bool)($kiroKey['enabled'] ?? true);
                // Persist to local DB so dashboard tiles remain stable
                $pdo->prepare('UPDATE api_keys SET tokens_limit = ?, used_tokens = ?, is_active = ? WHERE id = ?')
                    ->execute([$limit, $used, $active ? 1 : 0, (int)$row['id']]);
            }
        }
        // Sync with reseller for legacy keys
        elseif (isset($remoteMap[$keyVal])) {
            $r = $remoteMap[$keyVal];
            $limit = (int)$r['max_tokens'];
            $used = (int)$r['used_tokens'];
            $active = (bool)$r['is_active'];
            sync_key_stats($pdo, (int)$row['id'], $r);
        }

        $keys[] = [
            'id' => (int)$row['id'],
            'key_value' => $keyVal,
            'masked' => mask_key($keyVal),
            'label' => $row['label'],
            'tokens_limit' => $limit,
            'used_tokens' => $used,
            'remaining' => max(0, $limit - $used),
            'usage_percent' => $limit > 0 ? round(($used / $limit) * 100, 1) : 0,
            'is_active' => $active,
            'is_frozen' => (int)$row['is_frozen'] === 1,
            'is_test' => (int)$row['is_test'] === 1,
            'source' => $row['source'],
            'created_at' => $row['created_at'],
            'base_url' => API_BASE_URL
        ];
    }
    echo json_encode(['ok' => true, 'keys' => $keys, 'base_url' => API_BASE_URL]);
    exit;
}

if ($action === 'add') {
    $key = trim($input['key_value'] ?? '');
    $label = trim($input['label'] ?? '');

    if (!$key) {
        echo json_encode(['ok' => false, 'error' => 'Введите ключ']);
        exit;
    }
    if (strlen($key) < 20 || strlen($key) > 255) {
        echo json_encode(['ok' => false, 'error' => 'Некорректная длина ключа']);
        exit;
    }

    // Check if key exists in our DB at all (any user)
    $stmt = $pdo->prepare('SELECT id, user_id, tokens_limit, used_tokens FROM api_keys WHERE key_value = ?');
    $stmt->execute([$key]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Belongs to current user
        if ((int)$existing['user_id'] === (int)$userId) {
            echo json_encode(['ok' => false, 'error' => 'Этот ключ уже привязан к твоему аккаунту']);
            exit;
        }
        // Belongs to another user
        if ($existing['user_id']) {
            echo json_encode(['ok' => false, 'error' => 'Этот ключ уже привязан к другому пользователю']);
            exit;
        }
        // Orphan (user_id NULL) — claim it
        $pdo->prepare("UPDATE api_keys SET user_id = ?, tag = 'occupied', label = ? WHERE id = ?")
            ->execute([$userId, $label ?: null, $existing['id']]);
        echo json_encode([
            'ok' => true,
            'id' => (int)$existing['id'],
            'claimed' => true,
            'tokens_limit' => (int)$existing['tokens_limit'],
            'used_tokens' => (int)$existing['used_tokens'],
        ]);
        exit;
    }

    // Try Kiro pool first (our own scarlex_proxy keys)
    $kiroKey = lookup_kiro_key($key);
    if ($kiroKey) {
        $label = $label ?: ($kiroKey['name'] ?? null);
        $axis  = kiro_usage_axis($kiroKey);
        $stmt = $pdo->prepare('INSERT INTO api_keys (user_id, key_value, label, tag, tokens_limit, used_tokens, source, reseller_key_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $key, $label ?: null, 'occupied', $axis['limit'], $axis['used'], 'scarlex_proxy', null]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId(), 'source' => 'scarlex_proxy']);
        exit;
    }

    // Validate with upstream API (reseller)
    if (!validate_key_remote($key)) {
        echo json_encode(['ok' => false, 'error' => 'Ключ недействителен или недоступен']);
        exit;
    }

    // Fetch limits from reseller API
    $remoteMap = fetch_reseller_keys();
    $limit = 0;
    $used = 0;
    $rid = null;
    if (isset($remoteMap[$key])) {
        $limit = (int)$remoteMap[$key]['max_tokens'];
        $used = (int)$remoteMap[$key]['used_tokens'];
        $rid = (int)($remoteMap[$key]['id'] ?? 0) ?: null;
    }

    $stmt = $pdo->prepare('INSERT INTO api_keys (user_id, key_value, label, tag, tokens_limit, used_tokens, source, reseller_key_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $key, $label ?: null, 'occupied', $limit, $used, 'imported', $rid]);

    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM api_keys WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'rename') {
    $id = (int)($input['id'] ?? 0);
    $label = trim($input['label'] ?? '');
    if (mb_strlen($label) > 100) {
        echo json_encode(['ok' => false, 'error' => 'Слишком длинное название (макс. 100)']);
        exit;
    }
    $stmt = $pdo->prepare('UPDATE api_keys SET label = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$label ?: null, $id, $userId]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'freeze' || $action === 'unfreeze') {
    $id = (int)($input['id'] ?? 0);
    $row = require_owned_key($pdo, $userId, $id);
    $rid = ensure_reseller_key_id($pdo, $row);
    if (!$rid) {
        echo json_encode(['ok' => false, 'error' => 'Не удалось определить ID ключа у поставщика. Попробуйте обновить страницу.']);
        exit;
    }
    $endpoint = $action === 'freeze' ? '/freeze' : '/unfreeze';
    $r = reseller_request('POST', '/keys/' . $rid . $endpoint, null);
    $d = $r['data'];
    if (empty($d['ok'])) {
        $err = $d['message'] ?? $d['error'] ?? 'Reseller error';
        // already_inactive / already_active treated as success
        if (in_array($d['error'] ?? '', ['already_inactive', 'already_active'], true)) {
            $newFrozen = $action === 'freeze' ? 1 : 0;
            $pdo->prepare('UPDATE api_keys SET is_frozen = ?, is_active = ? WHERE id = ?')
                ->execute([$newFrozen, $newFrozen ? 0 : 1, $id]);
            echo json_encode(['ok' => true, 'is_frozen' => (bool)$newFrozen, 'note' => 'sync']);
            exit;
        }
        echo json_encode(['ok' => false, 'error' => $err]);
        exit;
    }
    $newFrozen = $action === 'freeze' ? 1 : 0;
    $pdo->prepare('UPDATE api_keys SET is_frozen = ?, is_active = ? WHERE id = ?')
        ->execute([$newFrozen, $newFrozen ? 0 : 1, $id]);
    notify_user($pdo, (int)$userId,
        $action === 'freeze'
            ? 'Ключ ' . ($row['label'] ?: mask_key($row['key_value'])) . ' заморожен. Запросы к нему вернут ошибку, пока не разморозите.'
            : 'Ключ ' . ($row['label'] ?: mask_key($row['key_value'])) . ' разморожен и снова работает.',
        $action === 'freeze' ? 'Ключ заморожен' : 'Ключ разморожен',
        'info'
    );
    if ($action === 'freeze') {
        tg_alert($pdo, (int)$userId, 'key_frozen',
            "🔒 <b>Ключ заморожен</b>\n\n🔑 " . ($row['label'] ?: mask_key($row['key_value'])) . "\n\nЗапросы к нему вернут ошибку, пока не разморозите.",
            'key_' . $id, 0);
    }
    echo json_encode(['ok' => true, 'is_frozen' => (bool)$newFrozen]);
    exit;
}

if ($action === 'topup') {
    $id = (int)($input['id'] ?? 0);
    $productId = (int)($input['product_id'] ?? 0);
    if (!$productId) {
        echo json_encode(['ok' => false, 'error' => 'product_id required']);
        exit;
    }
    $row = require_owned_key($pdo, $userId, $id);
    if ((int)$row['is_test'] === 1) {
        echo json_encode(['ok' => false, 'error' => 'Тестовый ключ нельзя пополнить. Купи обычный ключ в Маркете.']);
        exit;
    }
    if ((int)$row['is_frozen'] === 1) {
        echo json_encode(['ok' => false, 'error' => 'Ключ заморожен. Разморозьте его перед пополнением.']);
        exit;
    }
    $rid = ensure_reseller_key_id($pdo, $row);
    if (!$rid) {
        echo json_encode(['ok' => false, 'error' => 'Не удалось определить ID ключа у поставщика']);
        exit;
    }
    $product = load_product_for_user($pdo, (int)$userId, $productId);
    if (!$product) {
        echo json_encode(['ok' => false, 'error' => 'Тариф не найден']);
        exit;
    }
    $priceRub = (float)$product['price_rub'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) { $pdo->rollBack(); echo json_encode(['ok' => false, 'error' => 'User not found']); exit; }
        $balance = (float)$u['balance'];
        if ($balance < $priceRub) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Недостаточно средств. Нужно ' . $priceRub . ' ₽, на балансе ' . $balance . ' ₽']);
            exit;
        }

        $r = reseller_request('POST', '/keys/' . $rid . '/topup', ['product_id' => $productId]);
        $d = $r['data'];
        if (empty($d['ok'])) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => $d['message'] ?? $d['error'] ?? 'Reseller error']);
            exit;
        }
        $resp = $d['data'];
        $newMax = (int)($resp['new_max_tokens'] ?? ($row['tokens_limit'] + ($resp['tokens_added'] ?? 0)));
        $pdo->prepare('UPDATE api_keys SET tokens_limit = ? WHERE id = ?')->execute([$newMax, $id]);
        $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([$priceRub, $userId]);
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, api_key_id) VALUES (?, 'topup_key', ?, ?, ?)")
            ->execute([$userId, -$priceRub, 'Пополнение ключа: ' . $product['name'], $id]);
        $pdo->commit();

        notify_user($pdo, (int)$userId,
            'К ключу ' . ($row['label'] ?: mask_key($row['key_value'])) . ' добавлено ' . number_format((int)$resp['tokens_added'], 0, '.', ' ') . ' токенов (' . $product['name'] . ', −' . number_format($priceRub, 2, '.', ' ') . ' ₽).',
            'Ключ пополнен',
            'success'
        );
        tg_alert($pdo, (int)$userId, 'payment_received',
            "🔑 <b>Ключ пополнен</b>\n\n📦 " . $product['name'] . "\n💰 −" . number_format($priceRub, 2, '.', ' ') . " ₽\n🔑 " . ($row['label'] ?: mask_key($row['key_value'])) . "\n💵 Баланс: <b>" . number_format($balance - $priceRub, 2, '.', ' ') . " ₽</b>",
            'topup_key_' . $id . '_' . time(), 0);

        echo json_encode([
            'ok' => true,
            'tokens_added' => (int)$resp['tokens_added'],
            'new_max_tokens' => $newMax,
            'price_rub' => $priceRub,
            'new_balance' => $balance - $priceRub,
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('keys.php purchase tx failed: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Ошибка транзакции']);
        exit;
    }
}

if ($action === 'refund') {
    $id = (int)($input['id'] ?? 0);
    $row = require_owned_key($pdo, $userId, $id);
    if ((int)$row['is_test'] === 1) {
        echo json_encode(['ok' => false, 'error' => 'Тестовый ключ нельзя вернуть']);
        exit;
    }
    $rid = ensure_reseller_key_id($pdo, $row);
    if (!$rid) {
        echo json_encode(['ok' => false, 'error' => 'Не удалось определить ID ключа у поставщика']);
        exit;
    }

    // We need to know how much we charged the user originally (purchase tx) to convert refund USD → RUB.
    // Find the most recent 'purchase' transaction for this key.
    $stmtTx = $pdo->prepare("SELECT amount FROM transactions WHERE user_id = ? AND api_key_id = ? AND type = 'purchase' ORDER BY id DESC LIMIT 1");
    $stmtTx->execute([$userId, $id]);
    $origTx = $stmtTx->fetch();
    if (!$origTx) {
        echo json_encode(['ok' => false, 'error' => 'История покупки этого ключа не найдена. Возврат недоступен.']);
        exit;
    }
    $originalRub = abs((float)$origTx['amount']);

    $pdo->beginTransaction();
    try {
        $r = reseller_request('POST', '/keys/' . $rid . '/refund', null);
        $d = $r['data'];
        if (empty($d['ok'])) {
            $pdo->rollBack();
            $err = $d['message'] ?? $d['error'] ?? 'Reseller error';
            // Friendly Russian errors
            if (($d['error'] ?? '') === 'over_limit') {
                $err = 'Возврат недоступен: использовано более 50% токенов';
            } elseif (($d['error'] ?? '') === 'multi_purchase') {
                $err = 'Возврат недоступен: ключ был пополнен. Обратитесь в @ruapi_support';
            } elseif (($d['error'] ?? '') === 'inactive') {
                $err = 'Ключ уже неактивен';
            }
            echo json_encode(['ok' => false, 'error' => $err]);
            exit;
        }
        $resp = $d['data'];
        $refundUsd = (float)$resp['refund_amount'];
        $usedPercent = (float)($resp['used_percent'] ?? 0);
        // Convert proportionally: refundRub = originalRub * (refundUsd / originalUsd).
        // We don't have originalUsd; safer: refundRub = originalRub * (1 - usedPercent/100)
        $refundRub = round($originalRub * max(0, (1 - $usedPercent / 100)), 2);

        // Mark key as deleted locally (reseller already deleted it)
        $pdo->prepare("UPDATE api_keys SET is_active = 0, tag = 'refunded' WHERE id = ?")->execute([$id]);
        $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$refundRub, $userId]);
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, api_key_id) VALUES (?, 'refund', ?, ?, ?)")
            ->execute([$userId, $refundRub, 'Возврат за ключ ' . ($row['label'] ?: mask_key($row['key_value'])) . ' (использовано ' . $usedPercent . '%)', $id]);
        $pdo->commit();

        notify_user($pdo, (int)$userId,
            'Возврат за ключ ' . ($row['label'] ?: mask_key($row['key_value'])) . ': +' . number_format($refundRub, 2, '.', ' ') . ' ₽ зачислено на баланс. Использовано было ' . $usedPercent . '%.',
            'Возврат выполнен',
            'success'
        );
        tg_alert($pdo, (int)$userId, 'payment_received',
            "↩️ <b>Возврат выполнен</b>\n\n🔑 " . ($row['label'] ?: mask_key($row['key_value'])) . "\n💰 +" . number_format($refundRub, 2, '.', ' ') . " ₽\n📊 Использовано: " . $usedPercent . "%",
            'refund_' . $id, 0);

        echo json_encode([
            'ok' => true,
            'refund_rub' => $refundRub,
            'refund_usd' => $refundUsd,
            'used_percent' => $usedPercent,
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('keys.php refund tx failed: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Ошибка транзакции']);
        exit;
    }
}

if ($action === 'test_key') {
    // Free 1M-token test key, one per user lifetime.
    // Atomic claim BEFORE calling reseller to prevent race condition where
    // two concurrent requests both pass the eligibility check.
    $stmt = $pdo->prepare(
        'UPDATE users SET free_test_key_claimed = 1
         WHERE id = ? AND free_test_key_claimed = 0'
    );
    $stmt->execute([$userId]);
    if ($stmt->rowCount() === 0) {
        // Either user not found or already claimed
        $exists = $pdo->prepare('SELECT 1 FROM users WHERE id = ?');
        $exists->execute([$userId]);
        if (!$exists->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'User not found']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Бесплатный тестовый ключ уже был получен']);
        }
        exit;
    }

    // Claim succeeded — now create on Kiro. If it fails, roll back the claim.
    $tokens = 1000000;
    $r = scarlex_create_key('Тестовый ключ', $tokens, 30);
    if (empty($r['ok'])) {
        $pdo->prepare('UPDATE users SET free_test_key_claimed = 0 WHERE id = ?')->execute([$userId]);
        echo json_encode(['ok' => false, 'error' => $r['error'] ?? 'Kiro error']);
        exit;
    }
    $keyValue = $r['key'];

    $newId = null;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO api_keys (user_id, key_value, label, tag, tokens_limit, used_tokens, source, is_test) VALUES (?, ?, ?, 'occupied', ?, 0, 'scarlex_proxy', 1)")
            ->execute([$userId, $keyValue, 'Тестовый ключ', $tokens]);
        $newId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, api_key_id) VALUES (?, 'test_key', 0, ?, ?)")
            ->execute([$userId, 'Получен бесплатный тестовый ключ (1M токенов)', $newId]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        // Roll back the claim too — key wasn't saved locally
        $pdo->prepare('UPDATE users SET free_test_key_claimed = 0 WHERE id = ?')->execute([$userId]);
        error_log('test_key save failed: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Ошибка сохранения ключа']);
        exit;
    }

    notify_user($pdo, (int)$userId,
        'Получен бесплатный тестовый ключ на 1 000 000 токенов. Найдите его на вкладке "Мои ключи".',
        'Тестовый ключ активирован',
        'success'
    );

    echo json_encode([
        'ok' => true,
        'key_id' => (int)$newId,
        'api_key' => $keyValue,
        'tokens' => $tokens,
    ]);
    exit;
}

if ($action === 'check_test_key_eligibility') {
    $stmt = $pdo->prepare('SELECT free_test_key_claimed FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    echo json_encode(['ok' => true, 'eligible' => empty($u['free_test_key_claimed'])]);
    exit;
}

// ── IP whitelist helpers ──
function kiro_user_request(string $method, string $path, ?array $body = null): array {
    $ch = curl_init(KIRO_API_URL . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . KIRO_ADMIN_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($resp, true), 'raw' => $resp, 'err' => $err];
}

// GET seen IPs + current whitelist for a user-owned key
if ($action === 'key_ips') {
    $key = trim((string)($input['key'] ?? $_GET['key'] ?? ''));
    if ($key === '') { echo json_encode(['ok' => false, 'error' => 'key required']); exit; }
    // Ownership check
    $stmt = $pdo->prepare('SELECT id FROM api_keys WHERE key_value = ? AND user_id = ?');
    $stmt->execute([$key, $userId]);
    if (!$stmt->fetch()) { echo json_encode(['ok' => false, 'error' => 'Ключ не найден']); exit; }

    $preview = strlen($key) > 12 ? substr($key, 0, 8) . '...' . substr($key, -4) : $key;

    // Get seen IPs from logs
    $r1 = kiro_user_request('GET', '/admin/keys/' . urlencode($preview) . '/ips');
    $seen = ($r1['code'] === 200 && is_array($r1['data']) && isset($r1['data']['ips'])) ? $r1['data']['ips'] : [];

    // Get current key metadata for allowed_ips
    $r2 = kiro_user_request('GET', '/admin/keys');
    $allowed = [];
    if ($r2['code'] === 200 && isset($r2['data']['keys'])) {
        foreach ($r2['data']['keys'] as $k) {
            if (($k['key_preview'] ?? '') === $preview) {
                $allowed = $k['allowed_ips'] ?? [];
                break;
            }
        }
    }

    echo json_encode(['ok' => true, 'seen' => $seen, 'allowed_ips' => $allowed]);
    exit;
}

// PUT allowed_ips for a user-owned key
if ($action === 'set_key_whitelist') {
    $key = trim((string)($input['key'] ?? ''));
    $allowed = $input['allowed_ips'] ?? [];
    if ($key === '') { echo json_encode(['ok' => false, 'error' => 'key required']); exit; }
    if (!is_array($allowed)) { echo json_encode(['ok' => false, 'error' => 'allowed_ips must be array']); exit; }
    if (count($allowed) > 50) { echo json_encode(['ok' => false, 'error' => 'Максимум 50 IP']); exit; }

    // Validate each entry: IPv4 or IPv4 CIDR (rejects IPv6, out-of-range prefix)
    $clean = [];
    $seen = [];
    foreach ($allowed as $ip) {
        $ip = trim((string)$ip);
        if ($ip === '' || isset($seen[$ip])) continue;
        if (!validate_ipv4_cidr($ip)) {
            echo json_encode(['ok' => false, 'error' => 'Невалидный IPv4 / CIDR: ' . $ip]);
            exit;
        }
        $seen[$ip] = true;
        $clean[] = $ip;
    }

    // Ownership check
    $stmt = $pdo->prepare('SELECT id FROM api_keys WHERE key_value = ? AND user_id = ?');
    $stmt->execute([$key, $userId]);
    $owned = $stmt->fetch();
    if (!$owned) { echo json_encode(['ok' => false, 'error' => 'Ключ не найден']); exit; }

    // Forward to kiro-proxy (source of truth)
    $r = kiro_user_request('PUT', '/admin/keys', ['key' => $key, 'allowed_ips' => $clean]);
    if ($r['code'] !== 200) {
        $msg = is_array($r['data']) ? ($r['data']['detail'] ?? '') : '';
        echo json_encode(['ok' => false, 'error' => 'Прокси HTTP ' . $r['code'] . ($msg ? ': ' . $msg : '')]);
        exit;
    }

    // Mirror to local MySQL cache so dashboard renders w/o extra round-trip
    $pdo->prepare('UPDATE api_keys SET allowed_ips = ? WHERE id = ?')
        ->execute([$clean ? json_encode($clean) : null, (int)$owned['id']]);

    echo json_encode(['ok' => true, 'allowed_ips' => $clean]);
    exit;
}

// Rotate key value: new sk-scarlex-*, copy all metadata, old key revoked.
// User-facing endpoint — protected by session ownership check.
if ($action === 'regenerate') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false, 'error' => 'id required']); exit; }
    $row = require_owned_key($pdo, $userId, $id);

    if (($row['source'] ?? '') !== 'scarlex_proxy') {
        echo json_encode(['ok' => false, 'error' => 'Регенерация доступна только для ключей Scarlex (не для реселлерских).']);
        exit;
    }
    if ((int)$row['is_frozen'] === 1) {
        echo json_encode(['ok' => false, 'error' => 'Разморозьте ключ перед регенерацией.']);
        exit;
    }

    $oldKey = $row['key_value'];
    $r = kiro_user_request('POST', '/admin/keys/regenerate', ['key' => $oldKey]);
    if ($r['code'] !== 200 || empty($r['data']['new_key'])) {
        $msg = is_array($r['data']) ? ($r['data']['detail'] ?? '') : '';
        echo json_encode(['ok' => false, 'error' => 'Прокси HTTP ' . $r['code'] . ($msg ? ': ' . $msg : '')]);
        exit;
    }
    $newKey = (string)$r['data']['new_key'];

    // Atomic swap in MySQL: same row id, new key_value
    $pdo->prepare('UPDATE api_keys SET key_value = ? WHERE id = ?')->execute([$newKey, $id]);

    notify_user($pdo, (int)$userId,
        'Ключ ' . ($row['label'] ?: mask_key($oldKey)) . ' успешно регенерирован. Старое значение больше не работает.',
        'Ключ обновлён', 'success');

    echo json_encode([
        'ok' => true,
        'id' => $id,
        'new_key' => $newKey,
        'masked' => mask_key($newKey),
    ]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
