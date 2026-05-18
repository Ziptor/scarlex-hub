"""Reply / inline keyboards."""
from aiogram.types import (
    KeyboardButton, ReplyKeyboardMarkup,
    InlineKeyboardButton, InlineKeyboardMarkup,
)

from i18n import t


def main_menu(lang: str) -> ReplyKeyboardMarkup:
    return ReplyKeyboardMarkup(
        keyboard=[
            [KeyboardButton(text=t(lang, "btn_shop")), KeyboardButton(text=t(lang, "btn_balance"))],
            [KeyboardButton(text=t(lang, "btn_topup")), KeyboardButton(text=t(lang, "btn_my_key"))],
            [KeyboardButton(text=t(lang, "btn_stats")), KeyboardButton(text=t(lang, "btn_history"))],
            [KeyboardButton(text=t(lang, "btn_promo")), KeyboardButton(text=t(lang, "btn_ref"))],
            [KeyboardButton(text=t(lang, "btn_api")), KeyboardButton(text=t(lang, "btn_dashboard"))],
            [KeyboardButton(text=t(lang, "btn_settings")), KeyboardButton(text=t(lang, "btn_lang"))],
            [KeyboardButton(text=t(lang, "btn_link_site"))],
            [KeyboardButton(text=t(lang, "btn_help"))],
        ],
        resize_keyboard=True,
    )


def settings_kb(lang: str, daily_enabled: int, daily_time: str) -> InlineKeyboardMarkup:
    """Top-level settings menu: toggle + pick time."""
    toggle_text = (
        ("✅ Ежедневный отчёт: вкл" if daily_enabled else "❌ Ежедневный отчёт: выкл")
        if lang == "ru"
        else ("✅ Daily report: ON" if daily_enabled else "❌ Daily report: OFF")
    )
    time_text = (
        f"🕐 Время: {daily_time}" if lang == "ru" else f"🕐 Time: {daily_time}"
    )
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=toggle_text, callback_data="settings:toggle_daily")],
        [InlineKeyboardButton(text=time_text, callback_data="settings:pick_time")],
    ])


def time_picker_hours_kb(lang: str) -> InlineKeyboardMarkup:
    rows = []
    for row_start in range(0, 24, 4):
        row = [
            InlineKeyboardButton(text=f"{h:02d}", callback_data=f"settings:hour:{h}")
            for h in range(row_start, row_start + 4)
        ]
        rows.append(row)
    rows.append([InlineKeyboardButton(
        text="⬅ Назад" if lang == "ru" else "⬅ Back",
        callback_data="settings:back",
    )])
    return InlineKeyboardMarkup(inline_keyboard=rows)


def time_picker_minutes_kb(lang: str, hour: int) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text=f"{hour:02d}:00", callback_data=f"settings:set:{hour:02d}:00"),
            InlineKeyboardButton(text=f"{hour:02d}:30", callback_data=f"settings:set:{hour:02d}:30"),
        ],
        [InlineKeyboardButton(
            text="⬅ Назад к часам" if lang == "ru" else "⬅ Back to hours",
            callback_data="settings:pick_time",
        )],
    ])


def shop_kb(lang: str, products: list[dict]) -> InlineKeyboardMarkup:
    rows = []
    for p in products:
        rows.append([
            InlineKeyboardButton(
                text=f"{p['name']} — {p['price_rub']}₽",
                callback_data=f"buy:{p['id']}",
            )
        ])
    return InlineKeyboardMarkup(inline_keyboard=rows)


def confirm_buy_kb(lang: str, product_id: int, has_promo: bool = False) -> InlineKeyboardMarkup:
    promo_text = (
        ("🔁 Сменить промокод" if has_promo else "🎁 Применить промокод")
        if lang == "ru"
        else ("🔁 Change promo" if has_promo else "🎁 Apply promo")
    )
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=t(lang, "btn_buy"), callback_data=f"buy_ok:{product_id}")],
        [InlineKeyboardButton(text=promo_text, callback_data=f"buy_promo:{product_id}")],
        [InlineKeyboardButton(text=t(lang, "btn_cancel"), callback_data="cancel")],
    ])


def trial_blocked_kb(lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(
            text="🌐 Открыть scarlex.ru" if lang == "ru" else "🌐 Open scarlex.ru",
            url="https://scarlex.ru/dashboard/market.html")],
        [InlineKeyboardButton(text=t(lang, "btn_cancel"), callback_data="cancel")],
    ])


def topup_method_kb(lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=t(lang, "btn_topup_card"), callback_data="topup_method:card")],
        [InlineKeyboardButton(text=t(lang, "btn_topup_crypto"), callback_data="topup_method:crypto")],
    ])


def topup_currency_kb(lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[[
        InlineKeyboardButton(text=t(lang, "btn_cur_rub"), callback_data="topup_cur:RUB"),
        InlineKeyboardButton(text=t(lang, "btn_cur_usd"), callback_data="topup_cur:USD"),
    ]])


