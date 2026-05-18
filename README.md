# Scarlex Hub

Единая точка входа в документацию по экосистеме **Scarlex** — SaaS-сервису, который перепродаёт доступ к Claude через AWS Kiro по OpenAI-совместимому API.

## Что здесь

Эта папка — **навигатор и контекст** для будущих сессий (AI-агентов и людей). Сам исходный код живёт в других местах — здесь только описания, схемы, инструкции по деплою и сводные таблицы.

```
scarlex-hub/
├── README.md            ← вы здесь
├── AGENTS.md            ← контекст для AI-агентов: credentials, конвенции, workflow
├── ARCHITECTURE.md      ← как связаны прокси ↔ сайт ↔ бот
├── vps-proxy/           ← Python/FastAPI прокси на VPS (api.scarlex.ru)
├── website/             ← scarlex.ru: лендинг, дашборд, PHP backend
├── telegram-bot/        ← @scarlex_bot (aiogram, /opt/scarlex-bot)
└── scripts/             ← утилиты деплоя/тестов из ~/AppData/Local/Temp/opencode
```

## Где лежат настоящие исходники

| Компонент | Локальный путь | Удалённый путь |
|---|---|---|
| VPS-прокси | `C:\work_proxy_opencode\` | `/opt/kiro-proxy/` на VPS `62.109.20.160` |
| Сайт (канон) | `C:\Users\lucky\OneDrive\Desktop\project\scarlex_v2.02\` | `/www/scarlex.ru/` на shared hosting `37.140.192.187` |
| Telegram-бот | `C:\Users\lucky\.gemini\antigravity\scratch\scarlex\` | `/opt/scarlex-bot/` на VPS `62.109.20.160` |
| Утилиты | `C:\Users\lucky\AppData\Local\Temp\opencode\` | — (только локально) |

## С чего начать

1. **AI-агент стартует сессию** → читает [`AGENTS.md`](AGENTS.md) (credentials, конвенции, workflow деплоя).
2. **Нужна общая картина** → [`ARCHITECTURE.md`](ARCHITECTURE.md) (как компоненты общаются).
3. **Работа над конкретным компонентом** → его подпапка, начиная с `README.md` в ней.

## Внешние домены и сервисы

- `https://api.scarlex.ru` — OpenAI-совместимый прокси (nginx → 127.0.0.1:8000)
- `https://scarlex.ru` — публичный сайт + PHP-бэкенд для бота и дашборда
- `@scarlex_bot` — Telegram-бот (BotFather token в `.env` на VPS)
- `kiro.dev` / AWS CodeWhisperer (`us-east-1`) — внешний апстрим, к которому проксирует прокси
