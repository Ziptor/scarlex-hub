"""All bot handlers in one module — small enough not to split."""
from __future__ import annotations

import logging

from aiogram import F, Router
from aiogram.filters import Command, CommandStart, CommandObject
from aiogram.fsm.context import FSMContext
from aiogram.fsm.state import State, StatesGroup
from aiogram.types import CallbackQuery, Message

from api_client import APIError, api
from config import config
from i18n import t
from keyboards import (
    api_back_kb, api_menu_kb, confirm_buy_kb, dashboard_kb, history_kb,
    key_back_kb, keys_list_kb, lang_kb, main_menu, pay_kb, promo_cancel_kb,
    referrals_kb, settings_kb, shop_kb, time_picker_hours_kb,
    time_picker_minutes_kb, topup_amounts_kb, topup_currency_kb, topup_method_kb,
    trial_blocked_kb,
)

log = logging.getLogger(__name__)
router = Router()


class TopupStates(StatesGroup):
    waiting_amount_rub = State()
    waiting_amount_usd = State()
    waiting_amount_card = State()


class PromoStates(StatesGroup):
    waiting_code = State()
    waiting_buy_promo = State()


# ── Helpers ──
async def get_lang(tg_id: int) -> str:
    return await api.get_lang(tg_id)


# ── /start ──
@router.message(CommandStart())
async def cmd_start(msg: Message, command: CommandObject | None = None) -> None:
    tg = msg.from_user
    try:
        r = await api.auth_user(tg.id, tg.username, tg.first_name)
    except APIError as e:
        await msg.answer(t("ru", "error_generic", error=str(e)))
        return

    lang = await get_lang(tg.id)
    name = tg.first_name or tg.username or str(tg.id)

    # Deep-link: /start link_<token> → instantly link this TG to the site account that issued the token.
    arg = (command.args if command else None) or ""
    if arg.startswith("link_"):
        token = arg[len("link_"):].strip()
        if token:
            try:
                lr = await api.link_consume(tg.id, token, tg.username, tg.first_name)
                if lr.get("ok"):
                    email = lr.get("email") or "—"
                    ok_text = (
                        f"✅ Аккаунт привязан к <b>{email}</b>.\nТеперь покупки и баланс синхронизированы с сайтом."
                        if lang == "ru"
                        else f"✅ Linked to <b>{email}</b>.\nYour balance and purchases now sync with the website."
                    )
                    await msg.answer(ok_text, reply_markup=main_menu(lang), parse_mode="HTML")
                    return
            except APIError as e:
                err_text = (f"❌ Не удалось привязать: {e}" if lang == "ru"
                            else f"❌ Linking failed: {e}")
                await msg.answer(err_text, reply_markup=main_menu(lang))
                return

    # Deep-link: /start tglogin_<token> → confirm site "Login via Telegram" widget.
    if arg.startswith("tglogin_"):
        token = arg[len("tglogin_"):].strip()
        if token:
            try:
                cr = await api.tg_login_confirm(tg.id, token)
                if cr.get("ok"):
                    ok_text = (
                        "✅ Вход подтверждён! Возвращайся на сайт — он залогинится автоматически."
                        if lang == "ru"
                        else "✅ Login confirmed! Return to the website — it will log you in automatically."
                    )
                    await msg.answer(ok_text, reply_markup=main_menu(lang))
                    return
            except APIError as e:
                err_text = (f"❌ Не удалось подтвердить вход: {e}" if lang == "ru"
                            else f"❌ Login confirmation failed: {e}")
                await msg.answer(err_text, reply_markup=main_menu(lang))
                return

    greet_key = "start_welcome" if r.get("created") else "start_existing"

    await msg.answer(
        t(lang, greet_key, name=name) + "\n\n" + t(lang, "help", support=config.support_username),
        reply_markup=main_menu(lang),
        parse_mode="HTML",
    )