def topup_amounts_kb(lang: str, currency: str = "RUB") -> InlineKeyboardMarkup:
    if currency == "USD":
        amounts = [1, 5, 10, 25, 50, 100]; sym = "$"
    elif currency == "CARD":
        amounts = [100, 300, 500, 1000, 3000, 10000]; sym = "₽"
    else:
        amounts = [50, 100, 300, 500, 1000, 5000]; sym = "₽"
    rows = []
    for i in range(0, len(amounts), 3):
        row = [
            InlineKeyboardButton(text=f"{a} {sym}", callback_data=f"topup:{currency}:{a}")
            for a in amounts[i:i + 3]
        ]
        rows.append(row)
    rows.append([InlineKeyboardButton(
        text="✏ Своя сумма" if lang == "ru" else "✏ Custom",
        callback_data=f"topup:{currency}:custom"
    )])
    return InlineKeyboardMarkup(inline_keyboard=rows)


def api_menu_kb(lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=t(lang, "btn_api_curl"), callback_data="apidoc:curl"),
         InlineKeyboardButton(text=t(lang, "btn_api_claude"), callback_data="apidoc:claude")],
        [InlineKeyboardButton(text=t(lang, "btn_api_opencode"), callback_data="apidoc:opencode"),
         InlineKeyboardButton(text=t(lang, "btn_api_ohmy"), callback_data="apidoc:ohmy")],
        [InlineKeyboardButton(text=t(lang, "btn_api_models"), callback_data="apidoc:models")],
        [InlineKeyboardButton(
            text="📚 Полная документация" if lang == "ru" else "📚 Full docs",
            url="https://scarlex.ru/api-guide.html")],
    ])


def api_back_kb(lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[[
        InlineKeyboardButton(text="⬅ К API" if lang == "ru" else "⬅ To API", callback_data="apidoc:back"),
    ]])


KEYS_PER_PAGE = 5


def keys_list_kb(lang: str, keys: list[dict], page: int) -> InlineKeyboardMarkup:
    total = len(keys)
    pages = max(1, (total + KEYS_PER_PAGE - 1) // KEYS_PER_PAGE)
    page = max(0, min(page, pages - 1))
    start = page * KEYS_PER_PAGE

    rows = []
    for i, k in enumerate(keys[start:start + KEYS_PER_PAGE], start=start + 1):
        kv = k["key_value"]; masked = "..." + kv[-8:]
        used = int(k.get("used_tokens", 0)); limit = int(k.get("tokens_limit", 0))
        pct = int(used / limit * 100) if limit > 0 else 0
        rows.append([InlineKeyboardButton(text=f"🔑#{i} {masked} ({pct}%)", callback_data=f"key:{k['id']}")])

    if pages > 1:
        nav = []
        if page > 0: nav.append(InlineKeyboardButton(text="⬅", callback_data=f"keys_page:{page - 1}"))
        nav.append(InlineKeyboardButton(text=f"{page + 1}/{pages}", callback_data="noop"))
        if page < pages - 1: nav.append(InlineKeyboardButton(text="➡", callback_data=f"keys_page:{page + 1}"))
        rows.append(nav)

    return InlineKeyboardMarkup(inline_keyboard=rows)


def key_back_kb(lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[[
        InlineKeyboardButton(text=t(lang, "btn_key_back"), callback_data="keys_page:0"),
    ]])


def lang_kb() -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[[
        InlineKeyboardButton(text="🇷🇺 Русский", callback_data="lang:ru"),
        InlineKeyboardButton(text="🇬🇧 English", callback_data="lang:en"),
    ]])


def pay_kb(lang: str, url: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[[
        InlineKeyboardButton(text=t(lang, "btn_pay"), url=url),
    ]])


def history_kb(lang: str, page: int, pages: int) -> InlineKeyboardMarkup:
    nav = []
    if page > 1:
        nav.append(InlineKeyboardButton(text="⬅", callback_data=f"hist_page:{page - 1}"))
    nav.append(InlineKeyboardButton(text=f"{page}/{pages}", callback_data="noop"))
    if page < pages:
        nav.append(InlineKeyboardButton(text="➡", callback_data=f"hist_page:{page + 1}"))
    return InlineKeyboardMarkup(inline_keyboard=[nav]) if nav else InlineKeyboardMarkup(inline_keyboard=[])


def referrals_kb(lang: str, link: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[[
        InlineKeyboardButton(
            text="🔗 Открыть на сайте" if lang == "ru" else "🔗 Open on site",
            url=link),
    ]])


def dashboard_kb(lang: str, login_url: str | None = None) -> InlineKeyboardMarkup:
    rows = []
    if login_url:
        rows.append([InlineKeyboardButton(
            text="🔐 Войти на сайт (одноразовая ссылка)" if lang == "ru" else "🔐 Auto-login to site (one-time)",
            url=login_url)])
    rows += [
        [InlineKeyboardButton(
            text="🏠 Открыть дашборд" if lang == "ru" else "🏠 Open dashboard",
            url="https://scarlex.ru/dashboard.html")],
        [InlineKeyboardButton(
            text="👤 Профиль" if lang == "ru" else "👤 Profile",
            url="https://scarlex.ru/dashboard/profile.html")],
        [InlineKeyboardButton(
            text="📊 Аналитика" if lang == "ru" else "📊 Analytics",
            url="https://scarlex.ru/dashboard/analytics.html")],
    ]
    return InlineKeyboardMarkup(inline_keyboard=rows)


def promo_cancel_kb(lang: str) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[[
        InlineKeyboardButton(text=t(lang, "btn_cancel"), callback_data="cancel"),
    ]])
