<?php
// Shared product loader: applies admin overrides + computes stock
// Stock = number of reserve keys with matching tokens (admin-uploaded keys)
require_once __DIR__ . '/cbr_rate.php';
require_once __DIR__ . '/secrets.php';

if (!defined('RESELLER_API_PROD')) {
    define('RESELLER_API_PROD', RESELLER_API_URL);
    define('RESELLER_TOKEN_PROD', RESELLER_API_TOKEN);
}

function fetch_reseller_products(): array {
    $ch = curl_init(RESELLER_API_PROD . '/products');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . RESELLER_TOKEN_PROD, 'Accept: application/json'],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $r = curl_exec($ch); curl_close($ch);
    $d = json_decode($r, true);
    return $d['data']['products'] ?? [];
}

/**
 * Returns list of products with applied admin overrides + stock.
 * Each item:
 *   id (reseller product_id), name, description, tokens, price_rub, price_usd_estimate,
 *   stock, sort_order, is_visible, has_override
 */
function load_products($pdo, bool $only_visible = true): array {
    $reseller = fetch_reseller_products();
    $rate = get_usd_rub_rate();

    // Pre-fetch overrides
    $ov = [];
    foreach ($pdo->query("SELECT * FROM product_overrides")->fetchAll() as $row) {
        $ov[(int)$row['reseller_id']] = $row;
    }

    $out = [];
    foreach ($reseller as $p) {
        $rid = (int)$p['id'];
        $tokens = (int)$p['tokens'];
        $defaultPrice = round((float)$p['effective_price'] * $rate['rate'], 2);
        $defaultName = str_replace('AIMETRO', 'SCARLEX', $p['name']);
        $defaultDesc = $p['description'] ?? '';

        $o = $ov[$rid] ?? null;
        $name = $o['name'] ?? $defaultName;
        $desc = $o['description'] ?? $defaultDesc;
        $priceRub = $o ? (float)$o['price_rub'] : $defaultPrice;
        $sort = $o ? (int)$o['sort_order'] : $tokens;
        $visible = $o ? (int)$o['is_visible'] : 1;
        $stockCount = $o ? (int)($o['stock_count'] ?? 0) : 0;

        if ($only_visible && !$visible) continue;

        $out[] = [
            'id' => $rid,
            'name' => $name,
            'description' => $desc,
            'tokens' => $tokens,
            'price_rub' => round($priceRub, 2),
            'price_usd_default' => (float)$p['effective_price'],
            'price_usd_estimate' => round($priceRub / $rate['rate'], 2),
            'stock' => $stockCount,
            'sort_order' => $sort,
            'is_visible' => $visible,
            'has_override' => $o !== null,
        ];
    }

    usort($out, fn($a, $b) => $a['sort_order'] <=> $b['sort_order']);
    return $out;
}

function load_product_by_id($pdo, int $rid): ?array {
    foreach (load_products($pdo, false) as $p) {
        if ($p['id'] === $rid) return $p;
    }
    return null;
}

/**
 * Returns user-specific custom prices for a user, keyed by reseller_id.
 * Each value: ['price_rub' => float, 'currency' => 'RUB'|'USD', 'price_value' => float, 'note' => string|null]
 */
function load_user_price_overrides($pdo, int $userId): array {
    $stmt = $pdo->prepare("SELECT reseller_id, price_value, price_currency, note FROM user_product_prices WHERE user_id = ?");
    $stmt->execute([$userId]);
    $rate = get_usd_rub_rate();
    $rateValue = (float)($rate['rate'] ?? 0);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $val = (float)$row['price_value'];
        $cur = $row['price_currency'] === 'USD' ? 'USD' : 'RUB';
        $rub = $cur === 'USD' ? round($val * $rateValue, 2) : round($val, 2);
        $out[(int)$row['reseller_id']] = [
            'price_rub' => $rub,
            'price_value' => $val,
            'currency' => $cur,
            'note' => $row['note'],
        ];
    }
    return $out;
}

/**
 * Returns products with user-specific overrides applied.
 * Each item gets: is_custom_price (bool), custom_price_currency, custom_price_value (if applicable).
 */
function load_products_for_user($pdo, int $userId, bool $only_visible = true): array {
    $products = load_products($pdo, $only_visible);
    $overrides = load_user_price_overrides($pdo, $userId);
    foreach ($products as &$p) {
        $rid = (int)$p['id'];
        if (isset($overrides[$rid])) {
            $o = $overrides[$rid];
            $p['price_rub'] = $o['price_rub'];
            $p['price_usd_estimate'] = $o['currency'] === 'USD' ? $o['price_value'] : round($o['price_rub'] / max(1.0, (float)(get_usd_rub_rate()['rate'] ?? 1)), 2);
            $p['is_custom_price'] = true;
            $p['custom_price_currency'] = $o['currency'];
            $p['custom_price_value'] = $o['price_value'];
            $p['custom_price_note'] = $o['note'];
        } else {
            $p['is_custom_price'] = false;
        }
    }
    unset($p);
    return $products;
}

function load_product_for_user($pdo, int $userId, int $rid): ?array {
    foreach (load_products_for_user($pdo, $userId, false) as $p) {
        if ((int)$p['id'] === $rid) return $p;
    }
    return null;
}
