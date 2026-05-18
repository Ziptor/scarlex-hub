<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cbr_rate.php';
require_once __DIR__ . '/scarlex_proxy.php';
require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/_tg_notify.php';
require_once __DIR__ . '/_purchase.php';

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? $_GET['action'] ?? 'products';
$userId = (int)($_SESSION['user_id'] ?? 0);

/** Загрузить публичный список товаров. */
function load_scarlex_products(PDO $pdo): array {
    $rows = $pdo->query("SELECT * FROM products
                         WHERE is_visible = 1
                         ORDER BY sort_order ASC, id ASC")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $tokens = (int)$r['tokens'];
        $price  = (float)$r['price_rub'];
        $per1m  = $tokens > 0 ? round($price / ($tokens / 1000000.0), 2) : 0.0;
        $out[] = [
            'id'           => (int)$r['id'],
            'slug'         => $r['slug'],
            'name'         => $r['name'],
            'tokens'       => $tokens,
            'price_rub'    => round($price, 2),
            'price_per_1m' => $per1m,
            'rpm'          => (int)$r['rpm'],
            'description'  => $r['description'] ?? '',
            'badge'        => $r['badge'] ?? null,
            'is_trial'     => (int)$r['is_trial'] === 1,
            'allow_custom' => (int)$r['allow_custom'] === 1,
            'sort_order'   => (int)$r['sort_order'],
        ];
    }
    return $out;
}

// ── Public: products list ──
if ($action === 'products') {
    $rate = get_usd_rub_rate();
    $products = load_scarlex_products($pdo);
    $range = pricing_custom_range($pdo);
    echo json_encode([
        'ok'       => true,
        'products' => $products,
        'rate'     => $rate,
        'custom'   => [
            'enabled'     => $userId > 0,
            'min'         => $range['min'],
            'max'         => $range['max'],
            'quote_max'   => $range['quote_max'],
            'contact_url' => $range['contact_url'],
        ],
    ]);
    exit;
}

// ── Public: quote custom ──
if ($action === 'quote_custom') {
    $tokens = (int)($input['tokens'] ?? $_GET['tokens'] ?? 0);
    if ($tokens <= 0) { echo json_encode(['ok' => false, 'error' => 'tokens required']); exit; }
    $q = pricing_calc_custom($pdo, $tokens);
    $resp = ['ok' => true, 'tokens' => $tokens];
    if (!empty($q['contact'])) {
        $resp['contact']     = true;
        $resp['contact_url'] = $q['contact_url'];
        $resp['message']     = 'Объём свыше ' . number_format(PRICING_QUOTE_MAX, 0, '', ' ') . ' токенов — цена по договорённости.';
    } else {
        $resp['contact']   = false;
        $resp['price_rub'] = $q['price_rub'];
        $resp['per_1m']    = $q['per_1m'];
        $resp['tier']      = $q['tier'];
    }
    echo json_encode($resp);
    exit;
}

// ── All other actions require auth ──
if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

/** Применить промокод; на ошибке делает echo+exit. */
function apply_promo(PDO $pdo, int $userId, string $promoCode, float $priceRub): array {
    if (!$promoCode) return [0.0, null];
    $ps = $pdo->prepare('SELECT * FROM promocodes WHERE code = ? AND is_active = 1');
    $ps->execute([$promoCode]);
    $promo = $ps->fetch();
    if (!$promo) { echo json_encode(['ok' => false, 'error' => 'Промокод не найден или неактивен']); exit; }
    if ($promo['type'] === 'balance') {
        echo json_encode(['ok' => false, 'error' => 'Этот промокод для пополнения баланса, активируйте в профиле']); exit;
    }
    if ($promo['expires_at'] && strtotime($promo['expires_at']) < time()) {
        echo json_encode(['ok' => false, 'error' => 'Промокод истёк']); exit;
    }
    if ($promo['max_uses'] > 0 && $promo['used_count'] >= $promo['max_uses']) {
        echo json_encode(['ok' => false, 'error' => 'Промокод исчерпан']); exit;
    }
    $pu = $pdo->prepare('SELECT id FROM promocode_usages WHERE promo_id = ? AND user_id = ?');
    $pu->execute([$promo['id'], $userId]);
    if ($pu->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Вы уже использовали этот промокод']); exit;
    }
    $discount = round($priceRub * (float)$promo['value'] / 100, 2);
    return [$discount, $promo];
}

