<?php
// Shared-secret HTTP authentication for bot endpoints.
// Bot must send X-Bot-Secret header on every request.
// CryptoBot webhook is exempt (uses its own signature).

define('BOT_SHARED_SECRET', 'sx_bot_2K9pQrYvN8xLmF7tJzW3eHcVbUaR5sDgM4nXkPjT6oIyQwBeS1zHfA0u');

function bot_auth_or_die() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $secret = $headers['X-Bot-Secret'] ?? $headers['x-bot-secret'] ?? $_SERVER['HTTP_X_BOT_SECRET'] ?? '';
    if (!hash_equals(BOT_SHARED_SECRET, (string)$secret)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function bot_input() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

function bot_ok($extra = []) {
    echo json_encode(array_merge(['ok' => true], $extra));
    exit;
}

function bot_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}
