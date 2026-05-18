<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

function get_prefs($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT * FROM user_prefs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        $stmt = $pdo->prepare('INSERT INTO user_prefs (user_id) VALUES (?)');
        $stmt->execute([$userId]);
        return ['notify_login' => 1, 'notify_purchase' => 1, 'notify_low_balance' => 1, 'low_balance_threshold' => 10,
                'notify_key_exhausted' => 1, 'notify_referral' => 1, 'notify_achievement' => 1, 'notify_daily_report' => 1,
                'notify_daily_report_time' => '10:00'];
    }
    if (!isset($row['low_balance_threshold'])) $row['low_balance_threshold'] = 10;
    if (!isset($row['notify_daily_report_time']) || !$row['notify_daily_report_time']) $row['notify_daily_report_time'] = '10:00';
    return $row;
}

function parse_ua($ua) {
    if (!$ua) return 'Неизвестно';
    if (preg_match('/Edg\//', $ua)) return 'Edge';
    if (preg_match('/Chrome\//', $ua) && !preg_match('/Edg|OPR/', $ua)) return 'Chrome';
    if (preg_match('/Firefox\//', $ua)) return 'Firefox';
    if (preg_match('/Safari\//', $ua) && !preg_match('/Chrome/', $ua)) return 'Safari';
    if (preg_match('/OPR\//', $ua)) return 'Opera';
    return 'Браузер';
}

function parse_os($ua) {
    if (preg_match('/Windows NT/', $ua)) return 'Windows';
    if (preg_match('/Mac OS X/', $ua)) return 'macOS';
    if (preg_match('/Android/', $ua)) return 'Android';
    if (preg_match('/iPhone|iPad/', $ua)) return 'iOS';
    if (preg_match('/Linux/', $ua)) return 'Linux';
    return 'Неизвестно';
}

if ($action === 'get_all') {
    $stmt = $pdo->prepare('SELECT id, email, username FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT id, ip, user_agent, created_at, last_seen FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_seen DESC');
    $stmt->execute([$userId]);
    $sessions = [];
    foreach ($stmt->fetchAll() as $row) {
        $sessions[] = [
            'id' => $row['id'],
            'is_current' => $row['id'] === session_id(),
            'browser' => parse_ua($row['user_agent']),
            'os' => parse_os($row['user_agent']),
            'ip' => $row['ip'],
            'created_at' => $row['created_at'],
            'last_seen' => $row['last_seen']
        ];
    }

    echo json_encode([
        'ok' => true,
        'user' => $user,
        'sessions' => $sessions,
        'prefs' => get_prefs($pdo, $userId)
    ]);
    exit;
}

if ($action === 'change_password') {
    $current = $input['current_password'] ?? '';
    $new = $input['new_password'] ?? '';
    $confirm = $input['confirm_password'] ?? '';

    if (!$current || !$new) {
        echo json_encode(['ok' => false, 'error' => 'Заполните все поля']);
        exit;
    }
    if (strlen($new) < 6) {
        echo json_encode(['ok' => false, 'error' => 'Пароль минимум 6 символов']);
        exit;
    }
    if ($new !== $confirm) {
        echo json_encode(['ok' => false, 'error' => 'Пароли не совпадают']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row['password_hash'])) {
        echo json_encode(['ok' => false, 'error' => 'Неверный текущий пароль']);
        exit;
    }

    $hash = password_hash($new, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$hash, $userId]);

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'change_email') {
    $newEmail = trim($input['new_email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$newEmail || !$password) {
        echo json_encode(['ok' => false, 'error' => 'Заполните все поля']);
        exit;
    }
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Некорректный email']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        echo json_encode(['ok' => false, 'error' => 'Неверный пароль']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$newEmail, $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Email уже используется']);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
    $stmt->execute([$newEmail, $userId]);

    echo json_encode(['ok' => true, 'new_email' => $newEmail]);
    exit;
}

if ($action === 'revoke_session') {
    $sid = $input['session_id'] ?? '';
    if (!$sid || $sid === session_id()) {
        echo json_encode(['ok' => false, 'error' => 'Невозможно завершить эту сессию']);
        exit;
    }
    // Soft-revoke: keep audit trail, force re-login on next check_session.php
    $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND revoked_at IS NULL');
    $stmt->execute([$sid, $userId]);
    echo json_encode(['ok' => true, 'revoked' => $stmt->rowCount()]);
    exit;
}

if ($action === 'revoke_all_other') {
    $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND id != ? AND revoked_at IS NULL');
    $stmt->execute([$userId, session_id()]);
    echo json_encode(['ok' => true, 'revoked' => $stmt->rowCount()]);
    exit;
}

if ($action === 'update_prefs') {
    get_prefs($pdo, $userId); // ensure row exists

    // Partial update — only update fields actually present in input.
    // This prevents toggling one preference from resetting all others.
    $boolFields = [
        'notify_login', 'notify_purchase', 'notify_low_balance',
        'notify_key_exhausted', 'notify_referral', 'notify_achievement', 'notify_daily_report'
    ];
    $sets = [];
    $vals = [];
    foreach ($boolFields as $f) {
        if (array_key_exists($f, $input)) {
            $sets[] = "$f = ?";
            $vals[] = !empty($input[$f]) ? 1 : 0;
        }
    }
    if (array_key_exists('low_balance_threshold', $input)) {
        $sets[] = 'low_balance_threshold = ?';
        $vals[] = max(1, min(90, (int)$input['low_balance_threshold']));
    }
    if (array_key_exists('notify_daily_report_time', $input)) {
        $t = trim((string)$input['notify_daily_report_time']);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) {
            $sets[] = 'notify_daily_report_time = ?';
            $vals[] = $t; // store as HH:MM (VARCHAR(5))
        } else {
            echo json_encode(['ok' => false, 'error' => 'Неверный формат времени (HH:MM)']);
            exit;
        }
    }

    if (!$sets) {
        echo json_encode(['ok' => false, 'error' => 'Нечего обновлять']);
        exit;
    }

    $vals[] = $userId;
    $sql = 'UPDATE user_prefs SET ' . implode(', ', $sets) . ' WHERE user_id = ?';
    $pdo->prepare($sql)->execute($vals);

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
