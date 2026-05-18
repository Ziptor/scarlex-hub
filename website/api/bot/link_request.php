<?php
// Generate a 6-digit link code for TG → website account linking,
// OR consume a deep-link token (?start=link_<token>) to link the site account directly.
//
// Body: { telegram_id, telegram_username?, token? }
// Returns (token flow):   { ok:true, mode:"deep_link", linked:true, email, username, merged }
// Returns (code flow):    { ok:true, mode:"code", code, expires_in }
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../_link_tokens.php';

bot_auth_or_die();
$in         = bot_input();
$tgId       = (int)($in['telegram_id'] ?? 0);
$tgUsername = trim((string)($in['telegram_username'] ?? ''));
$tgFirst    = trim((string)($in['telegram_first_name'] ?? ''));
$token      = trim((string)($in['token'] ?? ''));
if (!$tgId) bot_err('telegram_id required');

// ── Deep-link flow ──────────────────────────────────────────────
if ($token !== '') {
    $userId = consume_link_token($pdo, $token, 'tg_link');
    if (!$userId) bot_err('Ссылка устарела или уже использована. Сгенерируй новую в профиле сайта.', 410);

    // Reject if the Telegram is already attached to a different user (avoid silent take-over).
    $stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ? AND id != ? LIMIT 1');
    $stmt->execute([$tgId, $userId]);
    $conflict = $stmt->fetch();
    if ($conflict) {
        bot_err('Этот Telegram уже привязан к другому аккаунту. Сначала отвяжи его в профиле сайта.', 409);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            "UPDATE users SET telegram_id = ?,
                              telegram_username = COALESCE(NULLIF(?, ''), telegram_username),
                              tg_first_name     = COALESCE(NULLIF(?, ''), tg_first_name)
             WHERE id = ?"
        )->execute([$tgId, $tgUsername, $tgFirst, $userId]);
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log('link_request deep_link error: ' . $e->getMessage());
        bot_err('Ошибка БД', 500);
    }

    $u = $pdo->prepare('SELECT email, username FROM users WHERE id = ? LIMIT 1');
    $u->execute([$userId]);
    $info = $u->fetch() ?: [];
    bot_ok([
        'mode'     => 'deep_link',
        'linked'   => true,
        'user_id'  => $userId,
        'email'    => $info['email']    ?? '',
        'username' => $info['username'] ?? '',
    ]);
}

// ── Legacy 6-digit code flow ────────────────────────────────────
$code = null;
for ($i = 0; $i < 10; $i++) {
    $candidate = (string)random_int(100000, 999999);
    $stmt = $pdo->prepare("SELECT id FROM bot_link_codes WHERE code = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$candidate]);
    if (!$stmt->fetch()) { $code = $candidate; break; }
}
if (!$code) bot_err('Failed to generate code', 500);

// Invalidate previous unused codes for this TG id
$pdo->prepare("UPDATE bot_link_codes SET used = 1 WHERE telegram_id = ? AND used = 0")->execute([$tgId]);

$pdo->prepare("INSERT INTO bot_link_codes (code, telegram_id, telegram_username, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))")
    ->execute([$code, $tgId, $tgUsername ?: null]);

bot_ok(['mode' => 'code', 'code' => $code, 'expires_in' => 900]);

