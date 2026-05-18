<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/_referrals_helpers.php';

// Ensure rate-limit table exists (idempotent, runs once per request — cheap)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        identifier VARCHAR(255) DEFAULT NULL,
        success TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_la_ip_time (ip, created_at),
        INDEX idx_la_id_time (identifier, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* best-effort */ }

function login_rate_check(\PDO $pdo, string $ip, string $identifier): ?string {
    // Window: 15 minutes. Limits: 8 fails/IP, 5 fails/identifier.
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= 8) return 'Слишком много попыток входа с этого IP. Попробуйте через 15 минут.';

        if ($identifier !== '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND success = 0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
            $stmt->execute([$identifier]);
            if ((int)$stmt->fetchColumn() >= 5) return 'Слишком много неудачных попыток для этого аккаунта. Попробуйте через 15 минут.';
        }
    } catch (Exception $e) { /* best-effort, do not block on infra error */ }
    return null;
}

function login_record_attempt(\PDO $pdo, string $ip, string $identifier, bool $success): void {
    try {
        $pdo->prepare("INSERT INTO login_attempts (ip, identifier, success) VALUES (?, ?, ?)")
            ->execute([$ip, $identifier, $success ? 1 : 0]);
    } catch (Exception $e) { /* best-effort */ }
}

function record_session($pdo, $userId) {
    $sid = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $stmt = $pdo->prepare('SELECT id FROM user_sessions WHERE id = ?');
    $stmt->execute([$sid]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare('UPDATE user_sessions SET last_seen = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$sid]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO user_sessions (id, user_id, ip, user_agent) VALUES (?, ?, ?, ?)');
        $stmt->execute([$sid, $userId, $ip, $ua]);
    }
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'register') {
    $email = trim($input['email'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $confirm = $input['password_confirm'] ?? '';

    if (!$email || !$username || !$password) {
        echo json_encode(['ok' => false, 'error' => 'Заполните все поля']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Некорректный email']);
        exit;
    }

    // Check email was verified
    if (empty($_SESSION['verified_email']) || $_SESSION['verified_email'] !== $email) {
        echo json_encode(['ok' => false, 'error' => 'Email не подтверждён']);
        exit;
    }

    if (strlen($username) < 3 || strlen($username) > 50) {
        echo json_encode(['ok' => false, 'error' => 'Имя пользователя: 3-50 символов']);
        exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(['ok' => false, 'error' => 'Пароль минимум 6 символов']);
        exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['ok' => false, 'error' => 'Пароли не совпадают']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Email или имя пользователя уже заняты']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $publicId = generate_public_id($pdo);
    $stmt = $pdo->prepare('INSERT INTO users (email, username, password_hash, public_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $username, $hash, $publicId]);

    $userId = $pdo->lastInsertId();

    // ── Referral capture: bind referrer if a code was passed during signup ──
    $refCode = trim($input['ref_code'] ?? ($_SESSION['pending_ref_code'] ?? ''));
    if ($refCode !== '') {
        $referrerId = lookup_referrer_by_code($pdo, $refCode, (int)$userId);
        if ($referrerId) {
            attach_referrer($pdo, (int)$userId, $referrerId);
            // Pay signup bonus immediately — email is already verified at this point
            credit_referral_signup_bonus($pdo, (int)$userId);
        }
        unset($_SESSION['pending_ref_code']);
    }

    // Generate referral code for the new user up-front
    try { ensure_referral_code($pdo, (int)$userId); } catch (Exception $e) {}

    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    unset($_SESSION['verified_email']);
    record_session($pdo, $userId);

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'login') {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['ok' => false, 'error' => 'Заполните все поля']);
        exit;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $rateError = login_rate_check($pdo, $clientIp, strtolower($email));
    if ($rateError !== null) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => $rateError]);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash, is_banned, ban_reason FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        login_record_attempt($pdo, $clientIp, strtolower($email), false);
        echo json_encode(['ok' => false, 'error' => 'Неверный email или пароль']);
        exit;
    }

    if (!empty($user['is_banned'])) {
        login_record_attempt($pdo, $clientIp, strtolower($email), false);
        $reason = $user['ban_reason'] ?: 'Нарушение правил сервиса';
        echo json_encode(['ok' => false, 'error' => 'Ваша учётная запись заблокирована. Причина: ' . $reason . '. Свяжитесь с администратором: @ruapi_support']);
        exit;
    }

    login_record_attempt($pdo, $clientIp, strtolower($email), true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    record_session($pdo, $user['id']);

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'logout') {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('DELETE FROM user_sessions WHERE id = ?');
        $stmt->execute([session_id()]);
    }
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
