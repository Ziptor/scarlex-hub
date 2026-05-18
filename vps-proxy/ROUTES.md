# vps-proxy / ROUTES

Все маршруты регистрируются в `server.py`. Указаны точные строки декораторов.

## Публичные (OpenAI-совместимые)

| Метод | Путь | Стр. | Описание |
|---|---|---|---|
| GET | `/health` | 1499 | Минимальный ping. Без авторизации. |
| GET | `/status` | 1456 | Подробный статус: размер пула, аптайм, статистика. |
| GET | `/v1/models` | 1443 | Список моделей в OpenAI-формате. |
| POST | `/v1/chat/completions` | 1005 | Основной эндпоинт. Поддержка `stream: true`. |
| POST | `/v1/messages` | 2504 | Anthropic Messages API. |
| POST | `/v1/responses` | 3011 | Anthropic Responses API (продолжение / streaming). |
| POST | `/v1/messages/count_tokens` | 3093 | Подсчёт токенов без обращения к Kiro. |
| GET | `/panel` | 3135 | Встроенная админ-панель (HTML из `PANEL_HTML`). |

## Админские (заголовок `Authorization: Bearer <KIRO_ADMIN_KEY>`)

### Ключи
| Метод | Путь | Стр. | Описание |
|---|---|---|---|
| GET | `/admin/keys` | 1557 | Список всех ключей с метаданными и статистикой. |
| POST | `/admin/keys` | 1563 | Создать ключ. Body: `{key?, name, rpm, credits_limit, allowed_models?, hard_limit?, allowed_ips?}`. `allowed_ips` — массив IPv4/CIDR; невалидное правило → 400. |
| PUT | `/admin/keys` | 1572 | Обновить ключ. Body: `{key, name?, rpm?, enabled?, credits_limit?, credits_used?, allowed_models?, hard_limit?, allowed_ips?}`. |
| POST | `/admin/keys/regenerate` | — | Сменить значение ключа. Body: `{key}`. Копирует все поля (расход, лимиты, IP-список, allowed_models, name); старый ключ удаляется. Возвращает `{old_key, new_key, key_info}`. |
| DELETE | `/admin/keys/{key}` | 1637 | Удалить ключ. |
| POST | `/admin/keys/topup` | 1604 | Пополнить кредиты. Body: `{key, amount}`. `amount > 0`. Если `credits_limit==0` → ставим в `amount`. |
| POST | `/admin/keys/topup-tokens` | 1621 | Пополнить токены (альтернативный лимит). |
| POST | `/admin/keys/bulk` | 1664 | Массовое создание (до 200). `key` опционален — автогенерация. |
| POST | `/admin/keys/bulk-action` | 1694 | Массовое действие: `delete`, `enable`, `disable`, `set_credits`, `set_rpm`, `reset_used`. |
| POST | `/admin/keys/duplicate` | 1740 | Создать копию по `source_key` (полный или preview). |
| GET | `/admin/key-resolve/{key_preview}` | 1976 | Полный ключ по preview (`sk-xxxx…yyyy`). |
| GET | `/admin/keys/top` | 1807 | Топ ключей по использованию. |
| GET | `/admin/keys/{key_preview}/stats` | 1831 | Подробная статистика по ключу. |
| GET | `/admin/sparkline-bulk` | 1882 | Sparkline-данные для дашборда. |

### Аккаунты
| Метод | Путь | Стр. | Описание |
|---|---|---|---|
| GET | `/admin/accounts` | 1938 | Список аккаунтов: статус, кредиты, статистика. Включает поле `frozen: bool`. |
| POST | `/admin/accounts/add` | 1998 | Добавить аккаунт. Body: `{label, email, refresh_token, proxy?}`. |
| DELETE | `/admin/accounts/{account_id}` | 2040 | Удалить аккаунт. |
| POST | `/admin/accounts/{account_id}/freeze` | — | Заморозить аккаунт. `acquire()` будет его пропускать. Состояние в `frozen_accounts.json` (переживает рестарт). |
| POST | `/admin/accounts/{account_id}/unfreeze` | — | Разморозить аккаунт. |

### Логи и метрики
| Метод | Путь | Стр. | Описание |
|---|---|---|---|
| GET | `/admin/logs` | 1762 | Глобальный журнал. Query: `?limit=N` (по умолчанию 100). |
| GET | `/admin/logs/key/{key_preview}` | 1967 | Логи запросов конкретного ключа. |
| GET | `/admin/metrics/history` | 1504 | Временной ряд для графиков. |
| GET | `/admin/billing` | 1961 | Сводка биллинга по всем ключам. |

### Множители
| Метод | Путь | Стр. | Описание |
|---|---|---|---|
| GET | `/admin/multipliers` | 1910 | Текущая таблица множителей. |
| POST | `/admin/multipliers` | 1916 | Заменить набор. Body: `{multipliers: {"model-name": float, …}}`. Сохраняется в `multipliers.json`. |

### Кэш ответов
| Метод | Путь | Описание |
|---|---|---|
| GET | `/admin/cache/stats` | Hits/misses/stores/evictions, hit_rate, size, sample. |
| POST | `/admin/cache/clear` | Wipe кэша; счётчики сохраняются. |

Кэш: in-memory dict, TTL 600с (env `KIRO_CACHE_TTL`), max 10000 записей (`KIRO_CACHE_MAX_ENTRIES`), биллинг хитов 15% (`KIRO_CACHE_BILL_FACTOR`). Eligible: `temperature ≤ 0.3` явно задан, не кэширует ответы с tool_calls. На хите клиенту: `X-Scarlex-Cache: HIT` header + Anthropic `usage.cache_read_input_tokens`.

## Коды ошибок

| Статус | Когда |
|---|---|
| 401 | Нет/неправильный Bearer-токен. |
| 403 | Ключ выключен (`enabled=false`). |
| 402 | Перерасход кредитов при `hard_limit=true`. |
| 429 | Превышен RPM на ключ (или RPM на аккаунт, если внутренний failover не помог). |
| 503 | Все Kiro-аккаунты дохлые или на кулдауне. |
| 502 | Kiro вернул что-то непарсимое из EventStream. |

## Особенности streaming

- При `stream: true` ответ идёт в формате `text/event-stream` (SSE).
- Финальный `data: [DONE]\n\n` — стандарт OpenAI.
- При смерти бота посередине стрима внутри `_stream_response()` ловится сигнал `[RETRY]`, дренаж старого бота, новый `acquire()`, новый запрос. До 3 попыток.
- Не-стримовый ответ через `_full_response()` накапливает чанки и возвращает один JSON.
