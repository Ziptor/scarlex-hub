<?php
/**
 * Минимальный sanitizer HTML для news_posts.body.
 * Whitelist тегов: b, strong, i, em, u, br, p, a, ul, ol, li, code, h3, h4.
 * Атрибуты: a[href|target|rel].
 */

function news_sanitize_html(string $html): string {
    $allowed = ['b','strong','i','em','u','br','p','a','ul','ol','li','code','h3','h4'];
    // Удаляем скрипты/стили целиком
    $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#</?(script|style|iframe|object|embed)[^>]*>#i', '', $html);
    // on*= атрибуты
    $html = preg_replace('#\s+on[a-z]+\s*=\s*"[^"]*"#i', '', $html);
    $html = preg_replace("#\s+on[a-z]+\s*=\s*'[^']*'#i", '', $html);
    // javascript: в href
    $html = preg_replace('#href\s*=\s*"javascript:[^"]*"#i', 'href="#"', $html);
    $html = preg_replace("#href\s*=\s*'javascript:[^']*'#i", "href='#'", $html);

    // Удаляем все теги, не входящие в whitelist
    $allowedTags = '<' . implode('><', $allowed) . '>';
    $html = strip_tags($html, $allowedTags);

    // Для <a> оставляем только href/target/rel; добавляем rel="noopener"
    $html = preg_replace_callback('#<a\b([^>]*)>#i', function ($m) {
        $attrs = $m[1];
        $href = '';
        if (preg_match('#href\s*=\s*"([^"]*)"#i', $attrs, $h)) $href = $h[1];
        elseif (preg_match("#href\s*=\s*'([^']*)'#i", $attrs, $h)) $href = $h[1];
        $href = htmlspecialchars($href, ENT_QUOTES | ENT_HTML5);
        return '<a href="' . $href . '" target="_blank" rel="noopener nofollow">';
    }, $html);

    return trim($html);
}