/** Создать запись api_keys + транзакцию. */
function persist_purchased_key(PDO $pdo, int $userId, string $keyValue, int $tokens, string $label, ?int $productId, bool $isTrial, float $totalPrice, string $txDesc = ''): int {
    $stmt = $pdo->prepare("INSERT INTO api_keys (user_id, key_value, label, tag, tokens_limit, source, is_trial, product_id)
                           VALUES (?, ?, ?, 'occupied', ?, 'scarlex_proxy', ?, ?)");
    $stmt->execute([$userId, $keyValue, $label, $tokens, $isTrial ? 1 : 0, $productId]);
    $keyId = (int)$pdo->lastInsertId();
    if ($totalPrice > 0) {
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, api_key_id) VALUES (?, 'purchase', ?, ?, ?)")
            ->execute([$userId, -$totalPrice, $txDesc ?: ('Покупка ' . $label), $keyId]);
    }
    return $keyId;
}

// ── Buy fixed-tariff (incl. Trial) ──
if ($action === 'buy') {
    $productId = (int)($input['product_id'] ?? 0);
    if (!$productId) { echo json_encode(['ok' => false, 'error' => 'product_id required']); exit; }

    $promoCode = trim((string)($input['promo_code'] ?? ''));
    $res = purchase_product($pdo, $userId, $productId, $promoCode, [
        'block_trial' => false,
        'tg_notify'   => true,
    ]);
    if (!$res['ok']) {
        echo json_encode(['ok' => false, 'error' => $res['error']]);
        exit;
    }
    echo json_encode([
        'ok'             => true,
        'key'            => $res['key'],
        'key_id'         => $res['key_id'],
        'product'        => $res['product'],
        'tokens'         => $res['tokens'],
        'price_rub'      => $res['price_rub'],
        'is_trial'       => $res['is_trial'],
        'new_balance'    => $res['new_balance'],
        'promo_discount' => $res['promo_discount'],
        'promo_code'     => $res['promo_code'],
    ]);
    exit;
}

// ── Buy custom amount of tokens (slider) ──
if ($action === 'buy_custom') {
    $tokens = (int)($input['tokens'] ?? 0);
    if ($tokens <= 0) { echo json_encode(['ok' => false, 'error' => 'tokens required']); exit; }
    if ($tokens > PRICING_QUOTE_MAX) {
        echo json_encode(['ok' => false, 'error' => 'Объём свыше 500M токенов — цена по договорённости. Свяжитесь с поддержкой: ' . PRICING_CONTACT_TG, 'contact' => true, 'contact_url' => PRICING_CONTACT_TG]);
        exit;
    }
    $range = pricing_custom_range($pdo);
    if ($tokens < $range['min']) {
        echo json_encode(['ok' => false, 'error' => "Минимум: {$range['min']} токенов"]);
        exit;
    }
    // round to 1M step
    $tokens = (int)(round($tokens / 1000000) * 1000000);
    $q = pricing_calc_custom($pdo, $tokens);
    $price = (float)$q['price_rub'];
    $name = 'Custom ' . round($tokens / 1000000) . 'M';

    $promoCode = trim($input['promo_code'] ?? '');
    [$promoDiscount, $promoData] = apply_promo($pdo, $userId, $promoCode, $price);
    $finalPrice = max(0, $price - $promoDiscount);

    $pdo->beginTransaction();
    try {
        $balance = (float)$pdo->query("SELECT balance FROM users WHERE id = " . $userId)->fetchColumn();
        if ($balance < $finalPrice) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Недостаточно средств. Нужно ' . $finalPrice . ' ₽, на балансе ' . $balance . ' ₽']);
            exit;
        }
        $r = scarlex_create_key($name, $tokens, 60);
        if (!$r['ok']) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Ошибка создания ключа: ' . ($r['error'] ?? 'unknown')]);
            exit;
        }
        $keyValue = $r['key'];
        $keyId = persist_purchased_key($pdo, $userId, $keyValue, $tokens, $name, null, false, $finalPrice);

        $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([$finalPrice, $userId]);
        $balance -= $finalPrice;

        if ($promoData) {
            $pdo->prepare('INSERT INTO promocode_usages (promo_id, user_id, discount_amount) VALUES (?, ?, ?)')
                ->execute([$promoData['id'], $userId, $promoDiscount]);
            $pdo->prepare('UPDATE promocodes SET used_count = used_count + 1 WHERE id = ?')->execute([$promoData['id']]);
        }
        $pdo->commit();

        echo json_encode([
            'ok'          => true,
            'key'         => $keyValue,
            'key_id'      => $keyId,
            'tokens'      => $tokens,
            'price_rub'   => $finalPrice,
            'per_1m'      => $q['per_1m'],
            'new_balance' => $balance,
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('buy_key(buy_custom) failed: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Ошибка транзакции']);
        exit;
    }
}

