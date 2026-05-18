# website / DATABASE

## Где живёт

- **Прод**: MySQL `u3493941_default@localhost` на shared hosting. Креды — в `api/secrets.php`.
- **Локальный dev**: SQLite-файл `api/scarlex.db` — `db.php` сам решает, какой backend использовать.

Все таблицы автоматически создаются на первом запросе через `db.php` (DDL — встроены).

## Основные таблицы

### `users`
Пользователи сайта.

| Поле | Тип | Описание |
|---|---|---|
| `id` | INT PK | внутренний ID |
| `public_id` | TEXT | публичный идентификатор (для URL) |
| `email` | TEXT UNIQUE | логин |
| `username` | TEXT | отображаемое имя |
| `password_hash` | TEXT | bcrypt |
| `telegram_id` | BIGINT NULL | привязанный TG |
| `telegram_username` | TEXT NULL | |
| `balance` | DECIMAL | баланс в рублях |
| `is_banned` | BOOL | |
| `created_at`, `updated_at` | TIMESTAMP | |

### `api_keys`
Выданные пользователю ключи. Это локальная копия — реальный счётчик расхода живёт в `api_keys.json` на VPS.

| Поле | Описание |
|---|---|
| `id` | PK |
| `user_id` | FK → users |
| `key_value` | сам ключ `sk-scarlex-…` |
| `label`, `tag` | пользовательские метки |
| `tokens_limit`, `used_tokens` | лимиты |
| `is_active` | флаг |
| `source` | `'imported'` / `'reseller_sync'` / `'claim'` |
| `allowed_ips` | TEXT, JSON-массив IPv4/CIDR; NULL = без ограничений. Источник правды — `api_keys.json::allowed_ips` на VPS. |

### `usage_history`
Снимки потребления (заполняется cron'ом из `chat_transactions`).

| Поле | Описание |
|---|---|
| `api_key_id` | FK |
| `used_tokens` | дельта |
| `recorded_at` | TIMESTAMP |

### `chat_transactions`
Каждый запрос пользователя через прокси (для аналитики).

| Поле | Описание |
|---|---|
| `user_id`, `api_key_id` | FK |
| `model` | модель |
| `prompt_tokens`, `completion_tokens` | |
| `created_at` | |

### `link_tokens`
One-time токены для deep-link флоу: TG-логин, линковка аккаунта.

| Поле | Описание |
|---|---|
| `token` | UUID |
| `kind` | `'site_login'` / `'tg_link'` |
| `expires_at` | |
| `used_at` | NULL пока не использован |

### `tg_login_sessions`
Состояние логина через Telegram Login Widget.

| Поле | Описание |
|---|---|
| `token` | |
| `status` | `'pending'` → `'confirmed'` → `'consumed'` |
| `tg_id`, `tg_username` | заполняется ботом при подтверждении |
| `expires_at` | |

### `notifications`
In-app уведомления для дашборда.

| Поле | Описание |
|---|---|
| `user_id` | |
| `body`, `type` | |
| `is_read` | |
| `created_at` | |

### `user_sessions`
Активные сессии (IP, user-agent, last_seen). После soft-revoke поле `revoked_at` устанавливается и `check_session.php` отдаёт `401 session_revoked` на следующий запрос. Используется на `dashboard/settings.html` (список устройств + "выйти везде").

| Поле | Описание |
|---|---|
| `id` | PHP session_id |
| `user_id` | FK → users |
| `ip`, `user_agent` | заполняется при логине |
| `created_at`, `last_seen` | last_seen апдейтится в `check_session.php` |
| `revoked_at` | NULL = активна; не-NULL = soft-revoke (выход с другого устройства) |

### `user_prefs`
Настройки уведомлений (включать ли daily report, в какое время по МСК).

### Дополнительные (создаются по мере использования)
- `products` — каталог пакетов.
- `transactions` — платежи и пополнения.
- `promocodes`, `promo_usages` — промокоды и их применение.
- `referrals`, `referral_earnings` — рефералы.
- `achievements`, `user_achievements` — milestone-награды.

## Связь со state'ом прокси

| MySQL (сайт) | JSON/SQLite (прокси) | Кто синхронизирует |
|---|---|---|
| `users.balance` | — | YooMoney/CryptoBot вебхуки |
| `api_keys.key_value` | `api_keys.json::key` | `buy_key.php` создаёт через ADMIN API |
| `api_keys.used_tokens` | `api_keys.json::credits_used` | cron `sync_reseller.php` (или вытягивание из `/admin/keys`) |
| `chat_transactions` | `request_log` (SQLite) | дублируется (сайт пишет своё на основе ответов прокси) |

> **Важно**: `api_keys.json` на VPS — единственный источник правды для текущих лимитов и расходов. MySQL — это слепок для аналитики и UI.

## Бэкапы

Cpanel shared hosting делает свои бэкапы. Дополнительно — рекомендуется периодически экспортировать MySQL через `mysqldump` и хранить локально (не настроено автоматически).
