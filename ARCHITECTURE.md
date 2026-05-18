# ARCHITECTURE.md — Общая архитектура Scarlex

Документ описывает, как связаны три кита экосистемы. Подробности по каждому компоненту — в соответствующей подпапке.

## Высокоуровневая схема

```
                                 ┌────────────────────────────────────────┐
                                 │           POTENTIAL CLIENTS            │
                                 │  (curl, Claude CLI, OpenCode, oh-my)   │
                                 └────────────────┬───────────────────────┘
                                                  │ Bearer sk-scarlex-…
                                                  ▼
        ┌──────────────────────┐         ┌────────────────────────────┐
        │ Telegram @scarlex_bot│────────▶│      api.scarlex.ru        │
        │   (aiogram, FSM)     │         │  nginx → FastAPI 127:8000  │
        │                      │         │       (server.py)          │
        │  /shop /balance      │         │                            │
        │  /key /topup /promo  │         │   ┌──────────────────┐     │
        │  /ref /history /api  │         │   │  APIKeyManager   │     │
        └──────────┬───────────┘         │   │  (verify, RPM,   │     │
                   │                     │   │   billing)       │     │
                   │ X-Bot-Secret        │   └──────────────────┘     │
                   ▼                     │   ┌──────────────────┐     │
        ┌────────────────────────┐       │   │   AccountPool    │     │
        │   scarlex.ru           │       │   │ (sticky 1200 s + │     │
        │  PHP backend           │       │   │  round-robin)    │     │
        │  + MySQL               │       │   └────────┬─────────┘     │
        │  shared hosting        │       │            │ acquire()     │
        │  37.140.192.187        │       │   ┌────────▼─────────┐     │
        │                        │       │   │  KiroFarmBot ×24 │     │
        │  api/bot/*.php  ───────┼───────┼─▶ │ refresh_token,   │     │
        │  api/admin*.php        │       │   │ fetch_credits,   │     │
        │  api/*.php             │       │   │ send_message     │     │
        │  /dashboard/*.html     │       │   └────────┬─────────┘     │
        │  /kiro.php (admin)     │       │            │               │
        └────────────────────────┘       └────────────┼───────────────┘
                   ▲                                  │
                   │ admin запросы из                 │ EventStream binary
                   │ kiro.php / kiro.html             ▼
                   └─────────────── ADMIN ───────  AWS CodeWhisperer
                                    Bearer            (us-east-1)
```

Сбоку у прокси на диске VPS:
- `accounts.json` (24 акка с refresh_token, proxy, profile_arn)
- `api_keys.json` (пользовательские API-ключи и лимиты)
- `multipliers.json` (множители стоимости по моделям)
- `request_logs.db` (SQLite, таблица `request_log` + 2 индекса)

## Кто кого зовёт

### 1. Клиент → прокси
- Любой OpenAI-клиент шлёт `Authorization: Bearer sk-scarlex-…` на `https://api.scarlex.ru/v1/chat/completions`.
- Прокси проверяет ключ, RPM, кредиты, whitelist моделей → выбирает Kiro-аккаунт → проксирует.
- Поддерживаются также `/v1/messages` (Anthropic Messages API), `/v1/responses`, `/v1/messages/count_tokens`.

### 2. Telegram-бот → PHP-бэкенд
- Бот **никогда не ходит напрямую** в прокси. Он шлёт `POST /api/bot/<endpoint>.php` с заголовком `X-Bot-Secret: sx_bot_…` на `scarlex.ru`.
- PHP-эндпоинты (`stats.php`, `balance.php`, `buy.php`, `promo.php`, …) читают MySQL, возвращают JSON.
- При покупке ключа PHP-бэкенд сам обращается к админскому API прокси (Bearer ADMIN_KEY) и создаёт ключ в `api_keys.json`.