// ── Top up existing key (including converting Trial to paid) ──
if ($action === 'topup_key') {
    $keyId  = (int)($input['key_id'] ?? 0);
    $tokens = (int)($input['tokens'] ?? 0);
    if (!$keyId || $tokens <= 0) { echo json_encode(['ok' => false, 'error' => 'key_id и tokens обязательны']); exit; }
    if ($tokens > PRICING_QUOTE_MAX) {
        echo json_encode(['ok' => false, 'error' => 'Объём свыше 500M токенов — цена по договорённости. Свяжитесь с поддержкой: ' . PRICING_CONTACT_TG, 'contact' => true, 'contact_url' => PRICING_CONTACT_TG]);
        exit;
    }
    $range = pricing_custom_range($pdo);
    if ($tokens < $range['min']) {
        echo json_encode(['ok' => false, 'error' => "Минимум: {$range['min']} токенов"]);
        exit;
    }
    $tokens = (int)(round($tokens / 1000000) * 1000000);

    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE id = ? AND user_id = ?");
    $stmt->execute([$keyId, $userId]);
    $key = $stmt->fetch();
    if (!$key) { echo json_encode(['ok' => false, 'error' => 'Ключ не найден']); exit; }

    $q = pricing_calc_custom($pdo, $tokens);
    $price = (float)$q['price_rub'];

    $pdo->beginTransaction();
    try {
        $balance = (float)$pdo->query("SELECT balance FROM users WHERE id = " . $userId)->fetchColumn();
        if ($balance < $price) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Недостаточно средств. Нужно ' . $price . ' ₽']);
            exit;
        }
        $r = scarlex_topup_key($key['key_value'], $tokens);
        if (!$r['ok']) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Ошибка пополнения: ' . ($r['error'] ?? 'unknown')]);
            exit;
        }

        $newLimit = (int)$key['tokens_limit'] + $tokens;
        $isTrial  = (int)$key['is_trial'] === 1;
        $upd = "UPDATE api_keys SET tokens_limit = ?, is_trial = 0 WHERE id = ?";
        $pdo->prepare($upd)->execute([$newLimit, $keyId]);

        $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([$price, $userId]);
        $balance -= $price;

        $desc = ($isTrial ? 'Превращение Trial в обычный' : 'Пополнение ключа') . ' (+' . round($tokens/1000000) . 'M)';
        $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, api_key_id) VALUES (?, 'topup_key', ?, ?, ?)")
            ->execute([$userId, -$price, $desc, $keyId]);
        $pdo->commit();

        echo json_encode([
            'ok'             => true,
            'key_id'         => $keyId,
            'added_tokens'   => $tokens,
            'tokens_limit'   => $newLimit,
            'price_rub'      => $price,
            'new_balance'    => $balance,
            'was_trial'      => $isTrial,
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('buy_key(topup_key) failed: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Ошибка транзакции']);
        exit;
    }
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
