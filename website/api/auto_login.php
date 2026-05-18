<?php
// One-time auto-login from Telegram bot.
// Flow:
//   1. GET /api/auto_login.php?t=<token>
//      → render HTML form with pre-filled email, asks for password.
//   2. POST /api/auto_login.php  { t, password }
//      → if password matches user that owns token → set session, redirect to dashboard.
//
// Token is single-use and only confirms "this Telegram user owns the site account".
// Password confirmation guards against stolen/leaked deep-link URLs.

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_link_tokens.php';

function _peek_token(\PDO $pdo, string $token, string $kind): ?array {
    if ($token === '') return null;
    $stmt = $pdo->prepare(
        "SELECT lt.user_id, lt.expires_at, lt.used_at, u.email
         FROM link_tokens lt
         JOIN users u ON u.id = lt.user_id
         WHERE lt.token = ? AND lt.kind = ? LIMIT 1"
    );
    $stmt->execute([$token, $kind]);
    $row = $stmt->fetch();
    if (!$row) return null;
    if (!empty($row['used_at'])) return null;
    if (strtotime((string)$row['expires_at']) < time()) return null;
    return $row;
}

function _render_page(string $title, string $body, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . htmlspecialchars($title, ENT_QUOTES) . ' · Scarlex</title>'
       . '<link rel="icon" href="/assets/scarlex-logo.png">'
       . '<style>'
       . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
       . 'background:#0b0b10;color:#e7e7ea;font-family:-apple-system,Segoe UI,Roboto,sans-serif}'
       . '.card{background:#15151c;border:1px solid #26262e;border-radius:14px;padding:32px;'
       . 'width:min(380px,92vw);box-shadow:0 12px 40px rgba(0,0,0,.5)}'
       . 'h1{margin:0 0 4px;font-size:22px}'
       . 'p{margin:0 0 18px;color:#9a9aa6;font-size:14px;line-height:1.5}'
       . 'label{display:block;font-size:12px;color:#9a9aa6;margin:14px 0 6px}'
       . 'input{width:100%;padding:12px 14px;background:#0e0e14;border:1px solid #2a2a34;'
       . 'border-radius:10px;color:#fff;font-size:15px;box-sizing:border-box}'
       . 'input:focus{outline:none;border-color:#6a5acd}'
       . 'button{margin-top:18px;width:100%;padding:12px;background:#6a5acd;border:0;'
       . 'border-radius:10px;color:#fff;font-weight:600;font-size:15px;cursor:pointer}'
       . 'button:hover{background:#5a4abd}'
       . '.err{margin-top:14px;padding:10px 12px;background:#3a1f24;border:1px solid #5a2a30;'
       . 'border-radius:8px;color:#ffb4b4;font-size:13px}'
       . '.muted{color:#7a7a84;font-size:12px;text-align:center;margin-top:18px}'
       . '.muted a{color:#9b8dff;text-decoration:none}'
       . '</style></head><body><div class="card">' . $body . '</div></body></html>';
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $token = trim((string)($_GET['t'] ?? ''));
    $row = _peek_token($pdo, $token, 'site_login');
    if (!$row) {
        _render_page('Ссылка недействительна',
            '<h1>Ссылка устарела</h1>'
          . '<p>Сгенерируй новую ссылку в Telegram-боте <b>@scarlex_bot</b> → «🌐 Сайт».</p>'
          . '<p class="muted"><a href="/authorization.html">Войти обычным способом</a></p>',
            410);
    }
    $email = htmlspecialchars((string)$row['email'], ENT_QUOTES);
    _render_page('Подтверждение входа',
        '<h1>Вход через Telegram</h1>'
      . '<p>Введи пароль от аккаунта <b>' . $email . '</b>, чтобы открыть личный кабинет.</p>'
      . '<form method="POST" action="/api/auto_login.php">'
      . '<input type="hidden" name="t" value="' . htmlspecialchars($token, ENT_QUOTES) . '">'
      . '<label>Email</label><input type="email" value="' . $email . '" disabled>'
      . '<label>Пароль</label><input type="password" name="password" autofocus required>'
      . '<button type="submit">Войти</button>'
      . '</form>'
      . '<p class="muted">Ссылка одноразовая, действует 10 минут.</p>');
}

// POST
$token    = trim((string)($_POST['t'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($token === '' || $password === '') {
    _render_page('Ошибка',
        '<h1>Заполни пароль</h1>'
      . '<p>Пароль обязателен. Вернись и попробуй снова.</p>',
        400);
}

$row = _peek_token($pdo, $token, 'site_login');
if (!$row) {
    _render_page('Ссылка недействительна',
        '<h1>Ссылка устарела</h1>'
      . '<p>Сгенерируй новую ссылку в боте.</p>',
        410);
}

$userId = (int)$row['user_id'];
$u = $pdo->prepare('SELECT id, username, password_hash, is_banned, ban_reason FROM users WHERE id = ? LIMIT 1');
$u->execute([$userId]);
$user = $u->fetch();

if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
    $email = htmlspecialchars((string)$row['email'], ENT_QUOTES);
    _render_page('Неверный пароль',
        '<h1>Неверный пароль</h1>'
      . '<p>Аккаунт <b>' . $email . '</b>. Попробуй ещё раз.</p>'
      . '<form method="POST" action="/api/auto_login.php">'
      . '<input type="hidden" name="t" value="' . htmlspecialchars($token, ENT_QUOTES) . '">'
      . '<label>Пароль</label><input type="password" name="password" autofocus required>'
      . '<button type="submit">Войти</button>'
      . '</form>',
        401);
}

if (!empty($user['is_banned'])) {
    $reason = $user['ban_reason'] ?: 'Нарушение правил сервиса';
    _render_page('Доступ закрыт',
        '<h1>Аккаунт заблокирован</h1>'
      . '<p>Причина: ' . htmlspecialchars($reason, ENT_QUOTES) . '</p>'
      . '<p>Свяжись с поддержкой: <a href="https://t.me/ruapi_support">@ruapi_support</a></p>',
        403);
}

// Burn token + set session
consume_link_token($pdo, $token, 'site_login');
$_SESSION['user_id']  = (int)$user['id'];
$_SESSION['username'] = $user['username'];

// Best-effort: record_session if available
if (function_exists('record_session')) {
    try { record_session($pdo, (int)$user['id']); } catch (\Throwable $e) {}
}

header('Location: /dashboard/home.html');
exit;
