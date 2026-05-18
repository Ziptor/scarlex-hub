"""Minimal i18n for RU / EN."""
from __future__ import annotations

TRANSLATIONS: dict[str, dict[str, str]] = {
    "ru": {
        "start_welcome": "👋 Привет, {name}!\n✅ Ты зарегистрирован.\n\n📋 Помощь",
        "start_existing": "👋 С возвращением, {name}!\n\n📋 Помощь",
        "help": (
            "Используй кнопки внизу для навигации:\n\n"
            "🛒 Магазин — купить токены\n"
            "💵 Баланс — баланс и расход\n"
            "💳 Пополнить — крипта или карта\n"
            "🔑 Мой ключ — API ключ\n"
            "📊 Статистика — тренды, burn rate\n"
            "📜 История — все транзакции\n"
            "🎁 Промокод — активировать\n"
            "👥 Рефералы — пригласи друга\n"
            "📖 API — подключение\n"
            "🌐 Дашборд — открыть сайт\n"
            "⚙ Настройки — уведомления\n"
            "🌐 Язык — сменить язык\n"
            "🔗 Привязать — связать с сайтом\n"
            "❓ Помощь — эта справка\n\n"
            "💬 Поддержка: @{support}"
        ),
        "btn_shop": "🛒 Магазин",
        "btn_balance": "💵 Баланс",
        "btn_topup": "💳 Пополнить",
        "btn_my_key": "🔑 Мой ключ",
        "btn_api": "📖 API",
        "btn_lang": "🌐 Язык",
        "btn_help": "❓ Помощь",
        "btn_link_site": "🔗 Привязать к сайту",
        "btn_settings": "⚙ Настройки",
        "btn_stats": "📊 Статистика",
        "btn_history": "📜 История",
        "btn_promo": "🎁 Промокод",
        "btn_ref": "👥 Рефералы",
        "btn_dashboard": "🌐 Дашборд",
        "btn_back": "⬅ Назад",
        "btn_buy": "🛒 Купить",
        "btn_cancel": "✖ Отмена",

        "settings_title": (
            "⚙ <b>Настройки уведомлений</b>\n\n"
            "📊 <b>Ежедневный отчёт</b> — сводка за вчера в Telegram:\n"
            "запросы, токены, расходы и баланс.\n\n"
            "Время указано по Москве (МСК)."
        ),
        "settings_pick_hour": "🕐 Выберите час (МСК):",
        "settings_pick_minute": "🕐 {hour:02d}:?? — выберите минуты:",
        "settings_saved_time": "✅ Ежедневный отчёт будет приходить в <b>{time}</b> МСК.",
        "settings_saved_toggle_on": "✅ Ежедневный отчёт включён.",
        "settings_saved_toggle_off": "❌ Ежедневный отчёт выключен.",

        "shop_title": "🛒 <b>Магазин</b>\n\nВыберите пакет токенов:",
        "shop_empty": "🛒 Магазин пуст. Товары скоро появятся.",
        "product_card": (
            "📦 <b>{name}</b>{badge_line}\n"
            "💰 Цена: <b>{price_rub}₽</b> (~{price_usd}$)\n"
            "🔢 Токенов: <b>{tokens:,}</b>\n"
            "📊 Ставка: {per_1m}₽ за 1M\n"
            "📝 {desc}"
        ),
        "buy_confirm": "Купить <b>{name}</b> за <b>{price}₽</b>?",
        "buy_success": (
            "✅ <b>Ключ куплен!</b>\n\n"
            "🔑 <code>{key}</code>\n\n"
            "📦 Пакет: {product}\n"
            "💎 Токенов: {tokens:,}\n"
            "💰 Списано: {price}₽{promo_line}\n"
            "💵 Баланс: {balance}₽\n\n"
            "Используй 📖 API чтобы узнать как подключиться."
        ),
        "buy_no_funds": "❌ Недостаточно средств.\nПополните баланс через 💳 Пополнить.",

        "balance_title": (
            "💵 <b>Баланс</b>\n\n"
            "💰 В рублях: <b>{balance_rub}₽</b>\n"
            "💵 В долларах: <b>{balance_usd}$</b>\n"
            "🆔 ID: <code>#{public_id}</code>\n\n"
            "📊 <b>Статистика</b>\n"
            "🔢 Всего токенов: {tokens_total:,}\n"
            "📊 Использовано: {tokens_used:,}\n"
            "📈 Осталось: {tokens_left:,}\n"
            "📡 Запросов: {requests}"
        ),

        "key_title": "🔑 <b>Твои API ключи</b>\n\nВыбери ключ:",
        "key_empty": "❌ У тебя пока нет ключей.\nКупи пакет в 🛒 Магазине.",
        "key_card": (
            "🔑 <b>Ключ #{n}</b>\n\n"
            "<code>{key}</code>\n\n"
            "📦 Пакет: {label}\n"
            "💎 Использовано: <b>{used:,}</b> / {limit:,}\n"
            "📊 {percent}%\n"
            "🗓 Создан: {created}"
        ),
        "btn_key_back": "⬅ К списку ключей",

        "topup_method": "💳 <b>Пополнение баланса</b>\n\nВыберите способ:",
        "btn_topup_card": "💳 Карта/СБП (₽)",
        "btn_topup_crypto": "🪙 Криптовалюта",
        "topup_card_unavailable": "💳 Оплата картой будет доступна скоро. Пока используйте криптовалюту.",
        "topup_card_amounts": "💳 <b>Пополнение картой через ЮMoney</b>\n\nВыберите сумму или введите свою (от 100 до 60 000 ₽):",
        "topup_custom_prompt_card": "Введи сумму в ₽ (от 100 до 60000):",
        "topup_invalid_card": "Неверная сумма. Введи число от 100 до 60 000 ₽.",
        "topup_card_invoice": (
            "🧾 <b>Счёт #{invoice_id}</b>\n\n"
            "💰 Сумма: <b>{amount_rub} ₽</b>\n\n"
            "Нажми кнопку — откроется ЮMoney. Оплати картой РФ или из ЮMoney-кошелька.\n\n"
            "После оплаты баланс зачислится автоматически и придёт уведомление."
        ),
        "topup_crypto_currency": "🪙 <b>Пополнение криптой</b>\n\nВыберите валюту суммы:",
        "btn_cur_rub": "₽ Рубли",
        "btn_cur_usd": "$ Доллары",
        "topup_crypto_amounts_rub": "🪙 <b>Пополнение в ₽</b>\n\nВыберите сумму или введите свою (от 100 до 500000 ₽):",
        "topup_crypto_amounts_usd": "🪙 <b>Пополнение в $</b>\n\nВыберите сумму или введите свою (от 1 до 5000 $):",
        "topup_custom_prompt_rub": "Введи сумму в ₽ (от 100 до 500000):",
        "topup_custom_prompt_usd": "Введи сумму в $ (от 1 до 5000):",
        "topup_invalid_rub": "Неверная сумма. Введи число от 100 до 500000 ₽.",
        "topup_invalid_usd": "Неверная сумма. Введи число от 1 до 5000 $.",
        "topup_invoice": (
            "🧾 <b>Счёт #{invoice_id}</b>\n\n"
            "💰 Сумма: <b>{amount_rub}₽</b> (~${amount_usd})\n\n"
            "Нажми кнопку ниже — откроется CryptoBot, где ты выберешь крипту для оплаты (USDT, TON, BTC, ETH и др.).\n\n"
            "После оплаты баланс зачислится автоматически и придёт уведомление."
        ),
        "btn_pay": "💳 Перейти к оплате",

        "api_doc": (
            "📖 <b>API Инструкция</b>\n\n"
            "🔗 <b>Base URL:</b>\n<code>https://api.scarlex.ru</code>\n\n"
            "🔑 <b>API Key:</b>\nПолучите ключ в 🛒 Магазин, посмотрите в 🔑 Мой ключ\n\n"
            "API совместим с OpenAI и Anthropic форматами."
        ),
        "btn_api_curl": "🔧 cURL",
        "btn_api_claude": "🤖 Claude Code CLI",
        "btn_api_opencode": "⚡️ OpenCode",
        "btn_api_ohmy": "🔌 oh-my-opencode",
        "btn_api_models": "📋 Модели",
        "api_curl": (
            "🔧 <b>cURL</b>\n\n"
            "<pre>curl https://api.scarlex.ru/v1/chat/completions \\\n"
            "  -H \"Content-Type: application/json\" \\\n"
            "  -H \"Authorization: Bearer YOUR_API_KEY\" \\\n"
            "  -d '{\n"
            "    \"model\": \"claude-sonnet-4\",\n"
            "    \"messages\": [\n"
            "      {\"role\": \"user\", \"content\": \"Hello!\"}\n"
            "    ]\n"
            "  }'</pre>"
        ),
        "api_claude": (
            "🤖 <b>Claude Code CLI</b>\n\n"
            "Создайте <code>%USERPROFILE%\\.claude\\settings.json</code>:\n\n"
            "<pre>{\n"
            "  \"env\": {\n"
            "    \"ANTHROPIC_BASE_URL\": \"https://api.scarlex.ru\",\n"
            "    \"ANTHROPIC_AUTH_TOKEN\": \"YOUR_API_KEY\",\n"
            "    \"ANTHROPIC_MODEL\": \"claude-sonnet-4\",\n"
            "    \"ANTHROPIC_SMALL_FAST_MODEL\": \"claude-sonnet-4\",\n"
            "    \"DISABLE_NON_ESSENTIAL_MODEL_CALLS\": \"1\",\n"
            "    \"CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC\": \"1\"\n"
            "  }\n"
            "}</pre>"
        ),
        "api_opencode": (
            "⚡️ <b>OpenCode</b> (opencode.json)\n\n"
            "<pre>{\n"
            "  \"$schema\": \"https://opencode.ai/config.json\",\n"
            "  \"model\": \"SCARLEX/claude-opus-4.6\",\n"
            "  \"provider\": {\n"
            "    \"SCARLEX\": {\n"
            "      \"npm\": \"@ai-sdk/openai-compatible\",\n"
            "      \"name\": \"SCARLEX\",\n"
            "      \"options\": {\n"
            "        \"baseURL\": \"https://api.scarlex.ru\",\n"
            "        \"apiKey\": \"YOUR_API_KEY\"\n"
            "      },\n"
            "      \"models\": {\n"
            "        \"claude-opus-4.7\": {\n"
            "          \"name\": \"Claude Opus 4.7\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 },\n"
            "          \"variants\": { \"high\": {}, \"medium\": {}, \"low\": {} }\n"
            "        },\n"
            "        \"claude-opus-4.6\": {\n"
            "          \"name\": \"Claude Opus 4.6\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 },\n"
            "          \"variants\": { \"high\": {}, \"medium\": {}, \"low\": {} }\n"
            "        },\n"
            "        \"claude-sonnet-4\": {\n"
            "          \"name\": \"Claude Sonnet 4\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 }\n"
            "        },\n"
            "        \"claude-sonnet-4-thinking\": {\n"
            "          \"name\": \"Claude Sonnet 4 Thinking\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 }\n"
            "        },\n"
            "        \"claude-haiku-3.5\": {\n"
            "          \"name\": \"Claude Haiku 3.5\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 }\n"
            "        }\n"
            "      }\n"
            "    }\n"
            "  },\n"
            "  \"plugin\": [\n"
            "    \"oh-my-opencode@latest\"\n"
            "  ]\n"
            "}</pre>"
        ),
        "api_ohmy": (
            "🔌 <b>oh-my-opencode</b> (oh-my-opencode.json)\n\n"
            "Плагин для OpenCode с мульти-агентной системой.\n\n"
            "<b>1.</b> Добавьте в opencode.json:\n"
            "<pre>\"plugin\": [\"oh-my-opencode@latest\"]</pre>\n\n"
            "<b>2.</b> Создайте oh-my-opencode.json:\n"
            "<pre>{\n"
            "  \"agents\": {\n"
            "    \"sisyphus\": { \"model\": \"SCARLEX/claude-opus-4.7\" },\n"
            "    \"oracle\": { \"model\": \"SCARLEX/claude-opus-4.7\" },\n"
            "    \"librarian\": { \"model\": \"SCARLEX/claude-sonnet-4\" },\n"
            "    \"explore\": { \"model\": \"SCARLEX/claude-sonnet-4\" },\n"
            "    \"prometheus\": { \"model\": \"SCARLEX/claude-opus-4.6\" },\n"
            "    \"metis\": { \"model\": \"SCARLEX/claude-sonnet-4-thinking\" },\n"
            "    \"momus\": { \"model\": \"SCARLEX/claude-opus-4.6\" }\n"
            "  },\n"
            "  \"categories\": {\n"
            "    \"visual-engineering\": { \"model\": \"SCARLEX/claude-opus-4.7\" },\n"
            "    \"ultrabrain\": { \"model\": \"SCARLEX/claude-opus-4.7\" },\n"
            "    \"quick\": { \"model\": \"SCARLEX/claude-sonnet-4\" },\n"
            "    \"deep\": { \"model\": \"SCARLEX/claude-opus-4.6\" }\n"
            "  }\n"
            "}</pre>"
        ),
        "api_models": (
            "📋 <b>Доступные модели</b>\n\n"
            "• claude-haiku-3.5 — Haiku 3.5\n"
            "• claude-sonnet-4 — Sonnet 4\n"
            "• claude-sonnet-4-thinking — Sonnet 4 (thinking)\n"
            "• claude-opus-4.6 — Opus 4.6\n"
            "• claude-opus-4.7 — Opus 4.7"
        ),

        "promo_intro": "🎁 Введите промокод:",
        "promo_success": "✅ Промокод активирован!\n\n💰 Начислено: <b>{amount}₽</b>\n💵 Баланс: <b>{balance}₽</b>",
        "promo_fail": "❌ Промокод недействителен или уже использован.",
        "buy_promo_intro": "🎁 Введите промокод-скидку на покупку <b>{name}</b>:\n\nИли нажмите «Отмена», чтобы купить без скидки.",
        "buy_promo_applied": "✅ Промокод <code>{code}</code> применён: −{discount}₽.\nИтоговая цена: <b>{final}₽</b>",
        "buy_promo_invalid": "❌ {error}\n\nПопробуйте ещё раз или нажмите «Отмена».",
        "trial_blocked": (
            "🎁 <b>Trial-ключ</b>\n\n"
            "Триал доступен только на сайте — нужна верифицированная почта.\n"
            "Перейдите на <b>scarlex.ru</b>, зарегистрируйтесь и заберите бесплатные токены."
        ),
        "ref_card": (
            "👥 <b>Реферальная программа</b>\n\n"
            "🔗 Код: <code>{code}</code>\n"
            "🔗 Ссылка: {link}\n\n"
            "📊 Всего приглашено: {total}\n"
            "✅ Активных: {active}\n"
            "💰 Заработано: <b>{earned}₽</b>"
        ),
        "history_empty": "📜 История пуста.",
        "history_header": "📜 <b>История транзакций</b>\n\n",
        "stats_full": (
            "📊 <b>Статистика</b>\n\n"
            "🔢 Всего токенов: <b>{tokens_total:,}</b>\n"
            "📊 Использовано: <b>{tokens_used:,}</b>\n"
            "📈 Осталось: <b>{tokens_left:,}</b>\n"
            "📡 Запросов: <b>{requests}</b>\n\n"
            "🔥 <b>Burn rate:</b> {burn_rate}\n"
            "⏳ Осталось дней: {days_left}\n\n"
            "🏆 <b>Топ моделей:</b>\n{top_models}"
        ),
        "dashboard_intro": "🌐 <b>Дашборд Scarlex</b>\n\nОткройте нужный раздел:",

        "lang_title": "🌐 Выбери язык:",
        "lang_set": "✅ Язык изменён",

        "link_intro": (
            "🔗 <b>Привязать Telegram к сайту</b>\n\n"
            "Твой код: <code>{code}</code>\n\n"
            "1. Зайди на {site}\n"
            "2. Авторизуйся\n"
            "3. Открой Профиль → Привязать Telegram\n"
            "4. Введи код выше\n\n"
            "Код действует 15 минут."
        ),

        "error_generic": "❌ Ошибка: {error}",
        "loading": "⏳ Загрузка...",
    },
    "en": {
        "start_welcome": "👋 Hi, {name}!\n✅ You are registered.\n\n📋 Help",
        "start_existing": "👋 Welcome back, {name}!\n\n📋 Help",
        "help": (
            "Use buttons below to navigate:\n\n"
            "🛒 Shop — buy token packs\n"
            "💵 Balance — balance & usage\n"
            "💳 Top up — crypto or card\n"
            "🔑 My key — API key\n"
            "📊 Stats — trends, burn rate\n"
            "📜 History — all transactions\n"
            "🎁 Promo — activate code\n"
            "👥 Referrals — invite friends\n"
            "📖 API — integration guide\n"
            "🌐 Dashboard — open website\n"
            "⚙ Settings — notifications\n"
            "🌐 Language — change language\n"
            "🔗 Link — connect to website\n"
            "❓ Help — this message\n\n"
            "💬 Support: @{support}"
        ),
        "btn_shop": "🛒 Shop",
        "btn_balance": "💵 Balance",
        "btn_topup": "💳 Top up",
        "btn_my_key": "🔑 My key",
        "btn_api": "📖 API",
        "btn_lang": "🌐 Language",
        "btn_help": "❓ Help",
        "btn_link_site": "🔗 Link to website",
        "btn_settings": "⚙ Settings",
        "btn_stats": "📊 Stats",
        "btn_history": "📜 History",
        "btn_promo": "🎁 Promo",
        "btn_ref": "👥 Referrals",
        "btn_dashboard": "🌐 Dashboard",
        "btn_back": "⬅ Back",
        "btn_buy": "🛒 Buy",
        "btn_cancel": "✖ Cancel",

        "settings_title": (
            "⚙ <b>Notification settings</b>\n\n"
            "📊 <b>Daily report</b> — yesterday's summary in Telegram:\n"
            "requests, tokens, spend, balance.\n\n"
            "Time is in Moscow timezone (MSK / UTC+3)."
        ),
        "settings_pick_hour": "🕐 Pick hour (MSK):",
        "settings_pick_minute": "🕐 {hour:02d}:?? — pick minutes:",
        "settings_saved_time": "✅ Daily report will arrive at <b>{time}</b> MSK.",
        "settings_saved_toggle_on": "✅ Daily report enabled.",
        "settings_saved_toggle_off": "❌ Daily report disabled.",

        "shop_title": "🛒 <b>Shop</b>\n\nChoose a token pack:",
        "shop_empty": "🛒 Shop is empty. Products coming soon.",
        "product_card": (
            "📦 <b>{name}</b>{badge_line}\n"
            "💰 Price: <b>{price_rub}₽</b> (~{price_usd}$)\n"
            "🔢 Tokens: <b>{tokens:,}</b>\n"
            "📊 Rate: {per_1m}₽ per 1M\n"
            "📝 {desc}"
        ),
        "buy_confirm": "Buy <b>{name}</b> for <b>{price}₽</b>?",
        "buy_success": (
            "✅ <b>Key purchased!</b>\n\n"
            "🔑 <code>{key}</code>\n\n"
            "📦 Pack: {product}\n"
            "💎 Tokens: {tokens:,}\n"
            "💰 Charged: {price}₽{promo_line}\n"
            "💵 Balance: {balance}₽\n\n"
            "Use 📖 API for integration guide."
        ),
        "buy_no_funds": "❌ Not enough funds.\nUse 💳 Top up.",

        "balance_title": (
            "💵 <b>Balance</b>\n\n"
            "💰 In RUB: <b>{balance_rub}₽</b>\n"
            "💵 In USD: <b>{balance_usd}$</b>\n"
            "🆔 ID: <code>#{public_id}</code>\n\n"
            "📊 <b>Stats</b>\n"
            "🔢 Total tokens: {tokens_total:,}\n"
            "📊 Used: {tokens_used:,}\n"
            "📈 Remaining: {tokens_left:,}\n"
            "📡 Requests: {requests}"
        ),

        "key_title": "🔑 <b>Your API keys</b>\n\nPick a key:",
        "key_empty": "❌ No keys yet.\nBuy a pack in the 🛒 Shop.",
        "key_card": (
            "🔑 <b>Key #{n}</b>\n\n"
            "<code>{key}</code>\n\n"
            "📦 Pack: {label}\n"
            "💎 Used: <b>{used:,}</b> / {limit:,}\n"
            "📊 {percent}%\n"
            "🗓 Created: {created}"
        ),
        "btn_key_back": "⬅ Back to list",

        "topup_method": "💳 <b>Top up balance</b>\n\nChoose method:",
        "btn_topup_card": "💳 Card/SBP (₽)",
        "btn_topup_crypto": "🪙 Crypto",
        "topup_card_unavailable": "💳 Card payments coming soon. Please use crypto for now.",
        "topup_card_amounts": "💳 <b>Card top-up via YooMoney</b>\n\nChoose amount or enter your own (100 - 60 000 ₽):",
        "topup_custom_prompt_card": "Enter amount in ₽ (100 - 60000):",
        "topup_invalid_card": "Invalid. Enter a number between 100 and 60 000 ₽.",
        "topup_card_invoice": (
            "🧾 <b>Invoice #{invoice_id}</b>\n\n"
            "💰 Amount: <b>{amount_rub} ₽</b>\n\n"
            "Tap the button — YooMoney will open. Pay with a Russian card or from your YooMoney wallet.\n\n"
            "Balance will be credited automatically + you'll get a notification."
        ),
        "topup_crypto_currency": "🪙 <b>Crypto top-up</b>\n\nChoose amount currency:",
        "btn_cur_rub": "₽ Rubles",
        "btn_cur_usd": "$ Dollars",
        "topup_crypto_amounts_rub": "🪙 <b>Top up in ₽</b>\n\nChoose amount or enter your own (100 - 500000 ₽):",
        "topup_crypto_amounts_usd": "🪙 <b>Top up in $</b>\n\nChoose amount or enter your own (1 - 5000 $):",
        "topup_custom_prompt_rub": "Enter amount in ₽ (100 - 500000):",
        "topup_custom_prompt_usd": "Enter amount in $ (1 - 5000):",
        "topup_invalid_rub": "Invalid. Enter a number between 100 and 500000 ₽.",
        "topup_invalid_usd": "Invalid. Enter a number between 1 and 5000 $.",
        "topup_invoice": (
            "🧾 <b>Invoice #{invoice_id}</b>\n\n"
            "💰 Amount: <b>{amount_rub}₽</b> (~${amount_usd})\n\n"
            "Tap the button below — CryptoBot will open where you can choose crypto (USDT, TON, BTC, ETH etc.).\n\n"
            "Balance will be credited automatically + you'll get a notification."
        ),
        "btn_pay": "💳 Open payment",

        "api_doc": (
            "📖 <b>API Guide</b>\n\n"
            "🔗 <b>Base URL:</b>\n<code>https://api.scarlex.ru</code>\n\n"
            "🔑 <b>API Key:</b>\nGet one from 🛒 Shop, view in 🔑 My key\n\n"
            "API is compatible with OpenAI and Anthropic formats."
        ),
        "btn_api_curl": "🔧 cURL",
        "btn_api_claude": "🤖 Claude Code CLI",
        "btn_api_opencode": "⚡️ OpenCode",
        "btn_api_ohmy": "🔌 oh-my-opencode",
        "btn_api_models": "📋 Models",
        "api_curl": (
            "🔧 <b>cURL</b>\n\n"
            "<pre>curl https://api.scarlex.ru/v1/chat/completions \\\n"
            "  -H \"Content-Type: application/json\" \\\n"
            "  -H \"Authorization: Bearer YOUR_API_KEY\" \\\n"
            "  -d '{\n"
            "    \"model\": \"claude-sonnet-4\",\n"
            "    \"messages\": [\n"
            "      {\"role\": \"user\", \"content\": \"Hello!\"}\n"
            "    ]\n"
            "  }'</pre>"
        ),
        "api_claude": (
            "🤖 <b>Claude Code CLI</b>\n\n"
            "Create <code>%USERPROFILE%\\.claude\\settings.json</code>:\n\n"
            "<pre>{\n"
            "  \"env\": {\n"
            "    \"ANTHROPIC_BASE_URL\": \"https://api.scarlex.ru\",\n"
            "    \"ANTHROPIC_AUTH_TOKEN\": \"YOUR_API_KEY\",\n"
            "    \"ANTHROPIC_MODEL\": \"claude-sonnet-4\",\n"
            "    \"ANTHROPIC_SMALL_FAST_MODEL\": \"claude-sonnet-4\",\n"
            "    \"DISABLE_NON_ESSENTIAL_MODEL_CALLS\": \"1\",\n"
            "    \"CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC\": \"1\"\n"
            "  }\n"
            "}</pre>"
        ),
        "api_opencode": (
            "⚡️ <b>OpenCode</b> (opencode.json)\n\n"
            "<pre>{\n"
            "  \"$schema\": \"https://opencode.ai/config.json\",\n"
            "  \"model\": \"SCARLEX/claude-opus-4.6\",\n"
            "  \"provider\": {\n"
            "    \"SCARLEX\": {\n"
            "      \"npm\": \"@ai-sdk/openai-compatible\",\n"
            "      \"name\": \"SCARLEX\",\n"
            "      \"options\": {\n"
            "        \"baseURL\": \"https://api.scarlex.ru\",\n"
            "        \"apiKey\": \"YOUR_API_KEY\"\n"
            "      },\n"
            "      \"models\": {\n"
            "        \"claude-opus-4.7\": {\n"
            "          \"name\": \"Claude Opus 4.7\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 },\n"
            "          \"variants\": { \"high\": {}, \"medium\": {}, \"low\": {} }\n"
            "        },\n"
            "        \"claude-opus-4.6\": {\n"
            "          \"name\": \"Claude Opus 4.6\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 },\n"
            "          \"variants\": { \"high\": {}, \"medium\": {}, \"low\": {} }\n"
            "        },\n"
            "        \"claude-sonnet-4\": {\n"
            "          \"name\": \"Claude Sonnet 4\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 }\n"
            "        },\n"
            "        \"claude-sonnet-4-thinking\": {\n"
            "          \"name\": \"Claude Sonnet 4 Thinking\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 }\n"
            "        },\n"
            "        \"claude-haiku-3.5\": {\n"
            "          \"name\": \"Claude Haiku 3.5\",\n"
            "          \"limit\": { \"context\": 200000, \"output\": 64000 }\n"
            "        }\n"
            "      }\n"
            "    }\n"
            "  },\n"
            "  \"plugin\": [\n"
            "    \"oh-my-opencode@latest\"\n"
            "  ]\n"
            "}</pre>"
        ),
        "api_ohmy": (
            "🔌 <b>oh-my-opencode</b> (oh-my-opencode.json)\n\n"
            "OpenCode plugin with multi-agent system.\n\n"
            "<b>1.</b> Add to opencode.json:\n"
            "<pre>\"plugin\": [\"oh-my-opencode@latest\"]</pre>\n\n"
            "<b>2.</b> Create oh-my-opencode.json:\n"
            "<pre>{\n"
            "  \"agents\": {\n"
            "    \"sisyphus\": { \"model\": \"SCARLEX/claude-opus-4.7\" },\n"
            "    \"oracle\": { \"model\": \"SCARLEX/claude-opus-4.7\" },\n"
            "    \"librarian\": { \"model\": \"SCARLEX/claude-sonnet-4\" },\n"
            "    \"explore\": { \"model\": \"SCARLEX/claude-sonnet-4\" },\n"
            "    \"prometheus\": { \"model\": \"SCARLEX/claude-opus-4.6\" },\n"
            "    \"metis\": { \"model\": \"SCARLEX/claude-sonnet-4-thinking\" },\n"
            "    \"momus\": { \"model\": \"SCARLEX/claude-opus-4.6\" }\n"
            "  },\n"
            "  \"categories\": {\n"
            "    \"visual-engineering\": { \"model\": \"SCARLEX/claude-opus-4.7\" },\n"
            "    \"ultrabrain\": { \"model\": \"SCARLEX/claude-opus-4.7\" },\n"
            "    \"quick\": { \"model\": \"SCARLEX/claude-sonnet-4\" },\n"
            "    \"deep\": { \"model\": \"SCARLEX/claude-opus-4.6\" }\n"
            "  }\n"
            "}</pre>"
        ),
        "api_models": (
            "📋 <b>Available models</b>\n\n"
            "• claude-haiku-3.5 — Haiku 3.5\n"
            "• claude-sonnet-4 — Sonnet 4\n"
            "• claude-sonnet-4-thinking — Sonnet 4 (thinking)\n"
            "• claude-opus-4.6 — Opus 4.6\n"
            "• claude-opus-4.7 — Opus 4.7"
        ),

        "promo_intro": "🎁 Enter promo code:",
        "promo_success": "✅ Promo code activated!\n\n💰 Credited: <b>{amount}₽</b>\n💵 Balance: <b>{balance}₽</b>",
        "promo_fail": "❌ Promo code is invalid or already used.",
        "buy_promo_intro": "🎁 Enter a discount promo code for <b>{name}</b>:\n\nOr tap Cancel to buy without discount.",
        "buy_promo_applied": "✅ Promo <code>{code}</code> applied: −{discount}₽.\nFinal price: <b>{final}₽</b>",
        "buy_promo_invalid": "❌ {error}\n\nTry again or tap Cancel.",
        "trial_blocked": (
            "🎁 <b>Trial key</b>\n\n"
            "Trial is available only on the website — a verified email is required.\n"
            "Open <b>scarlex.ru</b>, sign up and grab your free tokens."
        ),
        "ref_card": (
            "👥 <b>Referral Program</b>\n\n"
            "🔗 Code: <code>{code}</code>\n"
            "🔗 Link: {link}\n\n"
            "📊 Total invited: {total}\n"
            "✅ Active: {active}\n"
            "💰 Earned: <b>{earned}₽</b>"
        ),
        "history_empty": "📜 No transactions yet.",
        "history_header": "📜 <b>Transaction History</b>\n\n",
        "stats_full": (
            "📊 <b>Statistics</b>\n\n"
            "🔢 Total tokens: <b>{tokens_total:,}</b>\n"
            "📊 Used: <b>{tokens_used:,}</b>\n"
            "📈 Remaining: <b>{tokens_left:,}</b>\n"
            "📡 Requests: <b>{requests}</b>\n\n"
            "🔥 <b>Burn rate:</b> {burn_rate}\n"
            "⏳ Days left: {days_left}\n\n"
            "🏆 <b>Top models:</b>\n{top_models}"
        ),
        "dashboard_intro": "🌐 <b>Scarlex Dashboard</b>\n\nOpen a section:",

        "lang_title": "🌐 Choose language:",
        "lang_set": "✅ Language changed",

        "link_intro": (
            "🔗 <b>Link Telegram to website</b>\n\n"
            "Your code: <code>{code}</code>\n\n"
            "1. Open {site}\n"
            "2. Sign in\n"
            "3. Profile → Link Telegram\n"
            "4. Enter the code above\n\n"
            "Code expires in 15 minutes."
        ),

        "error_generic": "❌ Error: {error}",
        "loading": "⏳ Loading...",
    },
}


def t(lang: str, msg_key: str, /, **kwargs) -> str:
    base = TRANSLATIONS.get(lang) or TRANSLATIONS["ru"]
    template = base.get(msg_key) or TRANSLATIONS["ru"].get(msg_key, msg_key)
    try:
        return template.format(**kwargs)
    except (KeyError, IndexError):
        return template
