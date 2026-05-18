<?php
// Get/set notification preferences (currently: daily_report enabled + time slot).
// Body:
//   GET  { telegram_id }
//   SET  { telegram_id, daily_report_enabled?: 0|1, daily_report_time?: "HH:MM" }
//
// Time must be a 30-minute slot: HH:00 or HH:30 (00:00–23:30).
header('Content-Type: application/json');
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../db.php';

bot_auth_or_die();
$in = bot_input();

$tgId = (int)($in['telegram_id'] ?? 0);
if (!$tgId) bot_err('telegram_id required');

$stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = ?');
$stmt->execute([$tgId]);
$u = $stmt->fetch();
if (!$u) bot_err('User not found', 404);
$userId = (int)$u['id'];

// Ensure a user_prefs row exists (defaults from schema).
$pdo->prepare("INSERT IGNORE INTO user_prefs (user_id) VALUES (?)")->execute([$userId]);

$updates = [];
$params = [];

if (array_key_exists('daily_report_enabled', $in)) {
    $updates[] = 'notify_daily_report = ?';
    $params[] = (int)(bool)$in['daily_report_enabled'];
}

if (array_key_exists('daily_report_time', $in)) {
    $time = trim((string)$in['daily_report_time']);
    // Validate HH:MM and round to 30-minute slot
    if (!preg_match('/^([0-1]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
        bot_err('Invalid time format (HH:MM)');
    }
    $h = (int)$m[1];
    $mi = ((int)$m[2] < 30) ? 0 : 30;
    $time = sprintf('%02d:%02d', $h, $mi);
    $updates[] = 'notify_daily_report_time = ?';
    $params[] = $time;
}

if ($updates) {
    $params[] = $userId;
    $pdo->prepare('UPDATE user_prefs SET ' . implode(', ', $updates) . ' WHERE user_id = ?')->execute($params);
}

$stmt = $pdo->prepare(
    'SELECT COALESCE(notify_daily_report, 1) AS daily_report_enabled,
            COALESCE(notify_daily_report_time, "10:00") AS daily_report_time
     FROM user_prefs WHERE user_id = ?'
);
$stmt->execute([$userId]);
$row = $stmt->fetch();

bot_ok([
    'daily_report_enabled' => (int)($row['daily_report_enabled'] ?? 1),
    'daily_report_time'    => $row['daily_report_time'] ?? '10:00',
]);
