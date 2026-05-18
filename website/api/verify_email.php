<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/secrets.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// ── reCAPTCHA v3 verification helper ──
function verify_recaptcha($token, $expected_action, $min_score = 0.5) {
    if (!$token || !defined('RECAPTCHA_SECRET_KEY') || RECAPTCHA_SECRET_KEY === '') {
        return ['ok' => false, 'error' => 'reCAPTCHA token missing'];
    }
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    if (!$data || empty($data['success'])) {
        return ['ok' => false, 'error' => 'reCAPTCHA failed'];
    }
    if (!empty($data['action']) && $data['action'] !== $expected_action) {
        return ['ok' => false, 'error' => 'reCAPTCHA action mismatch'];
    }
    if (isset($data['score']) && $data['score'] < $min_score) {
        return ['ok' => false, 'error' => 'reCAPTCHA score too low', 'score' => $data['score']];
    }
    return ['ok' => true, 'score' => $data['score'] ?? null];
}

// ── Send verification code ──
if ($action === 'send_code') {
    $email = trim($input['email'] ?? '');

    // Honeypot trap: hidden form field "website".
    // Real users never see/fill it. Bots auto-fill all inputs.
    // We return success-shaped response but DO NOT create email_codes record
    // — bot thinks code was sent but never receives anything actionable.
    $hp = trim((string)($input['website'] ?? ''));
    if ($hp !== '') {
        // Log for monitoring (errors_log is silent — won't reveal trap)
        @error_log(sprintf('[honeypot] hit on send_code: ip=%s email=%s hp=%s',
            $_SERVER['REMOTE_ADDR'] ?? '?', $email, substr($hp, 0, 80)));
        // Fake success with random delay to mimic real flow
        usleep(random_int(200000, 800000)); // 200-800ms
        echo json_encode(['ok' => true, 'sent' => true]);
        exit;
    }

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Некорректный email']);
        exit;
    }

    // reCAPTCHA v3 check (only for send_code — registration anti-spam)
    $captcha = verify_recaptcha($input['recaptcha_token'] ?? '', 'send_code', 0.5);
    if (!$captcha['ok']) {
        echo json_encode(['ok' => false, 'error' => 'Проверка reCAPTCHA не пройдена. Перезагрузите страницу.']);
        exit;
    }

    // Check if email already registered
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Этот email уже зарегистрирован']);
        exit;
    }

    // Rate limit: max 1 code per 60s per email
    $stmt = $pdo->prepare("SELECT created_at FROM email_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    $last = $stmt->fetch();
    if ($last) {
        $diff = time() - strtotime($last['created_at']);
        if ($diff < 60) {
            echo json_encode(['ok' => false, 'error' => 'Подождите ' . (60 - $diff) . ' сек перед повторной отправкой']);
            exit;
        }
    }

    // Generate 6-digit code
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 min

    $stmt = $pdo->prepare('INSERT INTO email_codes (email, code, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$email, $code, $expiresAt]);

    // Build branded HTML email
    $subject_raw = 'Scarlex — код подтверждения ' . $code;
    $subject = '=?UTF-8?B?' . base64_encode($subject_raw) . '?=';

    $code_html = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $email_esc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $year = date('Y');

    $html_body = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Scarlex — Код подтверждения</title>
</head>
<body style="margin:0;padding:0;background:#0a0a0a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#f4f4f4;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#0a0a0a;padding:40px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="560" style="max-width:560px;width:100%;background:#0e0e0e;border:1px solid #1f1f1f;border-radius:18px;overflow:hidden;">
          <!-- Logo / header -->
          <tr>
            <td align="center" style="padding:36px 32px 26px;border-bottom:1px solid #1f1f1f;background:linear-gradient(180deg,#101010 0%,#0e0e0e 100%);">
              <img src="https://scarlex.ru/assets/scarlex-logo.png" alt="SCARLEX" width="160" height="auto" style="display:block;width:160px;max-width:160px;height:auto;border:0;outline:none;text-decoration:none;margin:0 auto;">
              <div style="margin-top:10px;font-size:11px;letter-spacing:.2em;color:#6a6a6a;text-transform:uppercase;font-family:'JetBrains Mono',Consolas,monospace;">Claude proxy</div>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px 36px 24px;">
              <h1 style="margin:0 0 12px;font-size:24px;font-weight:600;letter-spacing:-.02em;color:#ffffff;line-height:1.2;">Подтверди свой email</h1>
              <p style="margin:0 0 28px;font-size:15px;line-height:1.55;color:#a8a8a8;">
                Ты регистрируешься на <strong style="color:#f4f4f4;">scarlex.ru</strong> с адреса <strong style="color:#f4f4f4;">{$email_esc}</strong>. Введи код ниже, чтобы продолжить.
              </p>

              <!-- Code display -->
              <div style="margin:0 0 28px;padding:24px;background:#0a0a0a;border:1px solid #2a2a2a;border-radius:14px;text-align:center;">
                <div style="font-size:11px;letter-spacing:.18em;color:#6a6a6a;text-transform:uppercase;margin-bottom:14px;font-family:'JetBrains Mono',Consolas,monospace;">Код подтверждения</div>
                <div style="font-family:'JetBrains Mono',Consolas,monospace;font-size:42px;font-weight:700;letter-spacing:.18em;color:#ffffff;line-height:1;">{$code_html}</div>
                <div style="margin-top:14px;font-size:12px;color:#6a6a6a;font-family:'JetBrains Mono',Consolas,monospace;letter-spacing:.04em;">Действителен 10 минут</div>
              </div>

              <p style="margin:0 0 8px;font-size:13px;line-height:1.55;color:#6a6a6a;">
                Не запрашивал код? Проигнорируй это письмо — никаких действий не требуется.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 36px 28px;border-top:1px solid #1f1f1f;background:#080808;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td style="font-size:11px;line-height:1.6;color:#3f3f3f;font-family:'JetBrains Mono',Consolas,monospace;letter-spacing:.04em;">
                    © {$year} Scarlex · OpenAI-совместимый прокси к Claude<br>
                    <a href="https://scarlex.ru" style="color:#6a6a6a;text-decoration:none;">scarlex.ru</a> · <a href="https://scarlex.ru/terms.html" style="color:#6a6a6a;text-decoration:none;">Условия</a> · <a href="https://t.me/scarlex_bot" style="color:#6a6a6a;text-decoration:none;">@scarlex_bot</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    // Plain-text fallback for clients that don't render HTML
    $text_body = "Ваш код подтверждения Scarlex: $code\n\n"
        . "Код действителен 10 минут.\n"
        . "Если вы не запрашивали этот код, проигнорируйте это письмо.\n\n"
        . "© Scarlex — scarlex.ru";

    // Multipart MIME (text + html)
    $boundary = '=_scarlex_' . bin2hex(random_bytes(8));
    $body_mime = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $text_body . "\r\n\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $html_body . "\r\n\r\n"
        . "--{$boundary}--";

    $headers = "From: Scarlex <noreply@scarlex.ru>\r\n"
        . "Reply-To: noreply@scarlex.ru\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n"
        . "X-Mailer: Scarlex/1.0\r\n";

    $sent = @mail($email, $subject, $body_mime, $headers);

    // Store in session for verification
    $_SESSION['email_verify'] = $email;
    $_SESSION['email_code'] = $code;

    echo json_encode(['ok' => true, 'sent' => $sent]);
    exit;
}

// ── Verify code ──
if ($action === 'verify_code') {
    $email = trim($input['email'] ?? '');
    $code = trim($input['code'] ?? '');

    if (!$email || !$code) {
        echo json_encode(['ok' => false, 'error' => 'Введите код']);
        exit;
    }

    // Check code in DB
    $stmt = $pdo->prepare("SELECT id, code FROM email_codes WHERE email = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || $row['code'] !== $code) {
        echo json_encode(['ok' => false, 'error' => 'Неверный или истёкший код']);
        exit;
    }

    // Mark used
    $pdo->prepare('UPDATE email_codes SET used = 1 WHERE id = ?')->execute([$row['id']]);

    // Mark email as verified in session
    $_SESSION['verified_email'] = $email;

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
