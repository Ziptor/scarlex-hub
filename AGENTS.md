# AGENTS.md — Контекст для AI-агентов

> Читай этот файл в начале каждой сессии. Реальные значения секретов — в `secrets.local.md` (gitignored), они **не в репо**.

## Что это

**Scarlex** — SaaS, который продаёт API-ключи на OpenAI/Anthropic-совместимый эндпоинт. Под капотом — пул Kiro-аккаунтов (kiro.dev раздаёт бесплатный Claude через AWS CodeWhisperer). Биллинг по кредитам, множители стоимости по моделям, Telegram-бот для покупки и пополнения, веб-дашборд.

## Три кита

```
┌─────────────────┐         ┌──────────────────┐         ┌──────────────────┐
│  Telegram бот   │────────▶│   scarlex.ru     │◀───────▶│ api.scarlex.ru   │
│  @scarlex_bot   │ Bearer  │ PHP + MySQL      │ Bearer  │ FastAPI прокси   │
│  (aiogram)      │ SHARED  │ shared hosting   │ ADMIN   │ → AWS Kiro       │
└─────────────────┘         └──────────────────┘         └──────────────────┘
       VPS                      reg.ru shared              VPS (FirstVDS)
   /opt/scarlex-bot           /www/scarlex.ru/         /opt/kiro-proxy/
```

Детали потока запроса — в [`ARCHITECTURE.md`](ARCHITECTURE.md).

## Repo layout

```
scarlex-hub/
├── website/         # PHP + dashboard для scarlex.ru (shared host)
│   ├── api/         # PHP endpoints (auth, keys, settings, admin, bot/*)
│   ├── dashboard/   # /dashboard/*.html (home, settings, keys, ...)
│   └── kiro.php     # админка пула (доступна только Luckyanencko.andrej2017@yandex.ru)
├── vps-proxy/       # FastAPI прокся → api.scarlex.ru
│   ├── server.py    # ~5K строк: pool, billing, IP-whitelist, cache, sticky
│   ├── kiro_core.py # KiroFarmBot — refresh, credits, request к AWS
│   └── DATA_MODEL.md, ROUTES.md
├── telegram-bot/    # aiogram-бот, handlers, i18n
├── kiro-form/       # утилиты регистрации Kiro-аккаунтов
└── scripts/         # (gitignored) deploy_v4.py, copy_to_hub.py, и одноразовые скрипты
```

## Где живут секреты