# ── Help ──
@router.message(Command("help"))
@router.message(F.text.in_({"❓ Помощь", "❓ Help"}))
async def cmd_help(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    await msg.answer(t(lang, "help", support=config.support_username), reply_markup=main_menu(lang))


# ── Shop ──
def _fmt_product_card(lang: str, p: dict) -> str:
    badge = p.get("badge")
    badge_line = f"\n🏷 <i>{badge}</i>" if badge else ""
    if p.get("is_trial"):
        badge_line = ("\n🎁 <i>Бесплатный пробный</i>" if lang == "ru"
                      else "\n🎁 <i>Free trial</i>") + badge_line
    custom_mark = " 🎁" if p.get("is_custom_price") else ""
    per_1m = p.get("price_per_1m") or (
        round(p["price_rub"] / (p["tokens"] / 1_000_000.0), 2) if p["tokens"] else 0
    )
    return t(lang, "product_card",
             name=p["name"] + custom_mark,
             badge_line=badge_line,
             desc=p.get("description") or "—",
             tokens=p["tokens"],
             price_rub=p["price_rub"],
             price_usd=p.get("price_usd_estimate", 0),
             per_1m=per_1m)


@router.message(Command("shop"))
@router.message(F.text.in_({"🛒 Магазин", "🛒 Shop"}))
async def cmd_shop(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        r = await api.products(msg.from_user.id)
        products = r.get("products", [])
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return

    if not products:
        await msg.answer(t(lang, "shop_empty")); return

    text = t(lang, "shop_title") + "\n\n"
    for p in products:
        text += _fmt_product_card(lang, p) + "\n\n"
    if any(p.get("is_custom_price") for p in products):
        text += ("🎁 — у вас персональная цена.\n" if lang == "ru" else "🎁 — your custom price.\n")
    await msg.answer(text, reply_markup=shop_kb(lang, products), parse_mode="HTML")


@router.callback_query(F.data.startswith("buy:"))
async def cb_show_product(cb: CallbackQuery, state: FSMContext) -> None:
    lang = await get_lang(cb.from_user.id)
    pid = int(cb.data.split(":")[1])

    try:
        r = await api.products(cb.from_user.id)
    except APIError as e:
        await cb.answer(str(e), show_alert=True); return

    product = next((p for p in r.get("products", []) if p["id"] == pid), None)
    if not product:
        await cb.answer("Not found", show_alert=True); return

    # Trial — блокируем в боте, отправляем на сайт
    if product.get("is_trial"):
        await cb.message.answer(t(lang, "trial_blocked"),
                                reply_markup=trial_blocked_kb(lang),
                                parse_mode="HTML",
                                disable_web_page_preview=True)
        await cb.answer(); return

    # Очистить предыдущий buy-промокод, если был
    await state.update_data(buy_promo=None)

    text = _fmt_product_card(lang, product) + "\n\n" + t(
        lang, "buy_confirm", name=product["name"], price=product["price_rub"]
    )
    await cb.message.answer(text, reply_markup=confirm_buy_kb(lang, pid, has_promo=False), parse_mode="HTML")
    await cb.answer()


@router.callback_query(F.data.startswith("buy_promo:"))
async def cb_buy_apply_promo(cb: CallbackQuery, state: FSMContext) -> None:
    lang = await get_lang(cb.from_user.id)
    pid = int(cb.data.split(":")[1])
    try:
        r = await api.products(cb.from_user.id)
    except APIError as e:
        await cb.answer(str(e), show_alert=True); return
    product = next((p for p in r.get("products", []) if p["id"] == pid), None)
    if not product:
        await cb.answer("Not found", show_alert=True); return

    await state.set_state(PromoStates.waiting_buy_promo)
    await state.update_data(buy_pid=pid)
    await cb.message.answer(
        t(lang, "buy_promo_intro", name=product["name"]),
        reply_markup=promo_cancel_kb(lang),
        parse_mode="HTML",
    )
    await cb.answer()


@router.message(PromoStates.waiting_buy_promo)
async def buy_promo_code_input(msg: Message, state: FSMContext) -> None:
    lang = await get_lang(msg.from_user.id)
    code = (msg.text or "").strip().upper()
    if not code:
        return
    data = await state.get_data()
    pid = int(data.get("buy_pid", 0))
    if not pid:
        await state.clear(); return

    # Валидируем "сухим прогоном" — пробуем купить с suspend? Нет, просто запоминаем и пересчитаем после buy_ok.
    # Однако нужно показать пользователю скидку. Сделаем "пробный" расчёт: повторно дёрнем products и promocodes.
    try:
        r = await api.products(msg.from_user.id)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); await state.clear(); return
    product = next((p for p in r.get("products", []) if p["id"] == pid), None)
    if not product:
        await state.clear(); return

    # Сохраняем код и оставляем подтверждение пользователю — фактический расчёт сделает buy.php
    await state.update_data(buy_promo=code)
    await state.set_state(None)
    text = _fmt_product_card(lang, product) + "\n\n" + (
        f"🎁 Промокод: <code>{code}</code> (скидка применится при покупке)\n"
        if lang == "ru" else
        f"🎁 Promo: <code>{code}</code> (discount applies at checkout)\n"
    ) + "\n" + t(lang, "buy_confirm", name=product["name"], price=product["price_rub"])
    await msg.answer(text, reply_markup=confirm_buy_kb(lang, pid, has_promo=True), parse_mode="HTML")


@router.callback_query(F.data.startswith("buy_ok:"))
async def cb_buy_confirm(cb: CallbackQuery, state: FSMContext) -> None:
    lang = await get_lang(cb.from_user.id)
    pid = int(cb.data.split(":")[1])

    data = await state.get_data()
    promo = data.get("buy_promo")

    await cb.message.edit_text(t(lang, "loading"))
    try:
        r = await api.buy(cb.from_user.id, pid, promo_code=promo)
    except APIError as e:
        msg_text = str(e)
        if "Недостаточно" in msg_text or "Not enough" in msg_text or "не хват" in msg_text.lower():
            await cb.message.edit_text(t(lang, "buy_no_funds"))
        else:
            await cb.message.edit_text(t(lang, "error_generic", error=msg_text))
        await cb.answer(); return

    discount = float(r.get("promo_discount") or 0)
    if discount > 0:
        promo_line = (f"\n🎁 Промокод <code>{r.get('promo_code')}</code>: −{discount}₽"
                      if lang == "ru" else
                      f"\n🎁 Promo <code>{r.get('promo_code')}</code>: −{discount}₽")
    else:
        promo_line = ""

    await state.clear()
    await cb.message.edit_text(
        t(lang, "buy_success",
          key=r["key"],
          product=r["product"],
          tokens=r["tokens"],
          price=r["price_rub"],
          promo_line=promo_line,
          balance=round(r["new_balance"], 2)),
        parse_mode="HTML",
    )
    await cb.answer("✅")


@router.callback_query(F.data == "cancel")
async def cb_cancel(cb: CallbackQuery, state: FSMContext) -> None:
    await state.clear()
    await cb.message.delete()
    await cb.answer()


# ── Balance ──
@router.message(Command("balance"))
@router.message(F.text.in_({"💵 Баланс", "💵 Balance"}))
async def cmd_balance(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        r = await api.balance(msg.from_user.id)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return

    await msg.answer(
        t(lang, "balance_title",
          balance_rub=round(float(r["balance_rub"]), 2),
          balance_usd=round(float(r["balance_usd"]), 2),
          public_id=r["public_id"],
          tokens_total=int(r.get("tokens_total", 0)),
          tokens_used=int(r.get("tokens_used", 0)),
          tokens_left=int(r.get("tokens_left", 0)),
          requests=int(r.get("requests", 0))),
        parse_mode="HTML",
    )


# ── My key ──
@router.message(Command("key"))
@router.message(F.text.in_({"🔑 Мой ключ", "🔑 My key"}))
async def cmd_key(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        r = await api.my_key(msg.from_user.id)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return

    keys = r.get("keys", [])
    if not keys:
        await msg.answer(t(lang, "key_empty")); return

    await msg.answer(t(lang, "key_title"), reply_markup=keys_list_kb(lang, keys, 0), parse_mode="HTML")


@router.callback_query(F.data.startswith("keys_page:"))
async def cb_keys_page(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    page = int(cb.data.split(":")[1])
    try:
        r = await api.my_key(cb.from_user.id)
    except APIError as e:
        await cb.answer(str(e), show_alert=True); return

    keys = r.get("keys", [])
    if not keys:
        await cb.message.edit_text(t(lang, "key_empty")); await cb.answer(); return

    await cb.message.edit_text(
        t(lang, "key_title"),
        reply_markup=keys_list_kb(lang, keys, page),
        parse_mode="HTML",
    )
    await cb.answer()


@router.callback_query(F.data.startswith("key:"))
async def cb_key_detail(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    key_id = int(cb.data.split(":")[1])
    try:
        await cb.answer()
        r = await api.my_key(cb.from_user.id)
    except APIError as e:
        await cb.message.answer(t(lang, "error_generic", error=str(e)))
        return

    keys = r.get("keys", [])
    idx = next((i for i, k in enumerate(keys) if int(k["id"]) == key_id), -1)
    if idx < 0:
        await cb.message.answer(t(lang, "error_generic", error="Key not found"))
        return

    k = keys[idx]
    used = int(k.get("used_tokens") or 0)
    limit = int(k.get("tokens_limit") or 0)
    pct = int(used / limit * 100) if limit > 0 else 0
    created_raw = k.get("created_at") or ""
    created = str(created_raw)[:10] if created_raw else "—"

    try:
        await cb.message.edit_text(
            t(lang, "key_card",
              n=idx + 1,
              key=k["key_value"],
              label=k.get("label") or "—",
              used=used,
              limit=limit,
              percent=pct,
              created=created),
            reply_markup=key_back_kb(lang),
            parse_mode="HTML",
        )
    except Exception as e:
        log.exception("edit_text failed: %s", e)
        await cb.message.answer(t(lang, "error_generic", error=str(e)))


@router.callback_query(F.data == "noop")
async def cb_noop(cb: CallbackQuery) -> None:
    await cb.answer()


# ── Top up ──
@router.message(Command("topup"))
@router.message(F.text.in_({"💳 Пополнить", "💳 Top up"}))
async def cmd_topup(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    await msg.answer(t(lang, "topup_method"), reply_markup=topup_method_kb(lang), parse_mode="HTML")


@router.callback_query(F.data == "topup_method:card")
async def cb_topup_card(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    await cb.message.answer(
        t(lang, "topup_card_amounts"),
        reply_markup=topup_amounts_kb(lang, "CARD"),
        parse_mode="HTML",
    )
    await cb.answer()


@router.callback_query(F.data == "topup_method:crypto")
async def cb_topup_crypto(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    await cb.message.answer(
        t(lang, "topup_crypto_currency"),
        reply_markup=topup_currency_kb(lang),
        parse_mode="HTML",
    )
    await cb.answer()


@router.callback_query(F.data.startswith("topup_cur:"))
async def cb_topup_currency(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    cur = cb.data.split(":")[1]
    text_key = "topup_crypto_amounts_rub" if cur == "RUB" else "topup_crypto_amounts_usd"
    await cb.message.answer(t(lang, text_key), reply_markup=topup_amounts_kb(lang, cur), parse_mode="HTML")
    await cb.answer()


@router.callback_query(F.data.startswith("topup:"))
async def cb_topup_amount(cb: CallbackQuery, state: FSMContext) -> None:
    lang = await get_lang(cb.from_user.id)
    parts = cb.data.split(":")
    if len(parts) < 3:
        await cb.answer(); return
    cur = parts[1]
    val = parts[2]

    if val == "custom":
        if cur == "USD":
            await state.set_state(TopupStates.waiting_amount_usd)
            await cb.message.answer(t(lang, "topup_custom_prompt_usd"))
        elif cur == "CARD":
            await state.set_state(TopupStates.waiting_amount_card)
            await cb.message.answer(t(lang, "topup_custom_prompt_card"))
        else:
            await state.set_state(TopupStates.waiting_amount_rub)
            await cb.message.answer(t(lang, "topup_custom_prompt_rub"))
        await cb.answer(); return

    amount = float(val)
    if cur == "CARD":
        await _create_card_invoice(cb.message, cb.from_user.id, amount, lang)
    else:
        await _create_invoice(cb.message, cb.from_user.id, amount, cur, lang)
    await cb.answer()


@router.message(TopupStates.waiting_amount_rub)
async def topup_amount_rub_input(msg: Message, state: FSMContext) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        amount = float(msg.text.replace(",", ".").strip())
    except (ValueError, AttributeError):
        await msg.answer(t(lang, "topup_invalid_rub")); return
    if amount < 100 or amount > 500000:
        await msg.answer(t(lang, "topup_invalid_rub")); return
    await state.clear()
    await _create_invoice(msg, msg.from_user.id, amount, "RUB", lang)


@router.message(TopupStates.waiting_amount_usd)
async def topup_amount_usd_input(msg: Message, state: FSMContext) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        amount = float(msg.text.replace(",", ".").strip())
    except (ValueError, AttributeError):
        await msg.answer(t(lang, "topup_invalid_usd")); return
    if amount < 1 or amount > 5000:
        await msg.answer(t(lang, "topup_invalid_usd")); return
    await state.clear()
    await _create_invoice(msg, msg.from_user.id, amount, "USD", lang)


@router.message(TopupStates.waiting_amount_card)
async def topup_amount_card_input(msg: Message, state: FSMContext) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        amount = float(msg.text.replace(",", ".").strip())
    except (ValueError, AttributeError):
        await msg.answer(t(lang, "topup_invalid_card")); return
    if amount < 100 or amount > 60000:
        await msg.answer(t(lang, "topup_invalid_card")); return
    await state.clear()
    await _create_card_invoice(msg, msg.from_user.id, amount, lang)


async def _create_card_invoice(msg: Message, tg_id: int, amount: float, lang: str) -> None:
    try:
        r = await api.topup_card(tg_id, amount)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return
    pay_url = r.get("pay_url")
    if not pay_url:
        await msg.answer(t(lang, "error_generic", error="No pay URL")); return
    await msg.answer(
        t(lang, "topup_card_invoice", invoice_id=r["invoice_id"], amount_rub=r["amount_rub"]),
        reply_markup=pay_kb(lang, pay_url),
        parse_mode="HTML",
    )


async def _create_invoice(msg: Message, tg_id: int, amount: float, currency: str, lang: str) -> None:
    try:
        r = await api.topup_invoice(tg_id, amount, currency)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return

    pay_url = r.get("mini_app_url") or r.get("pay_url")
    if not pay_url:
        await msg.answer(t(lang, "error_generic", error="No pay URL")); return

    await msg.answer(
        t(lang, "topup_invoice",
          invoice_id=r["invoice_id"],
          amount_rub=r["amount_rub"],
          amount_usd=r["amount_usd"]),
        reply_markup=pay_kb(lang, pay_url),
        parse_mode="HTML",
    )


# ── Promo ──
@router.message(Command("promo"))
@router.message(F.text.in_({"🎁 Промокод", "🎁 Promo"}))
async def cmd_promo(msg: Message, state: FSMContext) -> None:
    lang = await get_lang(msg.from_user.id)
    await state.set_state(PromoStates.waiting_code)
    await msg.answer(t(lang, "promo_intro"), reply_markup=promo_cancel_kb(lang), parse_mode="HTML")


@router.message(PromoStates.waiting_code)
async def promo_code_input(msg: Message, state: FSMContext) -> None:
    lang = await get_lang(msg.from_user.id)
    code = (msg.text or "").strip()
    if not code:
        await msg.answer(t(lang, "promo_intro")); return
    await state.clear()
    try:
        r = await api.promo(msg.from_user.id, code)
    except APIError:
        await msg.answer(t(lang, "promo_fail"), reply_markup=main_menu(lang)); return
    await msg.answer(
        t(lang, "promo_success", amount=r.get("bonus", 0), balance=r.get("new_balance", 0)),
        reply_markup=main_menu(lang),
        parse_mode="HTML",
    )


# ── Referrals ──
@router.message(Command("ref"))
@router.message(F.text.in_({"👥 Рефералы", "👥 Referrals"}))
async def cmd_ref(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        r = await api.referrals(msg.from_user.id)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return
    link = r.get("link", f"https://scarlex.ru/authorization.html?ref={r.get('code', '')}")
    await msg.answer(
        t(lang, "ref_card",
          code=r.get("code", ""),
          link=link,
          total=r.get("total_referrals", 0),
          active=r.get("active_referrals", 0),
          earned=r.get("total_earned", 0)),
        reply_markup=referrals_kb(lang, link),
        parse_mode="HTML",
        disable_web_page_preview=True,
    )


# ── History ──
@router.message(Command("history"))
@router.message(F.text.in_({"📜 История", "📜 History"}))
async def cmd_history(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    await _show_history(msg, msg.from_user.id, lang, 1)


@router.callback_query(F.data.startswith("hist_page:"))
async def cb_history_page(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    page = int(cb.data.split(":")[1])
    try:
        r = await api.history(cb.from_user.id, page)
    except APIError as e:
        await cb.answer(str(e), show_alert=True); return

    txs = r.get("transactions", [])
    pages = r.get("pages", 1)
    if not txs:
        await cb.message.edit_text(t(lang, "history_empty"))
        await cb.answer(); return

    text = t(lang, "history_header")
    for tx in txs:
        date = (tx.get("created_at") or "")[:16]
        if tx.get("tx_type") == "chat":
            model = tx.get("model", "?")
            tok = tx.get("total_tokens", 0)
            text += f"📡 {date} | {model} | {tok:,} tok\n"
        else:
            amt = tx.get("amount", "")
            desc = tx.get("description", "")
            text += f"💰 {date} | {amt} | {desc}\n"
    await cb.message.edit_text(text, reply_markup=history_kb(lang, page, pages), parse_mode="HTML")
    await cb.answer()


async def _show_history(msg: Message, tg_id: int, lang: str, page: int) -> None:
    try:
        r = await api.history(tg_id, page)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return

    txs = r.get("transactions", [])
    pages = r.get("pages", 1)
    if not txs:
        await msg.answer(t(lang, "history_empty")); return

    text = t(lang, "history_header")
    for tx in txs:
        date = (tx.get("created_at") or "")[:16]
        if tx.get("tx_type") == "chat":
            model = tx.get("model", "?")
            tok = tx.get("total_tokens", 0)
            text += f"📡 {date} | {model} | {tok:,} tok\n"
        else:
            amt = tx.get("amount", "")
            desc = tx.get("description", "")
            text += f"💰 {date} | {amt} | {desc}\n"
    await msg.answer(text, reply_markup=history_kb(lang, page, pages), parse_mode="HTML")


# ── Stats ──
@router.message(Command("stats"))
@router.message(F.text.in_({"📊 Статистика", "📊 Stats"}))
async def cmd_stats(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        r = await api.stats(msg.from_user.id)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return

    daily_avg = float(r.get("daily_avg", 0))
    burn_str = f"~{daily_avg:.0f}₽/день" if lang == "ru" else f"~{daily_avg:.0f}₽/day"
    dl = r.get("days_left")
    days_str = str(dl) if dl is not None else "∞"
    top = r.get("top_models_30d", [])
    top_str = "\n".join(f"  • {m['model']}: {m['count']} req" for m in top) if top else "—"

    await msg.answer(
        t(lang, "stats_full",
          tokens_total=int(r.get("tokens_total", 0)),
          tokens_used=int(r.get("tokens_used", 0)),
          tokens_left=int(r.get("tokens_left", 0)),
          requests=r.get("requests_all_time", 0),
          burn_rate=burn_str,
          days_left=days_str,
          top_models=top_str),
        parse_mode="HTML",
    )


# ── Dashboard ──
@router.message(Command("dashboard"))
@router.message(F.text.in_({"🌐 Дашборд", "🌐 Dashboard"}))
async def cmd_dashboard(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    login_url: str | None = None
    try:
        r = await api.login_link(msg.from_user.id)
        if r.get("ok"):
            login_url = r.get("url")
    except APIError:
        login_url = None
    await msg.answer(
        t(lang, "dashboard_intro"),
        reply_markup=dashboard_kb(lang, login_url),
        parse_mode="HTML",
    )


# ── API doc ──
@router.message(Command("api"))
@router.message(F.text.in_({"📖 API"}))
async def cmd_api(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    await msg.answer(
        t(lang, "api_doc"),
        reply_markup=api_menu_kb(lang),
        parse_mode="HTML",
        disable_web_page_preview=True,
    )


@router.callback_query(F.data.startswith("apidoc:"))
async def cb_api_section(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    section = cb.data.split(":")[1]

    if section == "back":
        await cb.message.edit_text(
            t(lang, "api_doc"),
            reply_markup=api_menu_kb(lang),
            parse_mode="HTML",
            disable_web_page_preview=True,
        )
        await cb.answer(); return

    key_map = {
        "curl": "api_curl",
        "claude": "api_claude",
        "opencode": "api_opencode",
        "ohmy": "api_ohmy",
        "models": "api_models",
    }
    key = key_map.get(section)
    if not key:
        await cb.answer(); return
    await cb.message.edit_text(
        t(lang, key, site=config.site_url),
        reply_markup=api_back_kb(lang),
        parse_mode="HTML",
        disable_web_page_preview=True,
    )
    await cb.answer()


# ── Language ──
@router.message(Command("lang"))
@router.message(F.text.in_({"🌐 Язык", "🌐 Language"}))
async def cmd_lang(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    await msg.answer(t(lang, "lang_title"), reply_markup=lang_kb())


@router.callback_query(F.data.startswith("lang:"))
async def cb_set_lang(cb: CallbackQuery) -> None:
    new_lang = cb.data.split(":")[1]
    try:
        await api.set_lang(cb.from_user.id, new_lang)
    except APIError as e:
        await cb.answer(str(e), show_alert=True); return

    await cb.message.edit_text(t(new_lang, "lang_set"))
    await cb.message.answer(
        t(new_lang, "help", support=config.support_username),
        reply_markup=main_menu(new_lang),
    )
    await cb.answer("✅")


# ── Link to website ──
@router.message(Command("link"))
@router.message(F.text.in_({"🔗 Привязать к сайту", "🔗 Link to website"}))
async def cmd_link(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        r = await api.link_request(msg.from_user.id, msg.from_user.username)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return

    await msg.answer(
        t(lang, "link_intro", code=r["code"], site=config.site_url),
        parse_mode="HTML",
        disable_web_page_preview=True,
    )


# ── Settings (notifications) ──
@router.message(Command("settings"))
@router.message(F.text.in_({"⚙ Настройки", "⚙ Settings"}))
async def cmd_settings(msg: Message) -> None:
    lang = await get_lang(msg.from_user.id)
    try:
        s = await api.get_settings(msg.from_user.id)
    except APIError as e:
        await msg.answer(t(lang, "error_generic", error=str(e))); return
    await msg.answer(
        t(lang, "settings_title"),
        reply_markup=settings_kb(lang, int(s.get("daily_report_enabled", 1)), s.get("daily_report_time", "10:00")),
        parse_mode="HTML",
    )


@router.callback_query(F.data == "settings:toggle_daily")
async def cb_settings_toggle_daily(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    try:
        s = await api.get_settings(cb.from_user.id)
        new_state = 0 if int(s.get("daily_report_enabled", 1)) else 1
        s = await api.set_settings(cb.from_user.id, daily_report_enabled=new_state)
    except APIError as e:
        await cb.answer(str(e), show_alert=True); return

    await cb.message.edit_text(
        t(lang, "settings_title"),
        reply_markup=settings_kb(lang, int(s.get("daily_report_enabled", 1)), s.get("daily_report_time", "10:00")),
        parse_mode="HTML",
    )
    await cb.answer(t(lang, "settings_saved_toggle_on" if new_state else "settings_saved_toggle_off").replace("<b>", "").replace("</b>", ""))


@router.callback_query(F.data == "settings:pick_time")
async def cb_settings_pick_time(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    await cb.message.edit_text(
        t(lang, "settings_pick_hour"),
        reply_markup=time_picker_hours_kb(lang),
        parse_mode="HTML",
    )
    await cb.answer()


@router.callback_query(F.data.startswith("settings:hour:"))
async def cb_settings_hour(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    try:
        hour = int(cb.data.split(":")[2])
    except (ValueError, IndexError):
        await cb.answer(); return
    if hour < 0 or hour > 23:
        await cb.answer(); return
    await cb.message.edit_text(
        t(lang, "settings_pick_minute", hour=hour),
        reply_markup=time_picker_minutes_kb(lang, hour),
        parse_mode="HTML",
    )
    await cb.answer()


@router.callback_query(F.data.startswith("settings:set:"))
async def cb_settings_set_time(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    parts = cb.data.split(":")
    if len(parts) < 4:
        await cb.answer(); return
    time_str = f"{parts[2]}:{parts[3]}"

    try:
        s = await api.set_settings(cb.from_user.id, daily_report_time=time_str)
    except APIError as e:
        await cb.answer(str(e), show_alert=True); return

    saved_time = s.get("daily_report_time", time_str)
    await cb.message.edit_text(
        t(lang, "settings_title"),
        reply_markup=settings_kb(lang, int(s.get("daily_report_enabled", 1)), saved_time),
        parse_mode="HTML",
    )
    await cb.answer(t(lang, "settings_saved_time", time=saved_time).replace("<b>", "").replace("</b>", ""))


@router.callback_query(F.data == "settings:back")
async def cb_settings_back(cb: CallbackQuery) -> None:
    lang = await get_lang(cb.from_user.id)
    try:
        s = await api.get_settings(cb.from_user.id)
    except APIError as e:
        await cb.answer(str(e), show_alert=True); return
    await cb.message.edit_text(
        t(lang, "settings_title"),
        reply_markup=settings_kb(lang, int(s.get("daily_report_enabled", 1)), s.get("daily_report_time", "10:00")),
        parse_mode="HTML",
    )
    await cb.answer()


# ── Fallback ──
@router.message()
async def fallback(msg: Message, state: FSMContext) -> None:
    cur = await state.get_state()
    if cur:
        return
    lang = await get_lang(msg.from_user.id)
    await msg.answer(t(lang, "help", support=config.support_username), reply_markup=main_menu(lang))
