<?php
// Scarlex secrets template.
// 1. Copy this file to `secrets.php` in the same directory.
// 2. Fill in real values.
// 3. Make sure `secrets.php` is in .gitignore and not served by webserver
//    (already protected by api/.htaccess).
//
// All other PHP files require this via:  require_once __DIR__ . '/secrets.php';

// ── Database (MySQL) ──
if (!defined('DB_HOST'))       define('DB_HOST', 'localhost');
if (!defined('DB_NAME'))       define('DB_NAME', 'your_db_name');
if (!defined('DB_USER'))       define('DB_USER', 'your_db_user');
if (!defined('DB_PASS'))       define('DB_PASS', 'your_db_password');

// ── Reseller API (LEGACY, ress.stepanovikov.uno) — оставлено для совместимости со старыми ключами ──
if (!defined('RESELLER_API_URL'))   define('RESELLER_API_URL', 'https://ress.stepanovikov.uno/api');
if (!defined('RESELLER_API_TOKEN')) define('RESELLER_API_TOKEN', 'rat_xxxxxxxxxxxxxxxxxxxxxxxx');

// ── Scarlex proxy (api.scarlex.ru) — текущий бэкенд генерации ключей ──
if (!defined('SCARLEX_API_URL'))   define('SCARLEX_API_URL', 'https://api.scarlex.ru');
if (!defined('SCARLEX_ADMIN_KEY')) define('SCARLEX_ADMIN_KEY', 'adm-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// ── Upstream chat API ──
if (!defined('UPSTREAM_API_URL'))   define('UPSTREAM_API_URL', 'https://api.stepanovikov.uno');

// ── Telegram bot ──
if (!defined('TG_BOT_TOKEN'))  define('TG_BOT_TOKEN', '0000000000:AAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
if (!defined('TG_PROXY_URL'))  define('TG_PROXY_URL', '');

// ── YooMoney ──
if (!defined('YOOMONEY_WALLET'))             define('YOOMONEY_WALLET', '4100000000000000');
if (!defined('YOOMONEY_NOTIFICATION_SECRET')) define('YOOMONEY_NOTIFICATION_SECRET', 'xxxxxxxxxxxxxxxxxxxxxx');

// ── Internal cron token (for usage_log.php and other cron-only endpoints) ──
// Generate: php -r 'echo bin2hex(random_bytes(32));'
if (!defined('CRON_TOKEN')) define('CRON_TOKEN', 'CHANGE_ME_GENERATE_RANDOM_64_HEX_CHARS');

// ── Google reCAPTCHA v3 (registration anti-spam) ──
// Get at: https://www.google.com/recaptcha/admin/
if (!defined('RECAPTCHA_SITE_KEY'))   define('RECAPTCHA_SITE_KEY',   '6LeXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
if (!defined('RECAPTCHA_SECRET_KEY')) define('RECAPTCHA_SECRET_KEY', '6LeXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
