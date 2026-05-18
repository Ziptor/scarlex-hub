<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

// ── Public: validate promo code ──
if ($action === 'validate') {
    if (!$userId) { echo json_encode(['ok' => false, 'error' => 'Не авторизован']); exit; }
    $code = trim($input['code'] ?? $_GET['code'] ?? '');
    if (!$code) { echo json_encode(['ok' => false, 'error' => 'Введите промокод']); exit; }

    $stmt = $pdo->prepare('SELECT * FROM promocodes WHERE code = ? AND is_active = 1');
    $stmt->execute([$code]);
    $promo = $stmt->fetch();
    if (!$promo) { echo json_encode(['ok' => false, 'error' => 'Промокод не найден или неактивен']); exit; }

    if ($promo['expires_at'] && strtotime($promo['expires_at']) < time()) {
        echo json_encode(['ok' => false, 'error' => 'Промокод истёк']); exit;
    }
    if ($promo['max_uses'] > 0 && $promo['used_count'] >= $promo['max_uses']) {
        echo json_encode(['ok' => false, 'error' => 'Промокод исчерпан']); exit;
    }

    $usageStmt = $pdo->prepare('SELECT id FROM promocode_usages WHERE promo_id = ? AND user_id = ?');
    $usageStmt->execute([$promo['id'], $userId]);
    if ($usageStmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Вы уже использовали этот промокод']); exit;
    }

    echo json_encode([
        'ok' => true,
        'type' => $promo['type'],
        'value' => (float)$promo['value'],
        'code' => $promo['code']
    ]);
    exit;
}

// ── Public: activate balance promo ──
if ($action === 'activate_balance') {
    if (!$userId) { echo json_encode(['ok' => false, 'error' => 'Не авторизован']); exit; }
    $code = trim($input['code'] ?? '');
    if (!$code) { echo json_encode(['ok' => false, 'error' => 'Введите промокод']); exit; }

    $stmt = $pdo->prepare('SELECT * FROM promocodes WHERE code = ? AND is_active = 1');
    $stmt->execute([$code]);
    $promo = $stmt->fetch();
    if (!$promo) { echo json_encode(['ok' => false, 'error' => 'Промокод не найден или неактивен']); exit; }
    if ($promo['type'] !== 'balance') { echo json_encode(['ok' => false, 'error' => 'Этот промокод не для пополнения баланса']); exit; }
    if ($promo['expires_at'] && strtotime($promo['expires_at']) < time()) {
        echo json_encode(['ok' => false, 'error' => 'Промокод истёк']); exit;
    }
    if ($promo['max_uses'] > 0 && $promo['used_count'] >= $promo['max_uses']) {
        echo json_encode(['ok' => false, 'error' => 'Промокод исчерпан']); exit;
    }

    $usageStmt = $pdo->prepare('SELECT id FROM promocode_usages WHERE promo_id = ? AND user_id = ?');
    $usageStmt->execute([$promo['id'], $userId]);
    if ($usageStmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Вы уже использовали этот промокод']); exit;
    }

    $bonusAmount = (float)$promo['value'];
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$bonusAmount, $userId]);
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'bonus', ?, ?)")
            ->execute([$userId, $bonusAmount, 'Промокод ' . $promo['code'] . ': +' . $bonusAmount . ' ₽']);
        $pdo->prepare('INSERT INTO promocode_usages (promo_id, user_id, discount_amount) VALUES (?, ?, ?)')
            ->execute([$promo['id'], $userId, $bonusAmount]);
        $pdo->prepare('UPDATE promocodes SET used_count = used_count + 1 WHERE id = ?')
            ->execute([$promo['id']]);
        $pdo->commit();

        $bs = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $bs->execute([$userId]);
        $newBalance = (float)$bs->fetchColumn();
        echo json_encode(['ok' => true, 'bonus' => $bonusAmount, 'new_balance' => $newBalance]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('promocodes redeem failed: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Ошибка применения промокода']);
    }
    exit;
}

// ── Admin-only actions below ──
if (!$userId) { echo json_encode(['ok' => false, 'error' => 'Не авторизован']); exit; }
$stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
$stmt->execute([$userId]);
$u = $stmt->fetch();
$adminEmail = 'Luckyanencko.andrej2017@yandex.ru';
$isAdmin = $u && strtolower($u['email']) === strtolower($adminEmail);
if (!$isAdmin) { echo json_encode(['ok' => false, 'error' => 'Forbidden']); exit; }

if ($action === 'admin_list') {
    $promos = $pdo->query('SELECT * FROM promocodes ORDER BY created_at DESC')->fetchAll();
    echo json_encode(['ok' => true, 'promos' => $promos]);
    exit;
}

if ($action === 'create') {
    $code = trim($input['code'] ?? '');
    $type = $input['type'] ?? 'discount';
    $value = (float)($input['value'] ?? 0);
    $maxUses = (int)($input['max_uses'] ?? 0);
    $isActive = (int)($input['is_active'] ?? 1);
    $expiresAt = $input['expires_at'] ?? null;

    if (!$code) { echo json_encode(['ok' => false, 'error' => 'Код обязателен']); exit; }
    if ($value <= 0) { echo json_encode(['ok' => false, 'error' => 'Значение должно быть > 0']); exit; }

    $stmt = $pdo->prepare('INSERT INTO promocodes (code, type, value, max_uses, is_active, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
    try {
        $stmt->execute([$code, $type, $value, $maxUses, $isActive, $expiresAt ?: null]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'Код уже существует']);
    }
    exit;
}

if ($action === 'update') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'id required']); exit; }
    $code = trim($input['code'] ?? '');
    $type = $input['type'] ?? 'discount';
    $value = (float)($input['value'] ?? 0);
    $maxUses = (int)($input['max_uses'] ?? 0);
    $isActive = (int)($input['is_active'] ?? 1);
    $expiresAt = $input['expires_at'] ?? null;

    $stmt = $pdo->prepare('UPDATE promocodes SET code=?, type=?, value=?, max_uses=?, is_active=?, expires_at=? WHERE id=?');
    $stmt->execute([$code, $type, $value, $maxUses, $isActive, $expiresAt ?: null, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    $pdo->prepare('DELETE FROM promocode_usages WHERE promo_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM promocodes WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'toggle') {
    $id = (int)($input['id'] ?? 0);
    $pdo->prepare('UPDATE promocodes SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
