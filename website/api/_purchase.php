<?php
// Общая логика покупки тарифа из таблицы `products`.
// Используется обоими: api/buy_key.php (web) и api/bot/buy.php (бот).
//
// Возвращает массив с полями для ответа:
//   ['ok'=>true, 'key'=>..., 'key_id'=>..., 'product'=>..., 'tokens'=>...,
//    'price_rub'=>..., 'original_price'=>..., 'promo_discount'=>..., 'promo_code'=>...,
//    'is_trial'=>bool, 'new_balance'=>float]
// или ['ok'=>false, 'error'=>string, 'http'=>int].
//
// Опции в $opts:
//   block_trial (bool, default false) — если true, is_trial-тариф отклоняется (для бота).
//   tx_label (string|null) — кастомный лейбл api_keys.label (по умолчанию product.name).
//   tg_notify (bool, default true) — слать ли уведомления notify_user + tg_alert.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/scarlex_proxy.php';
require_once __DIR__ . '/_tg_notify.php';

if (!function_exists('purchase_product')) {
    /**
     * @return array  ['ok'=>bool, ...payload | 'error'=>string, 'http'=>int]
     */
    function purchase_product(PDO $pdo, int $userId, int $productId, string $promoCode = '', array $opts = []): array {
        $blockTrial = !empty($opts['block_trial']);
        $tgNotify   = !array_key_exists('tg_notify', $opts) || (bool)$opts['tg_notify'];

        // ── Product ──
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_visible = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) return ['ok' => false, 'error' => 'Тариф не найден', 'http' => 404];

        $isTrial = (int)$product['is_trial'] === 1;
        if ($isTrial && $blockTrial) {
            return ['ok' => false, 'error' => 'Trial-ключ можно получить только на сайте: https://scarlex.ru', 'http' => 403];
        }

        $tokens = (int)$product['tokens'];
        $rpm    = (int)($product['rpm'] ?: 60);
        $price  = (float)$product['price_rub'];
        $name   = $product['name'];

        // ── Trial guard (один на пользователя) ──
        if ($isTrial) {
            $chk = $pdo->prepare("SELECT 1 FROM api_keys WHERE user_id = ? AND is_trial = 1 LIMIT 1");
            $chk->execute([$userId]);
            if ($chk->fetch()) return ['ok' => false, 'error' => 'Trial-ключ уже был активирован для этого аккаунта', 'http' => 409];
            $chk2 = $pdo->prepare("SELECT free_test_key_claimed FROM users WHERE id = ?");
            $chk2->execute([$userId]);
            if ((int)($chk2->fetchColumn() ?: 0) === 1) {
                return ['ok' => false, 'error' => 'Trial-ключ уже был активирован для этого аккаунта', 'http' => 409];
            }
        }

        // ── Promo (опционально, только тип "purchase") ──
        $promoCode = strtoupper(trim($promoCode));
        $promo = null;
        $promoDiscount = 0.0;
        if ($promoCode !== '') {
            $ps = $pdo->prepare('SELECT * FROM promocodes WHERE code = ? AND is_active = 1');
            $ps->execute([$promoCode]);
            $promo = $ps->fetch();
            if (!$promo) return ['ok' => false, 'error' => 'Промокод не найден или неактивен', 'http' => 400];
            if ($promo['type'] === 'balance') {
                return ['ok' => false, 'error' => 'Этот промокод для пополнения баланса, активируйте в профиле', 'http' => 400];
            }
            if ($promo['expires_at'] && strtotime($promo['expires_at']) < time()) {
                return ['ok' => false, 'error' => 'Промокод истёк', 'http' => 400];
            }
            if ((int)$promo['max_uses'] > 0 && (int)$promo['used_count'] >= (int)$promo['max_uses']) {
                return ['ok' => false, 'error' => 'Промокод исчерпан', 'http' => 400];
            }
            $pu = $pdo->prepare('SELECT id FROM promocode_usages WHERE promo_id = ? AND user_id = ?');
            $pu->execute([$promo['id'], $userId]);
            if ($pu->fetch()) return ['ok' => false, 'error' => 'Вы уже использовали этот промокод', 'http' => 400];
            $promoDiscount = round($price * (float)$promo['value'] / 100, 2);
        }
        $finalPrice = max(0, $price - $promoDiscount);

        // ── Transaction ──
        $pdo->beginTransaction();
        try {
            $balRow = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
            $balRow->execute([$userId]);
            $balance = (float)$balRow->fetchColumn();

            if ($finalPrice > 0 && $balance < $finalPrice) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'error' => 'Недостаточно средств. Нужно ' . $finalPrice . ' ₽, на балансе ' . round($balance, 2) . ' ₽',
                    'http' => 402,
                ];
            }

            $r = scarlex_create_key($name, $tokens, $rpm);
            if (!$r['ok']) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Ошибка создания ключа: ' . ($r['error'] ?? 'unknown'), 'http' => 502];
            }
            $keyValue = $r['key'];

            $ins = $pdo->prepare(
                "INSERT INTO api_keys (user_id, key_value, label, tag, tokens_limit, source, is_trial, product_id)
                 VALUES (?, ?, ?, 'occupied', ?, 'scarlex_proxy', ?, ?)"
            );
            $ins->execute([$userId, $keyValue, $name, $tokens, $isTrial ? 1 : 0, $productId]);
            $keyId = (int)$pdo->lastInsertId();

            if ($finalPrice > 0) {
                $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?')->execute([$finalPrice, $userId]);
                $balance -= $finalPrice;
                $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, api_key_id) VALUES (?, 'purchase', ?, ?, ?)")
                    ->execute([$userId, -$finalPrice, 'Покупка ' . $name, $keyId]);
            }

            if ($isTrial) {
                $pdo->prepare('UPDATE users SET free_test_key_claimed = 1 WHERE id = ?')->execute([$userId]);
            }

            if ($promo) {
                $pdo->prepare('INSERT INTO promocode_usages (promo_id, user_id, discount_amount) VALUES (?, ?, ?)')
                    ->execute([$promo['id'], $userId, $promoDiscount]);
                $pdo->prepare('UPDATE promocodes SET used_count = used_count + 1 WHERE id = ?')->execute([$promo['id']]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('purchase_product tx failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Ошибка транзакции', 'http' => 500];
        }

        if ($tgNotify) {
            try {
                notify_user($pdo, $userId,
                    'Куплен ключ ' . $name . ' за ' . number_format($finalPrice, 2, '.', ' ') . ' ₽.',
                    'Ключ куплен', 'success');
                if (function_exists('tg_alert')) {
                    tg_alert($pdo, $userId, 'payment_received',
                        "🛒 <b>Ключ куплен</b>\n\n📦 " . $name . "\n💰 −" . number_format($finalPrice, 2, '.', ' ') . " ₽\n💵 Баланс: <b>" . number_format($balance, 2, '.', ' ') . " ₽</b>",
                        'purchase_' . $keyId, 0);
                }
            } catch (Exception $e) { /* best-effort */ }
        }

        return [
            'ok'             => true,
            'key'            => $keyValue,
            'key_id'         => $keyId,
            'product'        => $name,
            'tokens'         => $tokens,
            'price_rub'      => $finalPrice,
            'original_price' => $price,
            'promo_discount' => $promoDiscount,
            'promo_code'     => $promo ? $promo['code'] : null,
            'is_trial'       => $isTrial,
            'new_balance'    => round($balance, 2),
        ];
    }
}
