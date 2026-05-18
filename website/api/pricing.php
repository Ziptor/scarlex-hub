<?php
// Pricing helpers — ступенчатый прайсинг (Floor) с зоной enterprise (>500M).
require_once __DIR__ . '/db.php';

const PRICING_QUOTE_MAX     = 500000000;     // выше этого — "цена по договорённости"
const PRICING_SLIDER_MAX    = 5000000000;    // верх ползунка
const PRICING_CONTACT_TG    = 'https://t.me/scarlex_support';
const PRICING_FALLBACK_PER1M = 19.0;

/**
 * Anchor-пакеты: is_visible=1 AND allow_custom=1 AND is_trial=0, сортировка по tokens ASC.
 * Возврат [['tokens'=>int, 'price_rub'=>float, 'per_1m'=>float], ...]
 */
function pricing_anchors(PDO $pdo): array {
    $rows = $pdo->query("SELECT tokens, price_rub FROM products
                         WHERE is_visible = 1 AND allow_custom = 1 AND is_trial = 0
                         ORDER BY tokens ASC")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $tk = (int)$r['tokens'];
        if ($tk <= 0) continue;
        $out[] = [
            'tokens'    => $tk,
            'price_rub' => (float)$r['price_rub'],
            'per_1m'    => (float)$r['price_rub'] / ($tk / 1000000.0),
        ];
    }
    return $out;
}

/**
 * Ступенчатый расчёт (Floor): ставка = per_1m anchor'а с наибольшим tokens ≤ $tokens.
 * Если $tokens > PRICING_QUOTE_MAX — contact-зона.
 */
function pricing_calc_custom(PDO $pdo, int $tokens): array {
    if ($tokens <= 0) {
        return ['price_rub' => 0.0, 'per_1m' => 0.0, 'tier' => null, 'contact' => false];
    }
    if ($tokens > PRICING_QUOTE_MAX) {
        return [
            'price_rub'   => null,
            'per_1m'      => null,
            'tier'        => null,
            'contact'     => true,
            'contact_url' => PRICING_CONTACT_TG,
        ];
    }
    $anchors = pricing_anchors($pdo);
    if (empty($anchors)) {
        $per = PRICING_FALLBACK_PER1M;
        return [
            'price_rub' => round($per * $tokens / 1000000.0, 2),
            'per_1m'    => $per,
            'tier'      => null,
            'contact'   => false,
        ];
    }
    // Floor: первый anchor по умолчанию (для tokens < first.tokens)
    $tier = $anchors[0];
    foreach ($anchors as $a) {
        if ($tokens >= $a['tokens']) $tier = $a;
    }
    $per = $tier['per_1m'];
    $price = round($per * $tokens / 1000000.0, 2);

    // Кламп: custom не может стоить дороже ближайшего следующего пакета.
    // Иначе 24M @ Floor(10M, 19₽) = 456₽, а пакет 25M = 290₽ — выглядит сломанно.
    $nextAnchor = null;
    foreach ($anchors as $a) {
        if ($a['tokens'] > $tokens) { $nextAnchor = $a; break; }
    }
    if ($nextAnchor && $price > $nextAnchor['price_rub']) {
        $price = (float)$nextAnchor['price_rub'];
        $per   = $price / ($tokens / 1000000.0);
    }

    return [
        'price_rub' => $price,
        'per_1m'    => round($per, 4),
        'tier'      => [
            'tokens'    => $tier['tokens'],
            'per_1m'    => round($tier['per_1m'], 4),
            'price_rub' => $tier['price_rub'],
        ],
        'contact' => false,
    ];
}

/** Диапазон для ползунка + точки информации фронту. */
function pricing_custom_range(PDO $pdo): array {
    $row = $pdo->query("SELECT MIN(min_custom_tokens) AS mn
                        FROM products WHERE allow_custom = 1 AND is_visible = 1")->fetch();
    return [
        'min'         => (int)($row['mn'] ?? 1000000),
        'max'         => PRICING_SLIDER_MAX,
        'quote_max'   => PRICING_QUOTE_MAX,
        'contact_url' => PRICING_CONTACT_TG,
    ];
}
