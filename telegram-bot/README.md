# Scarlex Telegram Bot

`@scarlex_bot` — пользовательский интерфейс Scarlex в Telegram. Регистрация по `/start`, магазин пакетов, баланс, мой ключ, пополнение через CryptoBot, привязка к аккаунту на scarlex.ru.

## Архитектура

```
┌─────────────────┐     HTTP+secret      ┌──────────────────┐
│  scarlex-bot    │  ───────────────────►│   scarlex.ru     │
│  (VPS, aiogram) │                      │   /api/bot/*.php │
└─────────────────┘                      └──────────────────┘
        │                                         │
        │ getUpdates                              │
        ▼                                         ▼
   Telegram API                              MySQL (REG.RU)
        ▲                                         ▲
        │ webhook                                 │
   CryptoBot ──────► /api/bot/cryptobot_webhook.php
```

- Бот не имеет прямого доступа к БД. Все операции — через HTTPS на `scarlex.ru/api/bot/*` с заголовком `X-Bot-Secret`.
- CryptoBot шлёт webhook напрямую на `scarlex.ru/api/bot/cryptobot_webhook.php` (подпись HMAC-SHA256 проверяется).

## Установка на Ubuntu VPS

### 1. Системные зависимости

```bash
sudo apt update
sudo apt install -y python3 python3-venv python3-pip git
sudo useradd --system --home /opt/scarlex-bot --shell /usr/sbin/nologin scarlex
sudo mkdir -p /opt/scarlex-bot /var/log/scarlex-bot
sudo chown -R scarlex:scarlex /opt/scarlex-bot /var/log/scarlex-bot
```

### 2. Залить код

```bash
sudo -u scarlex git clone <your-repo>.git /opt/scarlex-bot
# или просто scp -r ./bot/* scarlex@vps:/opt/scarlex-bot/
```

### 3. Виртуальное окружение

```bash
sudo -u scarlex python3 -m venv /opt/scarlex-bot/venv
sudo -u scarlex /opt/scarlex-bot/venv/bin/pip install -r /opt/scarlex-bot/requirements.txt
```

### 4. Конфиг

```bash
sudo -u scarlex cp /opt/scarlex-bot/.env.example /opt/scarlex-bot/.env
sudo -u scarlex nano /opt/scarlex-bot/.env
```

Обязательные поля:
- `BOT_TOKEN` — `@BotFather`
- `BOT_SHARED_SECRET` — должен совпадать с `BOT_SHARED_SECRET` в `api/bot/_auth.php`
- `CRYPTOBOT_TOKEN` — получить у `@CryptoBot` → Crypto Pay → Create App

```bash
sudo chmod 600 /opt/scarlex-bot/.env
```

### 5. systemd

```bash
sudo cp /opt/scarlex-bot/scarlex-bot.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now scarlex-bot
sudo systemctl status scarlex-bot
sudo journalctl -u scarlex-bot -f
```

### 6. CryptoBot webhook

В `@CryptoBot` → My Apps → Webhooks:

```
URL: https://scarlex.ru/api/bot/cryptobot_webhook.php
Events: invoice_paid
```

Проверить подпись: запрос приходит с заголовком `Crypto-Pay-API-Signature` = HMAC-SHA256(body, SHA256(token)).

### 7. На стороне scarlex.ru

Убедиться, что `api/bot/_auth.php` содержит тот же секрет, что и `BOT_SHARED_SECRET` в `.env`. Файлы `api/bot/*.php` должны быть залиты на хостинг через FTP.

`api/bot/_cryptobot_config.php` тоже хранит токен CryptoBot на стороне сайта (для верификации подписи webhook). Можно вынести в env через `getenv('CRYPTOBOT_TOKEN')`.

## Локальный запуск (разработка)

```bash
cd bot
python -m venv venv
venv/Scripts/activate     # Windows
# source venv/bin/activate # Linux

pip install -r requirements.txt
copy .env.example .env    # отредактировать
python bot.py
```

## Команды

| Команда         | Описание                       |
|-----------------|--------------------------------|
| `/start`        | Регистрация / приветствие      |
| `/shop`         | Магазин пакетов                |
| `/balance`      | Баланс и статистика            |
| `/topup`        | Пополнить через крипту         |
| `/key`          | Показать API ключи             |
| `/api`          | Инструкция по подключению      |
| `/lang`         | Сменить язык (RU/EN)           |
| `/link`         | Привязать TG к аккаунту сайта  |
| `/help`         | Справка                        |

## Endpoints на scarlex.ru/api/bot

Все требуют `X-Bot-Secret`, кроме webhook.

| Endpoint                  | Описание                                  |
|---------------------------|-------------------------------------------|
| `auth.php`                | Создать/получить пользователя по TG ID    |
| `products.php`            | Список тарифов с RUB-ценами               |
| `balance.php`             | Баланс + транзакции + usage               |
| `my_key.php`              | Активные ключи пользователя               |
| `buy.php`                 | Купить пакет (атомарно, reserve→reseller) |
| `link_request.php`        | Сгенерить 6-значный код для линковки      |
| `topup_invoice.php`       | Создать счёт CryptoBot                    |
| `lang.php`                | get/set язык                              |
| `cryptobot_webhook.php`   | Приём оплаты (без секрета, проверка HMAC) |

## Обновление

```bash
cd /opt/scarlex-bot
sudo -u scarlex git pull
sudo -u scarlex venv/bin/pip install -r requirements.txt
sudo systemctl restart scarlex-bot
```

## Логи

```bash
sudo journalctl -u scarlex-bot -f
tail -f /var/log/scarlex-bot/bot.log
tail -f /var/log/scarlex-bot/bot.err.log
```
