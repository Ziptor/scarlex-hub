<?php
// Публичный JSON товаров для лендинга (без авторизации).
header('Content-Type: application/json');
header('Cache-Control: public, max-age=60');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pricing.php';

try {
    $rows = $pdo->query("SELECT * FROM products WHERE is_visible = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    $products = [];
    foreach ($rows as $r) {
        $tokens = (int)$r['tokens'];
        $price  = (float)$r['price_rub'];
        $per1m  = $tokens > 0 ? round($price / ($tokens / 1000000.0), 2) : 0.0;
        $products[] = [
            'slug'         => $r['slug'],
            'name'         => $r['name'],
            'tokens'       => $tokens,
            'price_rub'    => round($price, 2),
            'price_per_1m' => $per1m,
            'badge'        => $r['badge'] ?? null,
            'is_trial'     => (int)$r['is_trial'] === 1,
            'allow_custom' => (int)$r['allow_custom'] === 1,
        ];
    }
    $range = pricing_custom_range($pdo);
    echo json_encode(['ok' => true, 'products' => $products, 'custom' => $range]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'db']);
}
