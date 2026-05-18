# telegram-bot / ARCHITECTURE

## Entry point — `bot.py`

```python
# Упрощённо
bot = Bot(token=BOT_TOKEN, proxy=PROXY_URL)
dp  = Dispatcher(storage=MemoryStorage())
dp.include_router(router)        # из handlers/__init__.py
asyncio.run(dp.start_polling(bot))
```

- **aiogram 3.13** — async-first.
- **MemoryStorage** для FSM — стейт держится в памяти процесса. Перезапуск → пользователи в FSM сбрасываются (приемлемо, поскольку формы короткие).
- **Polling**, не webhook. Проще в эксплуатации, не нужен публичный endpoint у бота. При деплое на VPS сидит за `aiohttp-socks`-прокси, если у Telegram есть проблемы с прямым доступом.

## Слои

```
Telegram → bot.py (polling) → handlers/__init__.py → api_client.py → scarlex.ru/api/bot/*.php
                                  │
                                  ├─ keyboards.py (inline + reply)
                                  └─ i18n/__init__.py (t(lang, key, …))
```

- **handlers** — единственный файл, всё через декораторы `@router.message`, `@router.callback_query`. Чистая бизнес-логика; никаких HTTP-запросов напрямую.
- **api_client.py** — фасад над `aiohttp.ClientSession`. Один метод на эндпоинт. Возвращает dict / выбрасывает исключение.
- **keyboards.py** — фабрики `InlineKeyboardMarkup` / `ReplyKeyboardMarkup`. Принимают язык + данные.
- **i18n** — `t(lang, key, **kwargs)`. Шаблоны строк форматируются на лету.

## FSM-состояния

```python
class TopupStates(StatesGroup):
    waiting_amount_rub  = State()
    waiting_amount_usd  = State()
    waiting_amount_card = State()

class PromoStates(StatesGroup):
    waiting_code      = State()
    waiting_buy_promo = State()
```

Используются для многошаговых форм: ввод суммы пополнения, ввод промокода. При входе — `state.set_state(...)`, при сообщении в этом состоянии — соответствующий хэндлер, после успеха — `state.clear()`.

## Callback-формат

Все inline-кнопки используют `data` с префиксом и параметрами через двоеточие:

| Префикс | Параметры | Что делает |
|---|---|---|
| `buy:<product_id>` | | Показать карточку продукта |
| `buy_ok:<product_id>` | | Подтвердить покупку → API |
| `buy_promo:<product_id>` | | Запросить промокод перед покупкой (FSM) |
| `settings:toggle_daily` | | Включить/выключить ежедневный отчёт |
| `settings:pick_time` | | Открыть выбор времени |
| `settings:hour:<H>` | | Выбрать час |
| `settings:minute:<H>:<M>` | | Выбрать минуту → сохранить |
| `topup_method:<method>` | `card` / `crypto` | Выбор способа |
| `topup_currency:<cur>` | `rub` / `usd` | Выбор валюты для крипты |
| `topup:<amount>` | | Готовая сумма |
| `topup:custom` | | Произвольная сумма (FSM) |
| `keys_page:<N>` | | Пагинация ключей |
| `key:<key_id>` | | Просмотр конкретного ключа |
| `hist_page:<N>` | | Пагинация истории |
| `apidoc:<section>` | `curl` / `claude` / `opencode` / `ohmy` / `models` | Раздел инструкции по API |
| `lang:<code>` | `ru` / `en` | Смена языка |

## i18n

Файл `i18n/__init__.py` — словарь словарей: `{"ru": {"key": "...", …}, "en": {...}}`. Функция `t(lang, key, **kwargs)` подставляет значения через `str.format`.

Полный список ключей: см. источник + `TODO_BOT.txt` (шаг 1) — там список всего, что нужно добавить/обновить. Сейчас актуальные группы:
- `start_*`, `help`
- `main_menu_*`, `btn_*`
- `product_card`, `buy_*`
- `balance_title`, `balance_card`
- `key_title`, `key_card`
- `topup_*`
- `settings_*`, `daily_*`
- `api_curl`, `api_claude`, `api_opencode`, `api_ohmy`, `api_models`, `api_doc`
- `promo_*`, `ref_*`, `history_*`, `stats_*`, `dashboard_*`
- `error_generic`

## Клавиатуры

| Функция | Что |
|---|---|
| `main_menu(lang)` | главное меню — reply keyboard 8×2 со всеми разделами |
| `shop_kb(lang, products)` | inline-список товаров |
| `confirm_buy_kb(lang, product_id, has_promo)` | подтверждение покупки + промокод |
| `topup_method_kb(lang)` | картой / криптой |
| `topup_currency_kb(lang)` | RUB / USD |
| `topup_amounts_kb(lang, currency)` | пресетные суммы + ручной ввод |
| `keys_list_kb(lang, keys, page)` | список ключей 5/страница |
| `key_back_kb(lang)` | "← назад к списку" |
| `settings_kb(lang, daily_enabled, daily_time)` | toggle + выбор времени |
| `time_picker_hours_kb(lang)` | сетка 0–23 (4 колонки) |
| `time_picker_minutes_kb(lang, hour)` | `HH:00` / `HH:30` |
| `api_menu_kb(lang)` | разделы интеграции |
| `history_kb(lang, page, pages)` | пагинация |
| `dashboard_kb(lang, login_url)` | one-time login + ссылки на dashboard |
| `lang_kb()` | флаги RU / EN |

## API-клиент

`api_client.py` экспортирует методы (см. [`COMMANDS.md`](COMMANDS.md) где они используются):

- `auth_user`, `products`, `balance`, `my_key`, `buy`
- `link_request`, `link_consume`, `login_link`, `tg_login_confirm`
- `topup_invoice`, `topup_card`
- `promo`, `referrals`, `history`, `stats`
- `get_lang`, `set_lang`, `get_settings`, `set_settings`

Все принимают `telegram_id` как первый аргумент, бьют POST с `X-Bot-Secret`, возвращают dict из `data` или поднимают исключение. Ошибочные ответы (`{"ok": false, "error": …}`) логируются и пользователю отдаётся `t(lang, "error_generic")`.
