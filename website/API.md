# website / API

Все эндпоинты — в папке `api/` на shared hosting. Авторизация зависит от группы:

- **Браузер** → cookie-сессия от `api/auth.php`.
- **Telegram-бот** → заголовок `X-Bot-Secret`.
- **Cron** → заголовок/query `CRON_TOKEN`.
- **`kiro.php` (админка прокси)** → дополнительно Bearer ADMIN_KEY при вызовах в `api.scarlex.ru`.

## `api/` — пользовательские и админские эндпоинты

### Auth / сессии
| Файл | Что |
|---|---|
| `auth.php` | Регистрация / логин по email + пароль. Создаёт PHP-сессию. |
| `check_session.php` | Возвращает текущего пользователя или 401. Параллельно: апдейтит `user_sessions.last_seen`, проверяет `revoked_at IS NULL` (иначе destroy + 401 `session_revoked`), отдаёт `session_id` для UI. |
| `telegram_auth.php` | OAuth-старт для Telegram Login Widget. |
| `tg_login_init.php` | Генерит токен и deep-link для логина через бота. |
| `tg_login_check.php` | Поллинг статуса логина (`pending` → `confirmed` → `consumed`). |
| `bot_link_confirm.php` | Подтверждает линковку TG-аккаунта (вызывается из бота). |
| `verify_email.php` | Подтверждение email через ссылку. |

### Ключи и покупки
| Файл | Что |
|---|---|
| `keys.php` | CRUD API-ключей пользователя. Actions: `list`, `add`, `delete`, `rename`, `freeze`/`unfreeze`, `topup`, `refund`, `test_key`, `key_ips` (топ-20 IP из логов + текущий whitelist), `set_key_whitelist` (валидация IPv4/CIDR → форвард `PUT /admin/keys` на прокси + зеркало в MySQL), `regenerate` (форвард `POST /admin/keys/regenerate` → atomic swap `key_value` по тому же `id`). |
| `buy_key.php` | Покупка ключа (списание с баланса + создание через ADMIN-API прокси). |
| `claim_key.php` | Бесплатный trial-ключ. |
| `wallet.php` | Баланс кошелька. |
| `promocodes.php` | Промокоды (валидация + применение). |

### Использование и аналитика
| Файл | Что |
|---|---|
| `usage_history.php` | История потребления токенов. |
| `usage_log.php` | Подробный лог (вызывается из cron). |
| `transactions.php` | История платежей и списаний. |
| `analytics.php` | Агрегаты для графиков на дашборде. |
| `vps_metrics.php` | Принимает метрики от `vps_collector.py` (CPU/RAM/disk/net). |

### Каталог
| Файл | Что |
|---|---|
| `models.php` | Каталог AI-моделей с ценами. |
| `pricing.php` | Тарифы и пакеты. |
| `products_loader.php`, `products_public.php` | Список продуктов для покупки. |

### Соц / реклама / поддержка
| Файл | Что |
|---|---|
| `referrals.php` | Реферальная программа (генерация кода, начисления). |
| `achievements.php` | Достижения / milestone-бонусы. |
| `news.php` | Лента новостей на сайте. |
| `notifications.php` | In-app уведомления. |
| `support.php` | Тикеты в саппорт. |
| `daily_report.php` | Дневной отчёт пользователю (через cron). |

### Платежи
| Файл | Что |
|---|---|
| `yoomoney_callback.php` | Вебхук от YooMoney. Валидирует подпись и зачисляет баланс. |
| `cbr_rate.php` | Курс ЦБ для конвертации USD ↔ RUB. |

### Админ-зона
| Файл | Что |
|---|---|
| `admin.php` | Управление пользователями, ключами, транзакциями (используется в `admin.html`). |
| `admin_kiro.php` | Мониторинг пула Kiro-аккаунтов и редактирование множителей. |
| `scarlex_proxy.php` | Форвардер запросов в `api.scarlex.ru` (изолирует ADMIN_KEY от фронта). |
| `config_download.php` | Сгенерировать скачиваемый конфиг для пользователя. |
| `settings.php` | Глобальные настройки + пользовательские: `get_all` (user + sessions + prefs), `change_password`, `change_email`, `revoke_session` / `revoke_all_other` (soft-revoke через `revoked_at`), `update_prefs`. |

### Внутренние утилиты (не для прямого вызова из фронта)
| Файл | Что |
|---|---|
| `db.php` | PDO-коннектор + автосоздание таблиц. |
| `secrets.php` | Все секреты (доступ закрыт через `.htaccess`). |
| `helpers.php` | Шейред-утилиты (форматирование, валидации). |
| `_purchase.php`, `_referrals_helpers.php`, `_link_tokens.php`, `_tg_notify.php`, `_yoomoney_config.php` | Внутренние хелперы (префикс `_`). |

## `api/bot/` — бот-API

Все эндпоинты требуют `X-Bot-Secret: <BOT_SHARED_SECRET>`. Метод — POST, body — JSON.

| Эндпоинт | Body | Что возвращает |
|---|---|---|
| `auth.php` | `{telegram_id, username, first_name}` | Регистрирует/возвращает пользователя |
| `stats.php` | `{telegram_id}` | Баланс, ключи, дневное потребление, burn rate, top models |
| `balance.php` | `{telegram_id}` | Баланс + краткие данные кошелька |
| `products.php` | `{telegram_id}` | Список пакетов для покупки |
| `buy.php` | `{telegram_id, product_id, promo_code?}` | Старт оплаты или подтверждение |
| `my_key.php` | `{telegram_id}` | Активные ключи пользователя |
| `promo.php` | `{telegram_id, code}` | Активация промокода → новый баланс |
| `referrals.php` | `{telegram_id}` | Реферальный код + статы + последние 5 |
| `history.php` | `{telegram_id, limit, offset}` | История транзакций (с пагинацией) |
| `settings.php` | `{telegram_id, …}` | Get/set настроек уведомлений |
| `topup_card.php` | `{telegram_id, amount}` | Инвойс YooMoney |
| `topup_invoice.php` | `{telegram_id, amount, currency}` | Инвойс CryptoBot |
| `link_request.php` | `{telegram_id, token}` | Линковка TG-аккаунта с сайтом |
| `login_link.php` | `{telegram_id}` | One-time URL для авто-логина на сайте |
| `tg_login_confirm.php` | `{telegram_id, token}` | Подтверждение TG-логина (от Login Widget) |
| `cryptobot_webhook.php` | (от Crypto Bot) | Вебхук об оплате крипто-инвойса |

Хелперы:
- `_auth.php` — `bot_auth_or_die()`, `bot_input()`, `bot_err()`, `bot_ok()`
- `_cryptobot_config.php` — креды Crypto Bot

## `api/cron/`

Запускаются shared-hosting cron'ом с токеном `CRON_TOKEN=sx_cron_8fGHv7Lp9qMzTn2XwRy5bK`.

| Файл | Расписание | Что |
|---|---|---|
| `sync_reseller.php` | каждые 5 мин | Синк ключей с upstream-реселлера |
| `daily_report.php` | каждую минуту | Шлёт отчёт пользователям, у которых наступило время рассылки |
| `broadcast_worker.php` | каждую минуту | Раздаёт массовые рассылки из очереди |
| `index.php` | — | Роутер для всех cron-вызовов |

## Стиль ответов

В большинстве эндпоинтов: `{"ok": true, "data": {…}}` или `{"ok": false, "error": "..."}`. Бот-эндпоинты дополнительно: `bot_ok($data)` / `bot_err($msg, $code)`.
