# telegram-bot / COMMANDS

Все команды доступны и через слэш (`/cmd`), и через кнопки главного меню. Source: `handlers/__init__.py`.

## Основные команды

| Команда | Хэндлер | PHP-эндпоинт | Описание |
|---|---|---|---|
| `/start` | `cmd_start` | `auth.php` (+ `link_request.php` / `tg_login_confirm.php` при deep-link `link_<token>` / `tglogin_<token>`) | Приветствие, регистрация, обработка deep-link'ов |
| `/help` | `cmd_help` | — / `lang.php` | Главное меню + краткая справка |
| `/shop` | `cmd_shop` | `products.php` | Каталог пакетов. Карточка БЕЗ "📊 В наличии" (товар всегда есть) |
| `/balance` | `cmd_balance` | `balance.php` | Баланс кошелька и инфо по ключам |
| `/key` | `cmd_key` | `my_key.php` | Список ключей (5/страница, пагинация `keys_page:*`) |
| `/topup` | `cmd_topup` | `topup_invoice.php` / `topup_card.php` | Выбор: карта (YooMoney) или крипта (CryptoBot) |
| `/promo` | `cmd_promo` | `promo.php` | Ввод и активация промокода (FSM `PromoStates.waiting_code`) |
| `/ref` | `cmd_ref` | `referrals.php` | Реферальный код, ссылка `scarlex.ru/authorization?ref=…`, статистика, 5 последних рефералов |
| `/history` | `cmd_history` | `history.php` | История транзакций (15/страница, callback `hist_page:N`) |
| `/stats` | `cmd_stats` | `stats.php` | Подробная статистика: burn rate, days_left, top models |
| `/dashboard` | `cmd_dashboard` | `login_link.php` | Кнопки на 3 страницы сайта (home, profile, analytics) + one-time login |
| `/api` | `cmd_api` | — | Инлайн-меню: cURL, Claude CLI, OpenCode, oh-my-opencode, Models, ссылка на полную доку |
| `/lang` | `cmd_lang` | `lang.php` | Переключение RU/EN |
| `/link` | `cmd_link` | `link_request.php` | Привязка TG-аккаунта к существующему пользователю сайта |
| `/settings` | `cmd_settings` | `settings.php` | Toggle ежедневного отчёта + выбор времени (МСК) |

## Callback-обработчики (inline-кнопки)

| Callback | Что делает |
|---|---|
| `buy:<product_id>` | Показать карточку продукта с кнопкой подтверждения |
| `buy_ok:<product_id>` | Подтвердить → `buy.php` → выдать ключ |
| `buy_promo:<product_id>` | FSM: запросить промокод → пересчитать цену → `buy.php` с `promo_code` |
| `topup_method:card` / `topup_method:crypto` | Выбор способа |
| `topup_currency:rub` / `topup_currency:usd` | Валюта для крипты |
| `topup:<amount>` / `topup:custom` | Готовая сумма или произвольная (FSM) |
| `keys_page:<N>` | Пагинация ключей |
| `key:<key_id>` | Детали одного ключа (маскированный preview) |
| `hist_page:<N>` | Пагинация истории |
| `apidoc:<section>` | Показать раздел инструкции (curl / claude / opencode / ohmy / models) |
| `lang:<code>` | Сменить язык |
| `settings:toggle_daily` | Toggle ежедневного отчёта |
| `settings:pick_time` | Открыть выбор часа |
| `settings:hour:<H>` | Выбран час → показать минуты |
| `settings:minute:<H>:<M>` | Сохранить время |

## Deep-link флоу

### TG-логин на сайте
1. На сайте `authorization.html` → кнопка "Войти через Telegram".
2. Бэкенд `tg_login_init.php` → генерит токен, возвращает URL `t.me/scarlex_bot?start=tglogin_<token>`.
3. Пользователь открывает → нажимает /start в TG → `cmd_start` ловит `start tglogin_<token>` → зовёт `tg_login_confirm.php`.
4. Браузер на сайте поллит `tg_login_check.php` → видит `status=confirmed` → создаёт сессию.

### Линковка аккаунта
1. На сайте в профиле → "Привязать Telegram" → `link_request.php` создаёт токен `kind='tg_link'`.
2. Пользователь идёт в бот: `/link <token>` (или через ссылку `t.me/scarlex_bot?start=link_<token>`).
3. Бот зовёт `link_request.php` с `telegram_id + token` → сайт сохраняет `users.telegram_id`.

### Один-разовый логин из бота
1. `/dashboard` → бот зовёт `login_link.php` → получает URL вида `scarlex.ru/?auto_login=<token>`.
2. Пользователь жмёт кнопку → открывается сайт уже залогиненный.

## Платёжные флоу

### Карта (YooMoney)
1. `/topup` → `topup_method:card` → ввод суммы (FSM `TopupStates.waiting_amount_card`).
2. `topup_card.php` → возвращает URL YooMoney-инвойса.
3. Бот шлёт сообщение с кнопкой "Оплатить".
4. После оплаты YooMoney дёргает `api/yoomoney_callback.php` → начисление баланса.
5. Сайт (опционально) уведомляет бот через `_tg_notify.php` о зачислении.

### Крипта (CryptoBot)
1. `/topup` → `topup_method:crypto` → выбор валюты RUB/USD → сумма.
2. `topup_invoice.php` → возвращает CryptoBot-инвойс.
3. После оплаты CryptoBot дёргает `api/bot/cryptobot_webhook.php` (проверка подписи) → начисление.

## Известные TODO (см. `C:\work_proxy_opencode\TODO_BOT.txt`)

- Полная переработка `i18n/__init__.py`: убрать `api.stepanovikov.uno`, обновить модели, добавить блоки `promo_*`, `ref_*`, `history_*`, `stats_full`, `dashboard_*`.
- Доделать `handlers/__init__.py`: добавить FSM для `/promo`, новые callback-цепочки.
- Деплой PHP-эндпоинтов `promo.php`, `referrals.php`, `history.php` через `deploy_v4.py` (шаг 3 TODO).
- Smoke-тест в `@scarlex_bot` по 16-пунктовому списку (шаг 6 TODO).
