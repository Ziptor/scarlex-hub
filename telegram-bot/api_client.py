"""HTTP client for scarlex.ru bot API."""
from __future__ import annotations

import asyncio
import json
import logging
from typing import Any

import aiohttp

from config import config

log = logging.getLogger(__name__)


class APIError(Exception):
    pass


class API:
    def __init__(self) -> None:
        self._session: aiohttp.ClientSession | None = None

    async def session(self) -> aiohttp.ClientSession:
        if self._session is None or self._session.closed:
            self._session = aiohttp.ClientSession(
                headers={
                    "X-Bot-Secret": config.bot_secret,
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                },
                timeout=aiohttp.ClientTimeout(total=20),
            )
        return self._session

    async def close(self) -> None:
        if self._session and not self._session.closed:
            await self._session.close()

    async def _post(self, path: str, payload: dict[str, Any]) -> dict[str, Any]:
        url = f"{config.api_base}/bot/{path}"
        s = await self.session()
        try:
            async with s.post(url, json=payload) as r:
                text = await r.text()
        except aiohttp.ClientError as e:
            log.exception("API request failed: %s", e)
            raise APIError(f"Сеть: {e}")
        except asyncio.TimeoutError:
            raise APIError("Сервер не отвечает (timeout)")
        try:
            data = json.loads(text)
        except ValueError:
            log.error("Non-JSON response from %s (status=%s): %s", url, r.status, text[:400])
            raise APIError(f"Сервер вернул не JSON (HTTP {r.status})")
        if not data.get("ok"):
            raise APIError(data.get("error", "Unknown error"))
        return data

    # ── public methods ──
    async def auth_user(self, tg_id: int, username: str | None, first_name: str | None) -> dict:
        return await self._post("auth.php", {
            "telegram_id": tg_id,
            "username": username or "",
            "first_name": first_name or "",
        })

    async def products(self, tg_id: int | None = None) -> dict:
        return await self._post("products.php", {"telegram_id": tg_id} if tg_id else {})

    async def balance(self, tg_id: int) -> dict:
        return await self._post("balance.php", {"telegram_id": tg_id})

    async def my_key(self, tg_id: int) -> dict:
        return await self._post("my_key.php", {"telegram_id": tg_id})

    async def buy(self, tg_id: int, product_id: int, promo_code: str | None = None) -> dict:
        payload: dict[str, Any] = {"telegram_id": tg_id, "product_id": product_id}
        if promo_code:
            payload["promo_code"] = promo_code
        return await self._post("buy.php", payload)

    async def link_request(self, tg_id: int, tg_username: str | None) -> dict:
        return await self._post("link_request.php", {
            "telegram_id": tg_id,
            "telegram_username": tg_username or "",
        })

    async def link_consume(self, tg_id: int, token: str, tg_username: str | None = None,
                           tg_first_name: str | None = None) -> dict:
        return await self._post("link_request.php", {
            "telegram_id": tg_id,
            "telegram_username": tg_username or "",
            "telegram_first_name": tg_first_name or "",
            "token": token,
        })

    async def login_link(self, tg_id: int) -> dict:
        return await self._post("login_link.php", {"telegram_id": tg_id})

    async def tg_login_confirm(self, tg_id: int, token: str) -> dict:
        return await self._post("tg_login_confirm.php", {"telegram_id": tg_id, "token": token})

    async def topup_invoice(self, tg_id: int, amount: float, currency: str = "RUB") -> dict:
        return await self._post("topup_invoice.php", {
            "telegram_id": tg_id,
            "amount": amount,
            "currency": currency,
        })

    async def topup_card(self, tg_id: int, amount: float) -> dict:
        return await self._post("topup_card.php", {
            "telegram_id": tg_id,
            "amount": amount,
        })

    async def get_lang(self, tg_id: int) -> str:
        try:
            r = await self._post("lang.php", {"telegram_id": tg_id})
            return r.get("lang", "ru")
        except APIError:
            return "ru"

    async def set_lang(self, tg_id: int, lang: str) -> None:
        await self._post("lang.php", {"telegram_id": tg_id, "lang": lang})

    async def get_settings(self, tg_id: int) -> dict:
        return await self._post("settings.php", {"telegram_id": tg_id})

    async def set_settings(self, tg_id: int, *, daily_report_enabled: int | None = None,
                           daily_report_time: str | None = None) -> dict:
        payload: dict[str, Any] = {"telegram_id": tg_id}
        if daily_report_enabled is not None:
            payload["daily_report_enabled"] = daily_report_enabled
        if daily_report_time is not None:
            payload["daily_report_time"] = daily_report_time
        return await self._post("settings.php", payload)

    async def promo(self, tg_id: int, code: str) -> dict:
        return await self._post("promo.php", {"telegram_id": tg_id, "code": code})

    async def referrals(self, tg_id: int) -> dict:
        return await self._post("referrals.php", {"telegram_id": tg_id})

    async def history(self, tg_id: int, page: int = 1, filter_: str = "all") -> dict:
        return await self._post("history.php", {
            "telegram_id": tg_id,
            "page": page,
            "filter": filter_,
        })

    async def stats(self, tg_id: int) -> dict:
        return await self._post("stats.php", {"telegram_id": tg_id})


api = API()
