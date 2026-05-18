# vps-proxy / ARCHITECTURE

## Компоненты

| Компонент | Где живёт | Назначение |
|---|---|---|
| FastAPI app | `server.py` | HTTP-маршруты, middleware, биллинг, admin |
| `AccountPool` | `server.py:254` | Sticky sessions + round-robin между 24 ботами |
| `APIKeyManager` | `server.py:661` | Верификация ключей, RPM, лимиты, whitelist моделей |
| `KiroFarmBot` | `kiro_core.py:99` | Один Kiro-аккаунт: refresh_token, fetch_credits, send_message_stream |
| EventStream parser | `kiro_core.py:41-96` | Парсит бинарный AWS EventStream (prelude + headers + payload + CRC) |
| `panel.html` | встроен в `server.py` как `PANEL_HTML` | Минимальная админ-панель, отдаётся на `GET /panel` |
| `request_logs.db` | SQLite на диске | Перманентный лог запросов (заменил deque в RAM) |
| `_response_cache` | dict в RAM | Ответный кэш с TTL, бил 15% на хитах |
| `_producer_executor` | ThreadPoolExecutor(64) | Дедикейтный пул для `bot.send_message_stream()` (избегает starvation дефолтного loop executor'а) |

## Поток запроса `/v1/chat/completions`

1. **Auth** — `key_manager.verify(authorization)` → если ключ невалиден/выключен → 401/403.
2. **IP whitelist** — `enforce_ip_whitelist(api_key, client_ip)` → 403 если allowed_ips не пуст и IP не в списке + TG-алерт.
3. **RPM** — `_rpm_history[key]` (окно 60 с). Превышение → 429.
4. **Кредиты** — если `hard_limit=True` и `credits_used >= credits_limit` → 402.
5. **Whitelist моделей** — `allowed_models` (пустой = все).
6. **System prompt** — `messages.insert(0, SCARLEX_SYSTEM)`. Всегда первым, даже если клиент шлёт свой system. ~400 токенов биллится.
7. **MODEL_MAP** — алиасы (`gpt-4`, `claude-3.5-sonnet`) резолвятся в реальную Kiro-модель.
8. **Cache lookup** — `_cache_eligible(temp, top_p)` → `_cache_get(key)`. Если hit: бил 15%, отдаём cached (или replay SSE), header `X-Scarlex-Cache: HIT`.
9. **`pool.acquire(api_key=…)`** — sticky → round-robin (frozen пропускаются) → retry до 3 раз при `[RETRY]`-сигнале.
10. **Стрим (SSE)** через `_stream_response()` (peek первого события + retry-loop ДО первого SSE-байта) или await `_full_response()` для не-стрима. Все продюсеры через `_producer_executor`.
11. **Запись** — `save_log_entry()` в SQLite + `key_manager.record_usage(prompt, completion)` + `_cache_put()` если eligible и без tool_calls.

Маршруты для Anthropic API (`/v1/messages`, `/v1/responses`, `/v1/messages/count_tokens`) проходят тот же путь, но с конверсией формата на входе/выходе (см. `server.py:2076` и далее — Pydantic-модели `AContentText`, `AContentToolUse`, `AnthropicMessagesRequest`). У всех трёх стрим-эндпоинтов теперь идентичный retry-pattern и 90-секундный queue timeout (было 300с в Anthropic/Responses).

⚠️ **Anthropic `cache_control` принимается, но не реализован**. Поле есть в Pydantic-моделях для compat, но в `_anthropic_to_openai()` теряется и в Kiro не уходит. Наш кэш — это полностью отдельный proxy-level cache по хэшу всего запроса, не префиксный prompt-caching.

## Sticky sessions

- `AccountPool._sticky: dict[api_key, (bot, ts)]`, TTL **1200 секунд** (20 мин).
- На `acquire(api_key)`: если бот жив и доступен → возвращаем его. Иначе → round-robin + новая привязка.
- Дохлые боты не возвращаются в пул (`AccountPool._available` — set без них).

См. `server.py:254-450`.

## Refresh access_token

- Триггер: первый запрос за 3000 секунд (50 мин) ИЛИ фоновый health-check (`HEALTH_INTERVAL=900` с).
- POST на `https://prod.us-east-1.auth.desktop.kiro.dev/refreshToken` с `refresh_token`.
- 2 попытки на запрос (см. `kiro_core.py:511`). Если обе провалились — бот помечается `dead`.

## EventStream-парсер

Бинарный формат AWS: `[4B total length] [4B header length] [headers] [payload] [4B prelude CRC]`. Парсер:

1. Читает prelude, валидирует CRC.
2. Парсит хэдеры (тип 7 = UTF-8 string). Извлекает `:event-type`.
3. JSON-payload между хэдерами и финальной CRC.
4. Возвращает `(event_type, dict)`.

Обрабатываемые event-types:
- `assistantResponseEvent` — текстовые чанки → yield в стрим.
- `codeEvent` — блоки кода → yield.
- `toolUseEvent` — определения tool calls. Аккумулируются в `pending_tool_calls` по `toolUseId`.
- `messageStopEvent` — финал. Если есть накопленные tool calls — yield `{"type": "tool_calls", …}`.

Парсер встроен (не из `botocore`), потому что нужна кастомная обработка Kiro-специфичных event-types и точный контроль над агрегацией tool_calls.

## Биллинг и множители

```python
MODEL_COST_MULTIPLIER = {        # server.py:198, перезаписывается из multipliers.json
    "claude-haiku-3.5": 1.4,
    "claude-sonnet-4": 2.0,
    "claude-sonnet-4-thinking": 2.0,
    "claude-opus-4.6": 3.0,
    "claude-opus-4.7": 5.0,
}

billed_prompt     = prompt_tokens                                # без множителя
billed_completion = completion_tokens * MODEL_COST_MULTIPLIER[m] # с множителем
```

- `hard_limit=True` (по умолчанию) → блокируем при перерасходе (402).
- `hard_limit=False` → пускаем дальше, но фиксируем overage.
- На реальный расход кредитов Kiro-аккаунта множитель не влияет — это исключительно про биллинг пользователю прокси.
- Загрузка `multipliers.json` — на старте через `_load_multipliers()` (`server.py:206`). Обновление через `POST /admin/multipliers` сохраняет файл атомарно.

## Константы и env-переменные

| Имя | Где | Дефолт | Что |
|---|---|---|---|
| `KIRO_HEALTH_INTERVAL` | env | 900 | период фонового health-check |
| `KIRO_RPM_PER_ACCOUNT` | env | 60 | макс. запросов в минуту на один Kiro-аккаунт |
| `KIRO_ADMIN_KEY` | env | (см. `AGENTS.md`) | Bearer для `/admin/*` |
| `KIRO_HOST`, `KIRO_PORT` | env | 127.0.0.1:8000 | где слушает uvicorn |
| `KIRO_ACCOUNTS_FILE`, `KIRO_API_KEYS_FILE` | env | `accounts.json`, `api_keys.json` | пути к state-файлам |
| `DEFAULT_MODEL` | `server.py:195` | `claude-sonnet-4` | если клиент не указал модель |
| `_sticky_ttl` | `AccountPool.__init__` | 1200 | TTL sticky-привязки |
| TOKEN_REFRESH | `kiro_core.py` | 3000 | через сколько секунд гоним refresh |

## Что НЕ обрабатывается

- Очередь / отложенные задачи — нет. Сбой → немедленный failover на другой бот.
- ~~Кэш ответов — нет~~ → **есть** с 2026-05-17 (proxy-level, in-memory, TTL 10 мин, биллинг 15%).
- **Нативный Anthropic prompt-caching** (`cache_control`) — НЕ реализован. Kiro/AWS CodeWhisperer не поддерживает прокидывание этих маркеров.
- Метрики в Prometheus/StatsD — нет. Только встроенный `/admin/metrics`, SQLite и `/admin/cache/stats`.
