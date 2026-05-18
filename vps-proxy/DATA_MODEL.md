# vps-proxy / DATA MODEL

State прокси хранится в нескольких JSON-файлах рядом с `server.py` и одной SQLite-БД. Все пути относительно `/opt/kiro-proxy/` на VPS (и `C:\work_proxy_opencode\` локально).

## accounts.json

Массив Kiro-аккаунтов. Каждый объект:

```json
{
  "account_id": "kiro-1",
  "email": "user@gmail.com",
  "login_method": "Google",          // или "Github"
  "access_token": "aoaAAAA…",        // обновляется автоматически
  "refresh_token": "aorAAAA…",       // долгоживущий, источник правды
  "profile_arn": "arn:aws:codewhisperer:us-east-1:699475941385:profile/EHGA3GRVQMUK",
  "proxy": "http://user:pass@ip:port" // или null — для обхода geofencing Kiro в RU
}
```

Загружается в `AccountPool.load_accounts()` (`server.py:272`). Изменения — через `/admin/accounts/add`, `DELETE /admin/accounts/{id}`. Запись на диск синхронная с записью атомарным rename.

## api_keys.json

Массив пользовательских API-ключей:

```json
{
  "key": "sk-scarlex-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX",
  "name": "admin-default",
  "rpm": 45,
  "enabled": true,
  "credits_limit": 0,           // 0 = безлимит
  "credits_used": 0,
  "tokens_limit": 0,            // альтернативный лимит по сырым токенам
  "allowed_models": [],         // [] = все; иначе whitelist
  "hard_limit": true,           // true = блокируем при overflow; false = только предупреждение
  "allowed_ips": []             // [] = без ограничений; иначе IPv4/CIDR whitelist (hard-block 403)
}
```

Управление — `APIKeyManager` (`server.py:661`). Все CRUD через `/admin/keys*`. Сериализация атомарная.

Поля статистики (в памяти, не в файле, но видны через `/admin/keys/{preview}/stats`):
- requests
- tokens_prompt, tokens_completion
- `_rpm_history[key] = [timestamps]` — окно 60с для RPM

## multipliers.json

```json
{
  "claude-haiku-3.5": 1.4,
  "claude-sonnet-4": 2.0,
  "claude-sonnet-4-thinking": 2.0,
  "claude-opus-4.6": 3.0,
  "claude-opus-4.7": 5.0
}
```

Загрузка — `_load_multipliers()` (`server.py:206`) при старте. Если файла нет — берётся хардкоженый `MODEL_COST_MULTIPLIER` (`server.py:198`). Обновление — `POST /admin/multipliers` → сохраняет.

## request_logs.db (SQLite)

Файл `request_logs.db` рядом с `server.py`. Создаётся автоматически.

```sql
CREATE TABLE IF NOT EXISTS request_log (
    id TEXT PRIMARY KEY,
    ts TEXT,                 -- ISO timestamp для UI
    ts_unix REAL,            -- для сортировки/диапазонов
    model TEXT,              -- что клиент попросил (gpt-4, claude-3.5…)
    kiro_model TEXT,         -- что реально пошло в Kiro
    stream INTEGER,          -- 1/0
    key_preview TEXT,        -- 'sk-xxxx…yyyy' (8 + 4)
    key_name TEXT,
    messages INTEGER,        -- сколько сообщений было в request
    has_tools INTEGER,       -- 1/0
    prompt_preview TEXT,     -- первые ~200 символов user-сообщения
    prompt_tokens INTEGER,
    completion_tokens INTEGER,
    total_tokens INTEGER,
    cost_multiplier REAL,
    account TEXT,            -- account_id, который обслужил
    status TEXT,             -- 'ok' | 'error' | 'pending'
    error TEXT,
    duration_ms INTEGER,
    data_json TEXT           -- полный JSON-снэпшот
);

CREATE INDEX IF NOT EXISTS idx_logs_ts  ON request_log(ts_unix DESC);
CREATE INDEX IF NOT EXISTS idx_logs_key ON request_log(key_preview, ts_unix DESC);
```

Создаётся в `server.py:70`. Запись — `save_log_entry()` (`server.py:97`), safe для вызова из любого треда (WAL включён).

> **Важно**: ранее логи держались в `deque(maxlen=500)` в RAM — старая запись в `AGENTS.md` в репо `work_proxy_opencode` ещё упоминает это как known issue, **это устарело**. SQLite — текущая правда.

## frozen_accounts.json

Массив `account_id` замороженных аккаунтов. `AccountPool.acquire()` пропускает таких ботов (sticky-привязки тоже сбрасываются).

```json
["kiro-7", "kiro-12"]
```

Чтение: `load_accounts()` на старте. Запись: `pool._save_frozen()` в `POST /admin/accounts/{id}/freeze` и `.../unfreeze`.

## Сводная таблица: где какие файлы пишутся

| Файл | Кто пишет | Когда |
|---|---|---|
| `accounts.json` | `AccountPool` + `/admin/accounts/*` | при добавлении/удалении аккаунта |
| `api_keys.json` | `APIKeyManager` + `/admin/keys/*` | при создании/изменении/расходе |
| `multipliers.json` | `_save_multipliers()` | `POST /admin/multipliers` |
| `frozen_accounts.json` | `pool._save_frozen()` | freeze/unfreeze admin actions |
| `request_logs.db` | `save_log_entry()` | в конце каждого запроса |

## Ответный кэш (in-memory, не файл)

Глобальный `_response_cache: dict` в `server.py`. Не персистится, при рестарте — пусто (TTL всё равно ≤10 мин). Lock через `threading.Lock()`. Записи: `(response_dict, expires_at_unix, prompt_tokens, completion_tokens, kiro_model)`. См. `_cache_get`, `_cache_put`, `_cache_eligible`, `_cache_key`.

## Backup / восстановление

- Все 4 файла — пер-файл копируются `redeploy.py` при деплое. Перед обновлением — забирать `api_keys.json` с продакшена (там — реальный `credits_used`).
- Восстановление аккаунтов: в `kiro_form/build_accounts.py` есть логика собрать `accounts.json` обратно из бэкапов `kiro-N/token.json`.