| Что | Где (НЕ в репо) |
|---|---|
| VPS root, FTP creds, KIRO_ADMIN_KEY, BOT_TOKEN, CRYPTOBOT_TOKEN | `secrets.local.md` (root репо, gitignored) |
| Продакшен PHP-секреты (DB pass, OAuth, JWT) | `website/api/secrets.php` (на shared host'е, gitignored) |
| Kiro refresh-токены (24+ аккаунтов) | `/opt/kiro-proxy/accounts.json` на VPS |
| Прокся API-ключей (`sk-scarlex-*`) | `/opt/kiro-proxy/api_keys.json` на VPS |
| TG-бот env | `/opt/scarlex-bot/.env` на VPS |

Структура для шаблонов: `website/api/secrets.example.php`, `telegram-bot/.env.example`.

## Инфраструктура (без значений)

### VPS
- Хостер: **FirstVDS** (панель: `bill.firstvds.ru` — там же web-console / VNC при бане SSH)
- IP: см. `secrets.local.md`
- OS: Ubuntu 24.04 LTS
- Systemd units: `kiro-proxy` (127.0.0.1:8000), `scarlex-bot`, `nginx`
- CrowdSec + firewall-bouncer (iptables) — баны IP за брутфорс/паттерны. Web-консоль обходит firewall.

### Shared hosting (scarlex.ru)
- Хостер: **reg.ru**
- DNS через Cloudflare (`Nextrival23@gmail.com` аккаунт), все записи `DNS only` (proxy OFF) — это нужно для IP-whitelist'а API-ключей
- Web-root: `/www/scarlex.ru/`
- MySQL: `localhost` (одна БД на shared host'е, ~50+ таблиц)

### CDN / DNS
- `scarlex.ru`, `www.scarlex.ru` → shared host (reg.ru)
- `api.scarlex.ru` → VPS (FirstVDS)
- MX → reg.ru mail

## Workflow деплоя

> Деплоим только когда пользователь явно говорит «задеплой» или это очевидно из контекста (правка → тест → deploy).

| Что | Откуда | Куда | Чем |
|---|---|---|---|
| PHP (website/) | `scarlex-hub/website/*` | shared host через VPS staging | `scripts/deploy_v4.py` (gitignored — пароли) |
| `server.py` | `scarlex-hub/vps-proxy/server.py` | `/opt/kiro-proxy/server.py` + restart | `scripts/redeploy.py` или SFTP вручную |
| TG-бот | `scarlex-hub/telegram-bot/` | `/opt/scarlex-bot/` + restart | `scripts/deploy_bot.py` |
| `accounts.json` / `api_keys.json` | локально под управлением KiroTool | VPS `/opt/kiro-proxy/` | через kiro-proxy `/admin/accounts/add` (не SFTP) |

**Шаги после правки `server.py`:**
1. Бэкап на VPS: `cp /opt/kiro-proxy/server.py /opt/kiro-proxy/server.py.bak.$(date +%s)`
2. SFTP upload
3. `systemctl restart kiro-proxy`
4. Smoke: `curl -H "Authorization: Bearer $ADMIN_KEY" https://api.scarlex.ru/status`
5. При проблеме откат: `cp .bak.* server.py && systemctl restart kiro-proxy`

## Конвенции

- **Язык интерфейса**: всё пользовательское — на русском. EN — опция в боте.
- **Канон**: `scarlex-hub/` — единственный source of truth. Mirror в `project/scarlex_v2.02/` устаревший, но `deploy_v4.py` всё ещё может его читать (проверь LOCAL_DIR в скрипте).
- **secrets.php / .env не деплоить** — они уже на VPS / хостинге.
- **Никогда** не пушить креды в git, не вставлять в чат, не публиковать в issues.
- **Тестовый ключ** (1M токенов / 30 дней) — один на юзера на жизнь, через `/api/keys.php?action=test_key`.

## Gotchas (важно)

- **`deploy_v4.py` имеет `DELETE_REMOTE = ["kiro.html", ...]`** — каждый запуск удаляет `kiro.html` с FTP. Это OK, kiro.html уже не используется (реальная админка — `kiro.php`).
- **PHP session lock** — без `session_write_close()` сразу после auth-чека параллельные fetch'и из dashboard серииализуются (60-секундные таймауты). Все hot endpoint'ы (`check_session.php`, `keys.php`, `admin.php`) уже патчены.
- **`KIRO_ADMIN_KEY` имеет fallback `''` в `admin.php` / `admin_kiro.php`** — реальный из `secrets.php` подхватится через `if (!defined())`. Не пугайся пустой строки в коде.
- **CrowdSec на VPS банит подозрительные IP** (включая VPN-узлы и иногда твои тестовые curl'ы). Если SSH в timeout — это оно. Лекарство: web-console FirstVDS → `cscli decisions delete --ip X.X.X.X`.
- **Cloudflare proxy выключен** на всех A-записях. Это сознательно — для корректной работы IP-whitelist'а API-ключей (иначе VPS видит CF-IP вместо реального клиента).
- **Windows-сокеты исчерпываются** при серии SFTP/FTP подряд (`WinError 10055`). Пауза 5-10 мин или ретрай-петля с `sleep 15-60s`.

## История изменений

Полная — в `git log`. Текущие крупные эволюции:

- **2026-05-17/18 — security-пак**: IP-whitelist API-ключей (hard-block 403 + TG-алерт), регенерация ключей, soft-revoke сессий, in-site bell для уведомлений. Freeze/unfreeze Kiro-аккаунтов через kiro.php.
- **2026-05-17 — perf**: dedicated thread pool 64 для продюсеров, retry-loop на `[RETRY]`, queue timeout 90с, in-memory response cache (TTL 600с, billing 15% от обычной цены, surface через `X-Scarlex-Cache: HIT`).

## Что НЕ работает (важно знать)

- **Anthropic prompt-caching нативное** — `cache_control` парсится Pydantic-моделью, но в Kiro не передаётся (теряется в `_anthropic_to_openai`). AWS CodeWhisperer тоже не поддерживает. Наш `X-Scarlex-Cache` — кэш всего ответа, не префикса.
- **`kiro.html`** — присутствует в репо как legacy, но не используется. Админка — `kiro.php`.
- **Stripe / расчёт стоимости** в KiroTool — UnionPay-карты иногда отбиваются Stripe'ом с разной формулировкой; retry-логика в `tests/test_full_flow.py` обходит большинство.

## Что почитать дальше

- [`ARCHITECTURE.md`](ARCHITECTURE.md) — диаграмма + поток запроса
- [`vps-proxy/ARCHITECTURE.md`](vps-proxy/ARCHITECTURE.md), [`vps-proxy/ROUTES.md`](vps-proxy/ROUTES.md), [`vps-proxy/DATA_MODEL.md`](vps-proxy/DATA_MODEL.md) — детали проксика
- [`website/API.md`](website/API.md), [`website/DATABASE.md`](website/DATABASE.md), [`website/PAGES.md`](website/PAGES.md) — детали сайта
- [`telegram-bot/README.md`](telegram-bot/README.md), [`telegram-bot/COMMANDS.md`](telegram-bot/COMMANDS.md) — бот
