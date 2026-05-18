<?php
// Helper: send Telegram message to a user via bot HTTP API.
// Used by admin actions (give_balance) and other backend-side notifications.
// Best-effort: never throws, returns true/false.

require_once __DIR__ . '/secrets.php';

function tg_notify_user(int $tgId, string $text, string $parseMode = 'HTML'): bool {
    if ($tgId <= 0 || $text === '') return false;
    $ch = curl_init('https://api.telegram.org/bot' . TG_BOT_TOKEN . '/sendMessage');
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'chat_id' => $tgId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true,
        ]),
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ];
    if (TG_PROXY_URL) {
        $opts[CURLOPT_PROXY] = TG_PROXY_URL;
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    curl_close($ch);
    if ($resp === false) return false;
    $d = json_decode($resp, true);
    return is_array($d) && !empty($d['ok']);
}

/**
 * Add a persistent in-site notification (visible in dashboard).
 * Requires a `notifications` table created in db.php.
 */
function notify_user(\PDO $pdo, int $userId, string $body, ?string $title = null, string $type = 'info'): void {
    if ($userId <= 0 || $body === '') return;
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, body, type) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $title, $body, $type]);
    } catch (Exception $e) {
        // ignore
    }
}

/**
 * High-level event-based TG alert with preference check + dedup.
 *
 * Event types:
 *   - 'low_balance'        : 1/day max per user
 *   - 'payment_received'   : every payment, no dedup
 *   - 'key_exhausted'      : 1/key max
 *   - 'key_frozen'         : 1/key max
 *   - 'new_referral'       : every signup
 *   - 'achievement_unlocked': 1/achievement_id max
 *
 * @param int    $userId       Internal user id
 * @param string $event        Event type (above)
 * @param string $text         HTML message body
 * @param string|null $refId   Reference for dedup (e.g. key_id, achievement_id)
 * @param int    $dedupSecs    Dedup window in seconds (0 = no dedup, default 86400 for 'low_balance')
 * @return bool                True if message was sent, false otherwise
 */
function tg_alert(\PDO $pdo, int $userId, string $event, string $text, ?string $refId = null, int $dedupSecs = 0): bool {
    if ($userId <= 0 || $text === '') return false;

    // Map event to preference column (NULL = always send)
    $prefMap = [
        'low_balance'         => 'notify_low_balance',
        'payment_received'    => 'notify_purchase',
        'key_exhausted'       => 'notify_key_exhausted',
        'key_frozen'          => 'notify_key_exhausted',
        'new_referral'        => 'notify_referral',
        'achievement_unlocked'=> 'notify_achievement',
        'daily_report'        => 'notify_daily_report',
    ];

    // Fetch user + prefs in one go
    try {
        $stmt = $pdo->prepare(
            'SELECT u.telegram_id, p.notify_login, p.notify_purchase, p.notify_low_balance,
                    p.notify_key_exhausted, p.notify_referral, p.notify_achievement, p.notify_daily_report
             FROM users u
             LEFT JOIN user_prefs p ON p.user_id = u.id
             WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
    if (!$row || empty($row['telegram_id'])) return false;

    // Preference check (default ON if column missing/null)
    if (isset($prefMap[$event])) {
        $col = $prefMap[$event];
        $val = array_key_exists($col, $row) ? $row[$col] : 1;
        if ($val !== null && (int)$val === 0) return false;
    }

    // Dedup window
    if ($dedupSecs <= 0) {
        $defaults = [
            'low_balance' => 86400,           // 24h
            'key_exhausted' => 30 * 86400,    // ~once per key
            'key_frozen' => 30 * 86400,
            'achievement_unlocked' => 365 * 86400,
            'daily_report' => 12 * 3600,      // safety: not twice in 12h
        ];
        $dedupSecs = $defaults[$event] ?? 0;
    }

    if ($dedupSecs > 0) {
        try {
            if ($refId !== null && $refId !== '') {
                $chk = $pdo->prepare(
                    'SELECT id FROM notifications_sent
                     WHERE user_id = ? AND event_type = ? AND ref_id = ?
                       AND sent_at >= (NOW() - INTERVAL ? SECOND)
                     LIMIT 1'
                );
                $chk->execute([$userId, $event, $refId, $dedupSecs]);
            } else {
                $chk = $pdo->prepare(
                    'SELECT id FROM notifications_sent
                     WHERE user_id = ? AND event_type = ?
                       AND sent_at >= (NOW() - INTERVAL ? SECOND)
                     LIMIT 1'
                );
                $chk->execute([$userId, $event, $dedupSecs]);
            }
            if ($chk->fetchColumn()) return false;
        } catch (Exception $e) { /* table missing? send anyway */ }
    }

    $ok = tg_notify_user((int)$row['telegram_id'], $text);

    if ($ok) {
        try {
            $pdo->prepare('INSERT INTO notifications_sent (user_id, event_type, ref_id) VALUES (?, ?, ?)')
                ->execute([$userId, $event, $refId]);
        } catch (Exception $e) { /* best-effort */ }
    }
    return $ok;
}
