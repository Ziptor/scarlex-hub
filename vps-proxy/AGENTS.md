# vps-proxy — Контекст для AI-агентов

> Сначала прочитай корневой [`../AGENTS.md`](../AGENTS.md) — общая архитектура, инфра, конвенции.
> Этот файл — только специфика проксика.

## Что это

OpenAI/Anthropic-совместимая прокся для **Kiro Pro** (Claude через kiro.dev). Принимает запросы в форматах OpenAI Chat Completions и Anthropic Messages, ротирует пул Kiro-аккаунтов, биллит API-ключи с настраиваемыми множителями по моделям.

- Внешний домен: `https://api.scarlex.ru`
- На VPS: `/opt/kiro-proxy/` (systemd unit `kiro-proxy`, порт 127.0.0.1:8000, nginx terminates SSL)
- Реальные значения секретов — см. `../secrets.local.md`

## Файлы

| Файл | Что |
|---|---|
| `server.py` | FastAPI app (~5K строк): pool, billing, sticky sessions, system prompt, response cache, IP-whitelist, hot-reload accounts |
| `kiro_core.py` | `KiroFarmBot`, `refresh_oauth_token`, `fetch_credits` через AWS Cognito |
| `accounts.json` (НА VPS, gitignored) | Kiro refresh-токены |
| `api_keys.json` (НА VPS, gitignored) | API-ключи юзеров с лимитами/whitelist |
| `multipliers.json` (НА VPS) | Множители стоимости по моделям |
| `request_logs.db` (НА VPS, gitignored) | SQLite-лог запросов (key_preview, client_ip, tokens, cost, ts) |

## Ключевые механики (одной строкой)

- **Sticky sessions**: `AccountPool._sticky[api_key] = bot_id`, TTL 1200с. На `acquire(api_key=...)` сначала пробуем того же бота.
- **Биллинг**: `billed_completion = completion_tokens * MODEL_COST_MULTIPLIER[model]`. Input — без множителя. Текущие коэффициенты — в `multipliers.json` (haiku=1.4, sonnet-4=2.0, opus-4.7=5.0).
- **System prompt** (`SCARLEX_SYSTEM`) — всегда инжектится первым, ~400 токенов биллится. Блоки: IDENTITY PROTECTION, SECRECY, ANTI-JAILBREAK.
- **IP-whitelist** (`enforce_ip_whitelist()`): per-key `allowed_ips: [ipv4|cidr]`. Hard-block 403 + TG-алерт админу. Применяется ко всем 4 эндпоинтам `/v1/*`.
- **Response cache**: in-memory dict, TTL 600с, max 10K, биллинг 15% на HIT. Eligibility: `temperature ≤ 0.3` AND `top_p is None OR ≥ 0.95`. Surface через `X-Scarlex-Cache: HIT`.
- **Hot-reload**: `accounts.json` mtime watcher, изменения подхватываются без рестарта.

## API endpoints (ключевые)

| Метод | Путь | Auth | Описание |
|---|---|---|---|
| GET | `/health` | — | Состояние пула |
| GET | `/status` | admin | Подробный статус |
| GET | `/v1/models` | api-key | OpenAI-формат |
| POST | `/v1/chat/completions` | api-key | OpenAI запросы |
| POST | `/v1/messages` | api-key | Anthropic запросы |
| GET | `/admin/keys` | admin | Список ключей |
| POST/PUT/DELETE | `/admin/keys` | admin | CRUD ключей (PUT принимает `allowed_ips`) |
| GET | `/admin/keys/{preview}/ips` | admin | Топ IP из логов |
| POST | `/admin/keys/regenerate` | admin | Atomic rotate ключа |
| GET/POST | `/admin/accounts` | admin | Список / создание (auto-pick proxy при null) |
| PUT | `/admin/accounts/{id}/proxy` | admin | Сменить proxy у бота |
| GET | `/admin/logs?limit=N` | admin | Лог запросов из SQLite |

Полный список — в [`ROUTES.md`](ROUTES.md). Auth — Bearer ADMIN_KEY (см. `../secrets.local.md`).

## Деплой

Полный workflow — в корневом [`../AGENTS.md`](../AGENTS.md). Кратко:

```powershell
# 1. Бэкап на VPS
ssh root@<VPS> "cp /opt/kiro-proxy/server.py /opt/kiro-proxy/server.py.bak.$(date +%s)"

# 2. Upload + restart (через python helper в scripts/ — gitignored)
python ../scripts/redeploy.py

# 3. Smoke
curl -H "Authorization: Bearer <ADMIN_KEY>" https://api.scarlex.ru/status
```

При сбое — `cp .bak.* server.py && systemctl restart kiro-proxy`.

## Соотношение токены/кредит

Замеры на Sonnet 4 (с задержкой обновления credits_used):
- ~20-35k токенов на 1 кредит (с большим cache hit ratio)
- Точная цифра требует накопления за сутки+
- Текущие множители подобраны эмпирически

## Что НЕ работает

- **Anthropic prompt-caching нативное** — `cache_control` парсится Pydantic, но в Kiro не передаётся (теряется в `_anthropic_to_openai`). AWS CodeWhisperer тоже не поддерживает.
- Kiro обновляет `credits_used` с задержкой минуты-часы → ratio считать только после паузы.
