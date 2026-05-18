<?php
// Scarlex DB config
// Local: SQLite | Production: MySQL
// To switch to MySQL on hosting, uncomment the MySQL block and comment out SQLite

require_once __DIR__ . '/secrets.php';

$DB_MODE = 'mysql'; // 'sqlite' for local, 'mysql' for hosting

try {
    if ($DB_MODE === 'mysql') {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } else {
        $dbPath = __DIR__ . '/scarlex.db';
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL');
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('DB connection failed: ' . $e->getMessage());
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Auto-create tables
if ($DB_MODE === 'mysql') {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            public_id VARCHAR(10) DEFAULT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) DEFAULT NULL,
            telegram_id BIGINT DEFAULT NULL UNIQUE,
            telegram_username VARCHAR(100) DEFAULT NULL,
            balance DECIMAL(12,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Migrations
    try { $pdo->exec("ALTER TABLE users ADD COLUMN balance DECIMAL(12,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN public_id VARCHAR(10) DEFAULT NULL UNIQUE"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN ban_reason TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN free_test_key_claimed TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN low_balance_threshold INT DEFAULT 10"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN tg_first_name VARCHAR(128) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN tg_photo_url VARCHAR(512) DEFAULT NULL"); } catch (Exception $e) {}

    // Link tokens: one-time deep-link auth between site and Telegram bot
    //   kind = 'site_login' (bot -> site auto-login session)
    //        | 'tg_link'    (site -> bot, link telegram account by /start link_XXX)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS link_tokens (
            token       VARCHAR(64) PRIMARY KEY,
            user_id     INT NOT NULL,
            kind        VARCHAR(20) NOT NULL,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at  TIMESTAMP NOT NULL,
            used_at     TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_lt_user (user_id),
            INDEX idx_lt_kind_exp (kind, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Telegram-login pending sessions (deep-link sign-in: site → bot → site).
    //   status: 'pending' | 'confirmed' | 'consumed'
    //   user_id is set by the bot once user confirms.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tg_login_sessions (
            token            VARCHAR(64) PRIMARY KEY,
            php_session_id   VARCHAR(128) NOT NULL,
            user_id          INT DEFAULT NULL,
            status           VARCHAR(16) NOT NULL DEFAULT 'pending',
            created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at       TIMESTAMP NOT NULL,
            confirmed_at     TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_tls_status_exp (status, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Notifications (in-site, for dashboard popup)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            body TEXT NOT NULL,
            type VARCHAR(40) DEFAULT 'info',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notif_user (user_id),
            INDEX idx_notif_unread (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Backfill public_id for existing users
    $missing = $pdo->query("SELECT id FROM users WHERE public_id IS NULL OR public_id = ''")->fetchAll();
    foreach ($missing as $row) {
        for ($i = 0; $i < 10; $i++) {
            $pid = (string)random_int(10000, 99999);
            try {
                $pdo->prepare("UPDATE users SET public_id = ? WHERE id = ?")->execute([$pid, $row['id']]);
                break;
            } catch (Exception $e) { /* collision, retry */ }
        }
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id VARCHAR(64) PRIMARY KEY,
            user_id INT NOT NULL,
            ip VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            revoked_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_user (user_id),
            INDEX idx_user_active (user_id, revoked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    try { $pdo->exec("ALTER TABLE user_sessions ADD COLUMN revoked_at TIMESTAMP NULL DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("CREATE INDEX idx_user_active ON user_sessions(user_id, revoked_at)"); } catch (Exception $e) {}
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_prefs (
            user_id INT PRIMARY KEY,
            notify_login TINYINT(1) DEFAULT 1,
            notify_purchase TINYINT(1) DEFAULT 1,
            notify_low_balance TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    try { $pdo->exec("ALTER TABLE user_prefs ADD COLUMN low_balance_threshold INT DEFAULT 10"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE user_prefs ADD COLUMN notify_key_exhausted TINYINT(1) DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE user_prefs ADD COLUMN notify_referral TINYINT(1) DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE user_prefs ADD COLUMN notify_achievement TINYINT(1) DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE user_prefs ADD COLUMN notify_daily_report TINYINT(1) DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE user_prefs ADD COLUMN notify_daily_report_time VARCHAR(5) DEFAULT '10:00'"); } catch (Exception $e) {}

    // Public incidents log — shown on status.html. Created/resolved by admin via admin.php.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS incidents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            severity ENUM('minor','major','critical') DEFAULT 'minor',
            status ENUM('investigating','identified','monitoring','resolved') DEFAULT 'investigating',
            component VARCHAR(64) DEFAULT 'api',
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL DEFAULT NULL,
            created_by INT DEFAULT NULL,
            INDEX idx_inc_status (status, started_at DESC),
            INDEX idx_inc_started (started_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            key_value VARCHAR(255) NOT NULL UNIQUE,
            label VARCHAR(100) DEFAULT NULL,
            tag VARCHAR(20) DEFAULT 'occupied',
            tokens_limit BIGINT DEFAULT 0,
            used_tokens BIGINT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            source VARCHAR(20) DEFAULT 'imported',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_keys_user (user_id),
            INDEX idx_keys_tag (tag)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Migration: add tag column if missing
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN tag VARCHAR(20) DEFAULT 'occupied'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys MODIFY user_id INT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN reseller_key_id INT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN is_test TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN is_frozen TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN is_trial TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN product_id INT DEFAULT NULL"); } catch (Exception $e) {}
    // IP whitelist: JSON array of IPv4 / CIDR strings. NULL or '[]' = no restriction.
    try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN allowed_ips TEXT DEFAULT NULL"); } catch (Exception $e) {}
    // One-time fix: keys with tag='active' → 'occupied' (legacy)
    try { $pdo->exec("UPDATE api_keys SET tag='occupied' WHERE tag='active'"); } catch (Exception $e) {}
    // One-time fix: reseller_sync keys that were imported as 'reserve' (before code fix) → 'occupied'
    try { $pdo->exec("UPDATE api_keys SET tag='occupied' WHERE tag='reserve' AND source='reseller_sync'"); } catch (Exception $e) {}
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usage_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            api_key_id INT NOT NULL,
            used_tokens BIGINT NOT NULL,
            max_tokens BIGINT NOT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usage_key (api_key_id),
            INDEX idx_usage_time (recorded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            api_key_id INT NOT NULL,
            model VARCHAR(100) NOT NULL,
            prompt_tokens INT DEFAULT 0,
            completion_tokens INT DEFAULT 0,
            total_tokens INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'success',
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat_tx_user (user_id),
            INDEX idx_chat_tx_key (api_key_id),
            INDEX idx_chat_tx_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            description TEXT,
            api_key_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tx_user (user_id),
            INDEX idx_tx_time (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            code VARCHAR(10) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            used TINYINT(1) DEFAULT 0,
            INDEX idx_email_codes_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // ── Bot tables ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bot_link_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(12) NOT NULL UNIQUE,
            telegram_id BIGINT NOT NULL,
            telegram_username VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            used TINYINT(1) DEFAULT 0,
            INDEX idx_blc_tg (telegram_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pending_topups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            provider VARCHAR(20) NOT NULL,
            invoice_id VARCHAR(64) NOT NULL,
            amount_rub DECIMAL(12,2) NOT NULL,
            amount_provider DECIMAL(18,8) NOT NULL,
            currency VARCHAR(10) NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            payload TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            paid_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uq_invoice (provider, invoice_id),
            INDEX idx_pt_user (user_id),
            INDEX idx_pt_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_lang (
            user_id INT PRIMARY KEY,
            lang VARCHAR(5) DEFAULT 'ru'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_overrides (
            reseller_id INT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            description TEXT,
            price_rub DECIMAL(12,2) NOT NULL,
            sort_order INT DEFAULT 0,
            is_visible TINYINT(1) DEFAULT 1,
            stock_count INT DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    try { $pdo->exec("ALTER TABLE product_overrides ADD COLUMN stock_count INT DEFAULT 0"); } catch (Exception $e) {}
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_product_prices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reseller_id INT NOT NULL,
            price_value DECIMAL(12,2) NOT NULL,
            price_currency ENUM('RUB','USD') NOT NULL DEFAULT 'RUB',
            note VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_product (user_id, reseller_id),
            INDEX idx_upp_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Products (Scarlex tariffs, replaces reseller_products) ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(40) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            tokens BIGINT NOT NULL,
            price_rub DECIMAL(12,2) NOT NULL,
            rpm INT DEFAULT 60,
            description TEXT,
            badge VARCHAR(40) DEFAULT NULL,
            sort_order INT DEFAULT 0,
            is_visible TINYINT(1) DEFAULT 1,
            is_trial TINYINT(1) DEFAULT 0,
            allow_custom TINYINT(1) DEFAULT 0,
            min_custom_tokens BIGINT DEFAULT 1000000,
            max_custom_tokens BIGINT DEFAULT 500000000,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_products_visible (is_visible, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Seed default tariffs (idempotent — only if table empty)
    $prodCnt = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($prodCnt === 0) {
        $seed = [
            // slug, name, tokens, price_rub, badge, sort, is_trial, allow_custom
            ['trial',     'Trial 1M',      1000000,     0.00, null,                     10, 1, 0],
            ['plan-10m',  '10M токенов',  10000000,   190.00, null,                     20, 0, 1],
            ['plan-25m',  '25M токенов',  25000000,   290.00, 'Хит',                    30, 0, 1],
            ['plan-50m',  '50M токенов',  50000000,   540.00, 'Лучший баланс',          40, 0, 1],
            ['plan-250m', '250M токенов', 250000000, 2590.00, '−45% к Trial-ставке',    50, 0, 1],
            ['plan-500m', '500M токенов', 500000000, 4900.00, 'Минимальная цена',       60, 0, 1],
        ];
        $ins = $pdo->prepare("INSERT INTO products (slug, name, tokens, price_rub, badge, sort_order, is_trial, allow_custom, rpm, is_visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 60, 1)");
        foreach ($seed as $s) $ins->execute($s);
    }

    // ── Support tickets ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            subject VARCHAR(200) NOT NULL,
            status ENUM('open','answered','closed') DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tickets_user (user_id),
            INDEX idx_tickets_status (status, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            sender ENUM('user','admin') NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_msg_ticket (ticket_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Achievements (badges unlocked by user) ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_achievements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            achievement_id VARCHAR(64) NOT NULL,
            unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_ach (user_id, achievement_id),
            INDEX idx_ach_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Referral system ──
    try { $pdo->exec("ALTER TABLE users ADD COLUMN referral_code VARCHAR(16) DEFAULT NULL UNIQUE"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN referrer_id INT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN referral_signup_bonus_paid TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("CREATE INDEX idx_users_referrer ON users(referrer_id)"); } catch (Exception $e) {}
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS referral_bonuses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            referrer_id INT NOT NULL,
            referred_id INT NOT NULL,
            bonus_type VARCHAR(20) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            source_topup_id INT DEFAULT NULL,
            description VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_rb_referrer (referrer_id),
            INDEX idx_rb_referred (referred_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Chat playground ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(200) DEFAULT 'Новый чат',
            mode VARCHAR(20) DEFAULT 'single',
            models TEXT,
            system_prompt TEXT,
            temperature DECIMAL(3,2) DEFAULT 0.7,
            max_tokens INT DEFAULT 2048,
            api_key_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_cs_user (user_id),
            INDEX idx_cs_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            role VARCHAR(20) NOT NULL,
            model VARCHAR(100) DEFAULT NULL,
            content MEDIUMTEXT,
            tokens INT DEFAULT 0,
            error_message TEXT DEFAULT NULL,
            turn_index INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cm_session (session_id),
            INDEX idx_cm_turn (session_id, turn_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Prompts ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS prompts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            title VARCHAR(120) NOT NULL,
            content TEXT NOT NULL,
            category VARCHAR(40) DEFAULT 'general',
            icon VARCHAR(10) DEFAULT '💡',
            sort_order INT DEFAULT 0,
            is_global TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_prompts_user (user_id),
            INDEX idx_prompts_global (is_global)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Notification dedup log ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications_sent (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_type VARCHAR(40) NOT NULL,
            ref_id VARCHAR(80) DEFAULT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ns_user_event (user_id, event_type, sent_at),
            INDEX idx_ns_ref (event_type, ref_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Blog posts ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(120) NOT NULL UNIQUE,
            title VARCHAR(255) NOT NULL,
            excerpt TEXT,
            content MEDIUMTEXT NOT NULL,
            category VARCHAR(40) DEFAULT 'general',
            icon VARCHAR(10) DEFAULT '📰',
            cover_url VARCHAR(255) DEFAULT NULL,
            is_published TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_blog_pub (is_published, sort_order, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Seed initial blog posts (idempotent)
    $blogCnt = (int)$pdo->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
    if ($blogCnt === 0) {
        $posts = [
            [
                'claude-code-2min',
                '🚀 Подключение Claude Code за 2 минуты',
                'Пошаговая инструкция: создаём ключ, прописываем 2 переменные среды и работаем без VPN.',
                'tutorial', '🚀',
                "## Что нужно\n\n1. Аккаунт Scarlex и активный API-ключ из вкладки **Мои ключи**\n2. Установленный Claude Code: `npm i -g @anthropic-ai/claude-code`\n\n## Настройка\n\nСоздайте файл `~/.claude/settings.json` (или `%USERPROFILE%\\.claude\\settings.json` на Windows):\n\n```json\n{\n  \"env\": {\n    \"ANTHROPIC_BASE_URL\": \"https://api.scarlex.ru\",\n    \"ANTHROPIC_AUTH_TOKEN\": \"ВАШ_КЛЮЧ\",\n    \"ANTHROPIC_MODEL\": \"claude-sonnet-4.6\"\n  }\n}\n```\n\nПерезапустите Claude Code — все запросы пойдут через Scarlex. Готово.\n\n## Тише, чем дефолт\n\nДобавьте `\"DISABLE_NON_ESSENTIAL_MODEL_CALLS\": \"1\"` чтобы Claude Code не делал лишних запросов в фоне — экономия токенов до 30%.",
            ],
            [
                'cursor-with-scarlex',
                '🖱 Cursor + Scarlex: пошаговая инструкция',
                'Как подключить Scarlex как кастомный OpenAI-провайдер в Cursor IDE и использовать любую модель.',
                'tutorial', '🖱',
                "## Шаг 1 — Открыть настройки\n\nCursor → **Settings** (Cmd/Ctrl + `,`) → раздел **Models**.\n\n## Шаг 2 — Override Base URL\n\nВключите **«Override OpenAI Base URL»** и вставьте:\n\n```\nhttps://api.scarlex.ru/v1\n```\n\n## Шаг 3 — API Key\n\nВ поле **OpenAI API Key** вставьте свой ключ из Scarlex и нажмите **Verify**.\n\n## Шаг 4 — Добавить модели\n\nВ списке моделей добавьте кастомные ID:\n\n- `claude-opus-4.7`\n- `claude-sonnet-4.6`\n- `gpt-5.4`\n- `gemini-3.1-pro-preview`\n\nГотово. Cmd/Ctrl + L — и можно работать.",
            ],
            [
                'opencode-full-config',
                '⚡ OpenCode: полный конфиг со всеми моделями',
                'Готовый opencode.json с провайдером Scarlex и каталогом всех актуальных моделей Claude/GPT/Gemini.',
                'tutorial', '⚡',
                "## Зачем\n\nOpenCode — мощный универсальный AI coding agent. С одним ключом Scarlex вы получаете доступ ко всем моделям Claude/GPT/Gemini сразу.\n\n## Конфиг\n\nСохраните как `opencode.json` в корне проекта (или `~/.config/opencode/opencode.json` глобально):\n\n```json\n{\n  \"\$schema\": \"https://opencode.ai/config.json\",\n  \"model\": \"Scarlex/claude-opus-4.7\",\n  \"provider\": {\n    \"Scarlex\": {\n      \"npm\": \"@ai-sdk/openai-compatible\",\n      \"name\": \"Scarlex\",\n      \"options\": {\n        \"baseURL\": \"https://api.scarlex.ru/v1\",\n        \"apiKey\": \"ВАШ_КЛЮЧ\"\n      }\n    }\n  }\n}\n```\n\n## Готовый файл с ключом\n\nВ Dashboard → **Мои ключи** → откройте ключ → вкладка **Готовые конфиги** → нажмите **Скачать** напротив OpenCode. Файл уже содержит ваш ключ — никаких подстановок.",
            ],
            [
                'save-tokens-tips',
                '💡 5 советов как сэкономить на токенах',
                'Простые приёмы которые сократят ваш расход в 2-3 раза без потери качества.',
                'guide', '💡',
                "## 1. Используйте Haiku для простых задач\n\n`claude-haiku-4.5` стоит **в 10 раз дешевле Opus** и отлично справляется с автокомплитом, простыми правками и рефакторингом коротких функций.\n\n## 2. Отключите фоновые запросы Claude Code\n\nДобавьте в `settings.json`:\n```json\n\"DISABLE_NON_ESSENTIAL_MODEL_CALLS\": \"1\",\n\"CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC\": \"1\"\n```\n\n## 3. System prompt — короче\n\nКаждое слово system prompt отправляется с КАЖДЫМ запросом. Если есть длинная инструкция на 2000 токенов и вы делаете 50 сообщений — это 100к лишних токенов.\n\n## 4. Compare mode для оценки\n\nИспользуйте Compare mode в Чате на Scarlex чтобы понять — нужен ли Opus там, где Sonnet даёт такой же ответ.\n\n## 5. Burn rate в Аналитике\n\nВкладка **Аналитика** показывает burn rate и прогноз — следите за ним. Если темп растёт без причины — проверьте код агента: возможно, он делает лишние циклы.",
            ],
        ];
        $ins = $pdo->prepare('INSERT INTO blog_posts (slug, title, excerpt, category, icon, content, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $i = 0;
        foreach ($posts as $p) {
            $ins->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $i]);
            $i += 10;
        }
    }

    // Seed global prompts (idempotent: only insert if no globals exist yet)
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM prompts WHERE is_global = 1")->fetchColumn();
    if ($cnt === 0) {
        $globals = [
            ['Code Review',        'Review the following code and identify bugs, anti-patterns, performance issues, and suggest improvements. Be specific and concise.\n\n```\n[paste code]\n```',                                            'code',     '🔍', 10],
            ['Refactor',           'Refactor the following code for clarity, performance, and maintainability. Preserve behavior. Explain key changes.\n\n```\n[paste code]\n```',                                                          'code',     '♻️', 20],
            ['Explain Code',       'Explain what this code does, step by step, in plain language. Highlight any non-obvious behavior.\n\n```\n[paste code]\n```',                                                                          'code',     '📖', 30],
            ['Bug Fix',            'I am getting this error / unexpected behavior:\n\n[describe issue]\n\nHere is the relevant code:\n\n```\n[paste code]\n```\n\nFind the root cause and propose a minimal fix.',                          'code',     '🐛', 40],
            ['Test Generation',    'Generate comprehensive unit tests for this function. Cover happy path, edge cases, and error paths. Use [framework: jest/pytest/etc.].\n\n```\n[paste code]\n```',                                     'code',     '🧪', 50],
            ['SQL Helper',         'Schema:\n\n[paste table schema]\n\nWrite an SQL query that: [describe what you need]. Optimize for performance and explain the query plan.',                                                          'data',     '🗄', 60],
            ['Regex Builder',      'Build a regex that matches: [describe what to match].\nProvide:\n1. The regex pattern\n2. Flags needed\n3. Example matches and non-matches\n4. Brief explanation',                                       'data',     '🔣', 70],
            ['JSON Formatter',     'Reformat / validate / transform this JSON:\n\n[paste JSON]\n\nDescribe what transformation is needed: [describe].',                                                                                    'data',     '📄', 80],
            ['Translate',          'Translate the following text to [target language]. Preserve tone, formatting, and technical terms.\n\n[paste text]',                                                                                  'text',     '🌐', 90],
            ['Email Writer',       'Write a [tone: formal/friendly/concise] email about [topic]. Recipient: [who]. Goal: [what should they do]. Length: [short/medium/long].',                                                            'text',     '✉️', 100],
            ['Commit Message',     'Generate a clear, conventional-commits style git commit message for the following diff:\n\n```\n[paste diff or describe changes]\n```',                                                              'code',     '📝', 110],
            ['Documentation',      'Write API / code documentation for the following:\n\n```\n[paste code]\n```\n\nInclude: purpose, parameters, return values, examples, and edge cases.',                                              'code',     '📚', 120],
        ];
        $ins = $pdo->prepare("INSERT INTO prompts (user_id, title, content, category, icon, sort_order, is_global) VALUES (NULL, ?, ?, ?, ?, ?, 1)");
        foreach ($globals as $g) {
            // unescape \n
            $content = str_replace('\n', "\n", $g[1]);
            $ins->execute([$g[0], $content, $g[2], $g[3], $g[4]]);
        }
    }

    // Promo codes tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS promocodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        type ENUM('discount','balance') NOT NULL DEFAULT 'discount',
        value DECIMAL(12,2) NOT NULL,
        max_uses INT DEFAULT 0,
        used_count INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        expires_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_promo_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS promocode_usages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        promo_id INT NOT NULL,
        user_id INT NOT NULL,
        discount_amount DECIMAL(12,2) DEFAULT 0,
        used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_promo_user (promo_id, user_id),
        INDEX idx_pu_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // News feed (admin posts → dashboard)
    $pdo->exec("CREATE TABLE IF NOT EXISTS news_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        body MEDIUMTEXT NOT NULL,
        kind ENUM('news','update','maintenance','promo') NOT NULL DEFAULT 'news',
        is_published TINYINT(1) DEFAULT 1,
        is_pinned TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_news_pub (is_published, is_pinned, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS news_reads (
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, post_id),
        INDEX idx_nr_post (post_id),
        CONSTRAINT fk_nr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_nr_post FOREIGN KEY (post_id) REFERENCES news_posts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── VPS metrics history (CPU/RAM/disk/net) ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vps_metrics_raw (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ts INT NOT NULL,
            cpu_pct FLOAT DEFAULT 0,
            load_1m FLOAT DEFAULT 0,
            load_5m FLOAT DEFAULT 0,
            ram_used_mb INT DEFAULT 0,
            ram_total_mb INT DEFAULT 0,
            swap_used_mb INT DEFAULT 0,
            disk_used_gb FLOAT DEFAULT 0,
            disk_total_gb FLOAT DEFAULT 0,
            net_rx_bps BIGINT DEFAULT 0,
            net_tx_bps BIGINT DEFAULT 0,
            uptime_sec BIGINT DEFAULT 0,
            kiro_alive TINYINT(1) DEFAULT 0,
            py_procs INT DEFAULT 0,
            INDEX idx_vmr_ts (ts)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vps_metrics_5min (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ts INT NOT NULL,
            cpu_pct FLOAT, load_1m FLOAT, ram_used_mb INT, ram_total_mb INT,
            swap_used_mb INT, disk_used_gb FLOAT, disk_total_gb FLOAT,
            net_rx_bps BIGINT, net_tx_bps BIGINT, kiro_alive_pct FLOAT,
            UNIQUE KEY uniq_vmf_ts (ts)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vps_metrics_1h (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            ts INT NOT NULL,
            cpu_pct FLOAT, load_1m FLOAT, ram_used_mb INT, ram_total_mb INT,
            swap_used_mb INT, disk_used_gb FLOAT, disk_total_gb FLOAT,
            net_rx_bps BIGINT, net_tx_bps BIGINT, kiro_alive_pct FLOAT,
            UNIQUE KEY uniq_vmh_ts (ts)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT,
            telegram_id INTEGER UNIQUE,
            telegram_username TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            ip TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            revoked_at DATETIME DEFAULT NULL
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessions_user ON user_sessions(user_id)");
    // Migration for existing dev DBs
    $sCols = array_column($pdo->query("PRAGMA table_info(user_sessions)")->fetchAll(), 'name');
    if (!in_array('revoked_at', $sCols)) {
        try { $pdo->exec("ALTER TABLE user_sessions ADD COLUMN revoked_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_prefs (
            user_id INTEGER PRIMARY KEY,
            notify_login INTEGER DEFAULT 1,
            notify_purchase INTEGER DEFAULT 1,
            notify_low_balance INTEGER DEFAULT 1
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            key_value TEXT NOT NULL UNIQUE,
            label TEXT,
            tokens_limit INTEGER DEFAULT 0,
            used_tokens INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            source TEXT DEFAULT 'imported',
            allowed_ips TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_keys_user ON api_keys(user_id)");
    // Migration for existing dev DBs
    $kCols = array_column($pdo->query("PRAGMA table_info(api_keys)")->fetchAll(), 'name');
    if (!in_array('allowed_ips', $kCols)) {
        try { $pdo->exec("ALTER TABLE api_keys ADD COLUMN allowed_ips TEXT DEFAULT NULL"); } catch (Exception $e) {}
    }

    // Migration: add telegram columns if missing
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
    $colNames = array_column($cols, 'name');
    if (!in_array('telegram_id', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN telegram_id INTEGER UNIQUE");
        $pdo->exec("ALTER TABLE users ADD COLUMN telegram_username TEXT");
    }

    // Usage history table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usage_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            api_key_id INTEGER NOT NULL,
            used_tokens INTEGER NOT NULL,
            max_tokens INTEGER NOT NULL,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usage_key ON usage_history(api_key_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_usage_time ON usage_history(recorded_at)");

    // Chat transactions (API request log)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            api_key_id INTEGER NOT NULL,
            model TEXT NOT NULL,
            prompt_tokens INTEGER DEFAULT 0,
            completion_tokens INTEGER DEFAULT 0,
            total_tokens INTEGER DEFAULT 0,
            status TEXT DEFAULT 'success',
            error_message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_tx_user ON chat_transactions(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_tx_key ON chat_transactions(api_key_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_chat_tx_time ON chat_transactions(created_at)");

    // Financial transactions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            amount REAL NOT NULL,
            description TEXT,
            api_key_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tx_user ON transactions(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tx_time ON transactions(created_at)");

    // Email verification codes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            code TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used INTEGER DEFAULT 0
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_codes_email ON email_codes(email)");
}
