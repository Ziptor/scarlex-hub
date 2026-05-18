"""Scarlex Telegram bot entry point."""
from __future__ import annotations

import asyncio
import logging
import sys

from aiogram import Bot, Dispatcher
from aiogram.client.default import DefaultBotProperties
from aiogram.client.session.aiohttp import AiohttpSession
from aiogram.enums import ParseMode
from aiogram.fsm.storage.memory import MemoryStorage

from api_client import api
from config import config
from handlers import router


async def main() -> None:
    logging.basicConfig(
        level=getattr(logging, config.log_level.upper(), logging.INFO),
        format="%(asctime)s | %(levelname)s | %(name)s | %(message)s",
        stream=sys.stdout,
    )
    log = logging.getLogger("scarlex-bot")
    log.info("Starting Scarlex bot...")

    session = AiohttpSession(proxy=config.proxy_url) if config.proxy_url else None
    if config.proxy_url:
        log.info("Using proxy: %s", config.proxy_url.split("@")[-1])

    bot = Bot(
        token=config.bot_token,
        session=session,
        default=DefaultBotProperties(parse_mode=ParseMode.HTML),
    )
    dp = Dispatcher(storage=MemoryStorage())
    dp.include_router(router)

    try:
        # Drop pending updates that arrived while bot was offline
        await bot.delete_webhook(drop_pending_updates=True)
        log.info("Bot info: %s", await bot.get_me())
        await dp.start_polling(bot)
    finally:
        await api.close()
        await bot.session.close()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except (KeyboardInterrupt, SystemExit):
        pass
