# website / PAGES

## Корневые HTML

| Файл | Назначение |
|---|---|
| `index.html` | Публичный лендинг — маркетинг AI-API, цены, фичи. |
| `authorization.html` | Логин/регистрация. Поддерживает обычный email+пароль и Telegram login (deep-link через `tg_login_init.php`). |
| `admin.html` | Админский дашборд: пользователи, ключи, транзакции, создание ключей. Проверяет email = `luckyanencko.andrej2017@yandex.ru`. |
| `kiro.php` | **Серверная** админ-панель для управления пулом Kiro-аккаунтов и множителями. Аутентификация по session + email. Внутри JS бьёт в `api.scarlex.ru/admin/*` с Bearer ADMIN_KEY. |

## Канон kiro.html

Файл `kiro.html` в `scarlex_v2.02` — это полноценная админ-страница для прокси, отдельная от встроенной `panel.html` в `server.py`. На неё ссылается `kiro.php`. Содержит вкладки:

| Вкладка | Что показывает |
|---|---|
| Дашборд | агрегированная статистика, аптайм, RPM, состояние пула |
| API ключи | таблица пользовательских ключей с RPM/credits/topup/delete/bulk |
| Аккаунты | таблица Kiro-аккаунтов: email, credits_used/limit, status, proxy |
| Логи | последние запросы (читает `/admin/logs?limit=N`) |
| Интеграция | curl/Claude CLI/OpenCode-сниппеты для пользователей |
| Мониторинг | графики на основе `/admin/metrics/history` |
| Множители | редактор `MODEL_COST_MULTIPLIER`, POST в `/admin/multipliers` |

## Папка `dashboard/`

Личный кабинет пользователя после логина. Все страницы используют общую сессию и `dashboard.css` + `dashboard.js`.

| Файл | Назначение |
|---|---|
| `home.html` | Главная: баланс, активный ключ, быстрые ссылки |
| `keys.html` | Список API-ключей пользователя, кнопки create/disable/regenerate |
| `key.html` | Детали одного ключа: usage, остаток, история |
| `analytics.html` | Графики использования (модели, токены, дни) |
| `chat.html` | Inline-чат для теста ключа — шлёт прямо в `api.scarlex.ru/v1/chat/completions` |
| `market.html` | Покупка ключей разных пакетов |
| `transactions.html` | История платежей и пополнений |
| `referrals.html` | Реферальный код, статистика, начисления |
| `models.html` | Каталог моделей с описанием и ценами |
| `settings.html` | Безопасность (IP-whitelist на ключ + регенерация ключа), смена email, активные сессии с "выйти везде", настройки уведомлений и время daily report. |
| `profile.html` | Личные данные, смена пароля, привязка Telegram |

## Папка `dashboard_claude/`

Альтернативный вариант дашборда (вариант для интеграции с Claude как клиента). Используется для отдельной целевой группы — детали см. в исходниках.

## Папка `newindex/`

Архив итераций лендинга (`indexv2_tail.html`, `indexv3_bundle.js` и т.п.). Актуальный лендинг — `index.html` в корне. Папку можно использовать как референс старых дизайнов, но прод-копия — только `index.html`.

## Папка `assets/`

- Логотипы (`*.png`, `*.svg`)
- `assets/clients/` — логотипы клиентов для лендинга
- Стили (`dashboard.css`, `v4style.css` — версионирование для cache-busting)
- Скрипты (`dashboard.js`, `v4app.js`, `v4data.js`)

## Защита через `.htaccess`

В корне есть `.htaccess`, который запрещает прямой доступ к:
- `secrets.php`
- `*.db`, `*.sql`
- Внутренним инклюдам (`_*.php`)