### 3. Веб-дашборд → прокси
- `dashboard/keys.html` и friends работают через JS-вызовы к `scarlex.ru/api/*.php`. Авторизация — сессионная cookie от `api/auth.php`.
- `kiro.php` (раздел админа) — после PHP-проверки email-владельца отправляет admin-запросы напрямую в `api.scarlex.ru/admin/*` с Bearer ADMIN_KEY.
- Канон `kiro.html` лежит у бота как `staging` (`/opt/scarlex-deploy/kiro.html`), оттуда `upload_kiro.py` заливает на FTP в `/www/scarlex.ru/kiro.html`. На самом сайте отдаётся через `kiro.php`, который инклюдит/обёртывает `kiro.html`.

### 4. Сайт ↔ бот (deep-link)
- При логине через TG: сайт генерирует токен в `api/tg_login_init.php` → пользователь нажимает кнопку → открывается `t.me/scarlex_bot?start=tglogin_<token>` → бот вызывает `api/bot/tg_login_confirm.php` → сайт `api/tg_login_check.php` поллит и переводит сессию в `confirmed`.
- При линковке аккаунта: похожий поток через `link_request.php` / `bot_link_confirm.php`.

### 5. Биллинг (упрощённо)
- Клиент дёргает прокси → прокси возвращает ответ, фиксирует `prompt_tokens`, `completion_tokens`.
- `billed = prompt_tokens + completion_tokens * MODEL_COST_MULTIPLIER[model]` (input множитель НЕ применяется).
- `key_manager.record_usage()` обновляет `credits_used` в `api_keys.json`.
- Сайт показывает баланс в `dashboard/home.html` и `dashboard/key.html`, бот — в `/balance`, `/stats`.
- Пополнение: бот зовёт `topup_invoice.php` (CryptoBot) или `topup_card.php` (YooMoney). После вебхука об оплате PHP обновляет `users.balance`, при покупке ключа атомарно: списать с баланса → создать ключ через ADMIN API прокси → записать `chat_transactions`.

## Потоки данных по доменам

| Граница | Транспорт | Авторизация |
|---|---|---|
| клиент → прокси | HTTPS, OpenAI-формат | `Authorization: Bearer sk-scarlex-…` |
| сайт ↔ MySQL | внутренний loopback | креды в `secrets.php` |
| сайт → прокси | HTTPS | `Authorization: Bearer ADMIN_KEY` |
| бот → сайт | HTTPS POST JSON | header `X-Bot-Secret` |
| сайт → бот (уведомления) | HTTPS Telegram Bot API | `BOT_TOKEN` |
| прокси → AWS | HTTPS (через прокси-IP при необходимости) | refresh_token → access_token (50 мин TTL) |
| cron → сайт | HTTPS | header/query `CRON_TOKEN` |
| локальные утилиты → VPS | SSH/SFTP | root password |
| локальные утилиты → FTP | FTP | u3493941 password |

## Ключевые инварианты

1. **Один источник правды по моделям** — `server.py:181 MODEL_MAP`. Бот, сайт, дашборд должны показывать тот же список.
2. **System prompt инжектится первым** в `/v1/chat/completions` — даже если клиент шлёт свой system, `SCARLEX_SYSTEM` идёт `messages.insert(0, …)`. Цель — защитить identity бренда (~400 токенов биллится).
3. **Sticky sessions** — один API-ключ 1200 секунд работает с одним и тем же `KiroFarmBot`, чтобы максимизировать cache locality на стороне Kiro.
4. **Refresh access_token** проактивный — фоновый health-check каждые 900с обновляет токены, чтобы не падать посреди реального запроса.
5. **Биллинг с множителем — только на output**. Это специально, чтобы не наказывать длинные системные промпты.

## Что НЕ задокументировано в этом hub'е

- Внутреннее устройство shared hosting (контрольная панель, бэкапы) — это вне Scarlex.
- Внешние сервисы: YooMoney, CryptoBot, Telegram BotFather — только точки интеграции и креды.
- Старые версии `index*.html`, `indexv2_*`, `indexv3_*` — это итерации лендинга, актуальный — `index.html` в `scarlex_v2.02`.

См. дальше:
- [`vps-proxy/ARCHITECTURE.md`](vps-proxy/ARCHITECTURE.md) — детали прокси.
- [`website/`](website/) — структура сайта и API.
- [`telegram-bot/`](telegram-bot/) — устройство бота.
- [`scripts/`](scripts/) — что лежит в `Temp\opencode`.
