"""
Kiro Farm Bot — ядро для взаимодействия с Kiro AI через AWS CodeWhisperer API.
================================================================================

Подход скопирован с omniroute (рабочий, стабильный):
─────────────────────────────────────────────────────────────────
1. Refresh token: POST https://prod.us-east-1.auth.desktop.kiro.dev/refreshToken
   - Простой JSON, без CSRF, без HTML-парсинга
   - Возвращает новый accessToken (и иногда новый refreshToken)

2. Chat API: POST https://codewhisperer.us-east-1.amazonaws.com/generateAssistantResponse
   - JSON payload (не CBOR!)
   - AWS EventStream binary response
   - Headers: X-Amz-Target, Authorization: Bearer <access>

3. НЕ ходим через app.kiro.dev (это web-портал, убивает сессии!)
─────────────────────────────────────────────────────────────────
"""

import json
import uuid
import struct
import logging
import time
import threading
import requests

logger = logging.getLogger("kiro.core")

# ── AWS EventStream parsing ────────────────────────────────────────

# CRC32 lookup table (IEEE polynomial)
_CRC32_TABLE = [0] * 256
for _i in range(256):
    _c = _i
    for _j in range(8):
        _c = (0xEDB88320 ^ (_c >> 1)) if (_c & 1) else (_c >> 1)
    _CRC32_TABLE[_i] = _c & 0xFFFFFFFF


def _crc32(data: bytes) -> int:
    crc = 0xFFFFFFFF
    for b in data:
        crc = _CRC32_TABLE[(crc ^ b) & 0xFF] ^ (crc >> 8)
    return (crc ^ 0xFFFFFFFF) & 0xFFFFFFFF


def _parse_event_frame(data: bytes):
    """Parse one AWS EventStream frame → (event_type, payload_dict)."""
    if len(data) < 16:
        return None, None

    total_len = struct.unpack(">I", data[0:4])[0]
    headers_len = struct.unpack(">I", data[4:8])[0]

    # CRC validation
    prelude_crc = struct.unpack(">I", data[8:12])[0]
    if _crc32(data[0:8]) != prelude_crc:
        return None, None

    msg_crc = struct.unpack(">I", data[-4:])[0]
    if _crc32(data[:-4]) != msg_crc:
        return None, None

    # Parse headers
    headers = {}
    offset = 12
    header_end = 12 + headers_len
    while offset < header_end and offset < len(data):
        name_len = data[offset]
        offset += 1
        name = data[offset:offset + name_len].decode("utf-8")
        offset += name_len
        header_type = data[offset]
        offset += 1
        if header_type == 7:  # String
            value_len = (data[offset] << 8) | data[offset + 1]
            offset += 2
            value = data[offset:offset + value_len].decode("utf-8")
            offset += value_len
            headers[name] = value
        else:
            break

    # Parse payload
    payload_start = 12 + headers_len
    payload_end = len(data) - 4
    payload = None
    if payload_end > payload_start:
        try:
            payload = json.loads(data[payload_start:payload_end])
        except Exception:
            pass

    event_type = headers.get(":event-type", "")
    return event_type, payload


class KiroFarmBot:
    """
    Один экземпляр бота = один аккаунт Kiro.
    Работает через прямой AWS CodeWhisperer API (как omniroute).
    """

    TOKEN_REFRESH_INTERVAL = 3000  # 50 мин — access живёт ~60 мин
    AUTH_ENDPOINT = "https://prod.us-east-1.auth.desktop.kiro.dev/refreshToken"
    API_ENDPOINT = "https://codewhisperer.us-east-1.amazonaws.com/generateAssistantResponse"

    def __init__(self, account_id, refresh_token, access_token, profile_arn,
                 proxy=None, email=None, login_method="Google"):
        self.account_id = account_id
        self.email = email or "unknown"
        self.login_method = login_method
        self.refresh_token = refresh_token
        self.access_token = access_token
        self.profile_arn = profile_arn
        self.proxy = proxy

        self.session = requests.Session()
        if proxy:
            self.session.proxies = {"http": proxy, "https": proxy}

        self._last_token_refresh = 0.0
        self._dead = False
        self._dead_reason = ""
        self._cooldown_until = 0.0  # timestamp until which this account is rate-limited

        # ── Кредиты ──────────────────────────────────────────────
        self.credits_used = 0
        self.credits_limit = 0
        self.credits_remaining = 0
        self.credits_plan = ""
        self.credits_reset = ""
        self.credits_overage_enabled = False
        self.credits_overage_charges = 0.0
        self._last_credits_check = 0.0

        # ── Статистика ───────────────────────────────────────────
        self.requests_total = 0
        self.tokens_prompt_total = 0
        self.tokens_completion_total = 0
        self._lock = threading.Lock()

    @property
    def is_dead(self):
        return self._dead

    @property
    def is_on_cooldown(self):
        return time.time() < self._cooldown_until

    @property
    def dead_reason(self):
        return self._dead_reason

    @property
    def has_credits(self):
        if self.credits_limit == 0:
            return True
        if self.credits_overage_enabled:
            return True
        return self.credits_remaining > 0

    def record_usage(self, prompt_tokens: int, completion_tokens: int):
        with self._lock:
            self.requests_total += 1
            self.tokens_prompt_total += prompt_tokens
            self.tokens_completion_total += completion_tokens

    # ------------------------------------------------------------------
    # Token refresh — через прямой auth API (как omniroute)
    # ------------------------------------------------------------------

    def refresh_access_token(self, force=False):
        """
        Обновляет access_token через Kiro auth service.
        POST https://prod.us-east-1.auth.desktop.kiro.dev/refreshToken
        Body: {"refreshToken": "aorAAAAA..."}
        """
        if self._dead:
            return False

        now = time.time()
        if not force and (now - self._last_token_refresh < self.TOKEN_REFRESH_INTERVAL):
            return True

        logger.info("[%s] Refreshing access_token via auth API …", self.account_id)

        try:
            r = self.session.post(
                self.AUTH_ENDPOINT,
                json={"refreshToken": self.refresh_token},
                headers={
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                },
                timeout=15,
            )

            if r.status_code == 200:
                data = r.json()
                new_access = data.get("accessToken")
                new_refresh = data.get("refreshToken")
                self.profile_arn = data.get("profileArn", self.profile_arn)

                if new_access:
                    self.access_token = new_access
                # Если сервер вернул новый refresh — сохраняем
                if new_refresh and new_refresh != self.refresh_token:
                    logger.info("[%s] Refresh token rotated!", self.account_id)
                    self.refresh_token = new_refresh

                self._last_token_refresh = time.time()
                logger.info("[%s] access_token refreshed OK", self.account_id)
                return True
            else:
                error_text = r.text[:200]
                logger.error("[%s] Auth refresh failed: HTTP %d — %s",
                             self.account_id, r.status_code, error_text)
                if r.status_code in (400, 401, 403):
                    self._dead = True
                    self._dead_reason = f"auth_failed_{r.status_code}"
                return False

        except Exception as e:
            logger.error("[%s] Auth refresh exception: %s", self.account_id, e)
            return False

    def update_tokens(self):
        return self.refresh_access_token()

    # ------------------------------------------------------------------
    # Credits — через web portal (единственное что оттуда нужно)
    # ------------------------------------------------------------------

    def fetch_credits(self):
        """Получает кредиты через web portal GetUserUsageAndLimits."""
        if self._dead:
            return False

        try:
            import cbor2

            # Нужно CSRF для web portal API
            self.session.cookies.set("RefreshToken", self.refresh_token, domain="app.kiro.dev")
            self.session.cookies.set("AccessToken", self.access_token, domain="app.kiro.dev")
            self.session.cookies.set("Idp", self.login_method, domain="app.kiro.dev")

            import re
            res = self.session.get("https://app.kiro.dev/", timeout=15)
            match = re.search(
                r'name=["\']csrf-token["\'][^>]*content=["\']([^"\']+)["\']',
                res.text, re.IGNORECASE
            )
            if not match:
                match = re.search(
                    r'content=["\']([^"\']+)["\'][^>]*name=["\']csrf-token["\']',
                    res.text, re.IGNORECASE
                )
            if not match:
                logger.warning("[%s] Could not get CSRF for credits", self.account_id)
                return False

            csrf = match.group(1)

            headers = {
                "accept": "application/cbor",
                "content-type": "application/cbor",
                "smithy-protocol": "rpc-v2-cbor",
                "x-csrf-token": csrf,
                "authorization": f"Bearer {self.access_token}",
                "origin": "https://app.kiro.dev",
            }

            r2 = self.session.post(
                "https://app.kiro.dev/service/KiroWebPortalService/operation/GetUserUsageAndLimits",
                data=cbor2.dumps({}),
                headers=headers, timeout=15
            )

            if r2.status_code != 200:
                return False

            data = cbor2.loads(r2.content)
            sub = data.get("subscriptionInfo", {})
            self.credits_plan = sub.get("subscriptionTitle", "")
            self.credits_reset = str(data.get("nextDateReset", ""))

            overage = data.get("overageConfiguration", {})
            self.credits_overage_enabled = overage.get("overageEnabled", False)

            breakdown = data.get("usageBreakdownList", [])
            if breakdown:
                b = breakdown[0]
                self.credits_used = b.get("currentUsage", 0)
                self.credits_limit = b.get("usageLimit", 0)
                self.credits_remaining = max(0, self.credits_limit - self.credits_used)
                self.credits_overage_charges = b.get("overageCharges", 0.0)

            self._last_credits_check = time.time()
            logger.info("[%s] Credits: %d/%d, plan=%s",
                        self.account_id, self.credits_used, self.credits_limit, self.credits_plan)
            return True

        except Exception as e:
            logger.error("[%s] fetch_credits error: %s", self.account_id, e)
            return False

    # ------------------------------------------------------------------
    # Health check / Revive
    # ------------------------------------------------------------------

    def health_check(self):
        """Проверка здоровья: refresh token + credits."""
        logger.info("[%s] Running health check …", self.account_id)

        if self._dead:
            return self.revive()

        if not self.refresh_access_token(force=True):
            return False

        self.fetch_credits()

        logger.info("[%s] Health check OK — %s, %d/%d credits",
                    self.account_id, self.email, self.credits_used, self.credits_limit)
        return True

    def revive(self):
        """Попытка оживить dead-аккаунт."""
        logger.info("[%s] Attempting revive (was: %s) …", self.account_id, self._dead_reason)
        self._dead = False
        self._dead_reason = ""

        if self.refresh_access_token(force=True):
            self.fetch_credits()
            logger.info("[%s] REVIVED!", self.account_id)
            return True

        if not self._dead:
            self._dead = True
            self._dead_reason = "revive_failed"
        return False

    # ------------------------------------------------------------------
    # Chat — через прямой AWS CodeWhisperer API
    # ------------------------------------------------------------------

    @staticmethod
    def _openai_tools_to_kiro(tools):
        """Convert OpenAI tools format to Kiro toolSpecification format."""
        if not tools:
            return []
        kiro_tools = []
        for tool in tools:
            if tool.get("type") == "function":
                func = tool["function"]
                kiro_tools.append({
                    "toolSpecification": {
                        "name": func["name"],
                        "description": func.get("description", ""),
                        "inputSchema": {
                            "json": func.get("parameters", {})
                        }
                    }
                })
        return kiro_tools

    @staticmethod
    def _build_tool_results(tool_messages):
        """Convert consecutive tool-role messages into Kiro toolResults."""
        results = []
        for msg in tool_messages:
            results.append({
                "toolUseId": msg.get("tool_call_id", ""),
                "status": "success",
                "content": [{"text": msg.get("content", "")}]
            })
        return results

    def _build_payload(self, messages, model, tools=None):
        """
        Формирует payload для CodeWhisperer generateAssistantResponse.
        Принимает массив сообщений в формате OpenAI.
        Поддерживает tool calling.
        """
        history = []
        current_message = None
        kiro_tools = self._openai_tools_to_kiro(tools)

        i = 0
        while i < len(messages):
            msg = messages[i]
            role = msg.get("role", "user")
            content = msg.get("content", "") or ""

            if role in ("system", "user"):
                entry = {
                    "userInputMessage": {
                        "content": content,
                        "modelId": model,
                    }
                }
                # Attach tools to user messages
                if kiro_tools:
                    entry["userInputMessage"]["userInputMessageContext"] = {
                        "tools": kiro_tools
                    }
                history.append(entry)
                i += 1

            elif role == "assistant":
                entry = {
                    "assistantResponseMessage": {
                        "content": content,
                    }
                }
                # If assistant has tool_calls, add them
                tool_calls = msg.get("tool_calls", [])
                if tool_calls:
                    entry["assistantResponseMessage"]["toolUses"] = []
                    for tc in tool_calls:
                        func = tc.get("function", {})
                        args = func.get("arguments", "{}")
                        if isinstance(args, str):
                            try:
                                args = json.loads(args)
                            except Exception:
                                args = {}
                        entry["assistantResponseMessage"]["toolUses"].append({
                            "toolUseId": tc.get("id", str(uuid.uuid4())),
                            "name": func.get("name", ""),
                            "input": args,
                        })
                history.append(entry)
                i += 1

            elif role == "tool":
                # Collect consecutive tool messages
                tool_msgs = []
                while i < len(messages) and messages[i].get("role") == "tool":
                    tool_msgs.append(messages[i])
                    i += 1
                tool_results = self._build_tool_results(tool_msgs)
                # Add as user message with tool results
                entry = {
                    "userInputMessage": {
                        "content": "Tool results:",
                        "modelId": model,
                        "userInputMessageContext": {
                            "toolResults": tool_results,
                        }
                    }
                }
                if kiro_tools:
                    entry["userInputMessage"]["userInputMessageContext"]["tools"] = kiro_tools
                history.append(entry)
            else:
                i += 1

        # Последнее сообщение user — это currentMessage
        if history and "userInputMessage" in history[-1]:
            current_message = history.pop()
        else:
            current_message = {
                "userInputMessage": {
                    "content": "continue",
                    "modelId": model,
                }
            }

        # Добавляем timestamp в контент (как omniroute)
        from datetime import datetime, timezone
        timestamp = datetime.now(timezone.utc).isoformat()
        original = current_message["userInputMessage"].get("content", "")
        current_message["userInputMessage"]["content"] = f"[Context: Current time is {timestamp}]\n\n{original}"
        current_message["userInputMessage"]["origin"] = "AI_EDITOR"

        payload = {
            "conversationState": {
                "chatTriggerType": "MANUAL",
                "conversationId": str(uuid.uuid4()),
                "currentMessage": current_message,
                "history": history,
            },
            "profileArn": self.profile_arn,
            "inferenceConfig": {
                "maxTokens": 32000,
            },
        }

        return payload

    def send_message_stream(self, prompt, model="claude-sonnet-4", messages=None, tools=None):
        """
        Генератор: отправляет запрос в CodeWhisperer и yield'ит события.

        Yields:
          - str: текстовый чанк (assistantResponseEvent / codeEvent)
          - dict: {"type": "tool_calls", "tool_calls": [...]} (toolUseEvent)
          - str: "[ERROR] ..." (ошибка)

        Поддерживает два режима:
        - prompt: строка (старый формат, обёрнется в одно user-сообщение)
        - messages: массив dict [{role, content, tool_calls, tool_call_id}] (OpenAI формат)
        - tools: массив OpenAI tools [{type, function: {name, description, parameters}}]
        """
        if messages is None:
            messages = [{"role": "user", "content": prompt}]

        for attempt in range(2):
            if not self.refresh_access_token(force=(attempt > 0)):
                yield "[ERROR] Service authorization failed, please try again later"
                return

            payload = self._build_payload(messages, model, tools=tools)

            headers = {
                "Content-Type": "application/json",
                "Accept": "application/vnd.amazon.eventstream",
                "X-Amz-Target": "AmazonCodeWhispererStreamingService.GenerateAssistantResponse",
                "Authorization": f"Bearer {self.access_token}",
                "User-Agent": "AWS-SDK-JS/3.0.0 kiro-ide/1.0.0",
                "X-Amz-User-Agent": "aws-sdk-js/3.0.0 kiro-ide/1.0.0",
                "Amz-Sdk-Request": "attempt=1; max=3",
                "Amz-Sdk-Invocation-Id": str(uuid.uuid4()),
            }

            try:
                response = self.session.post(
                    self.API_ENDPOINT,
                    json=payload,
                    headers=headers,
                    stream=True,
                    timeout=120,
                )

                if response.status_code == 200:
                    # Parse AWS EventStream binary
                    buffer = b""
                    # Accumulate tool calls across multiple frames
                    pending_tool_calls = {}  # toolUseId → {id, name, args_parts[]}
                    for chunk in response.iter_content(chunk_size=None):
                        if not chunk:
                            continue
                        buffer += chunk

                        # Parse frames from buffer
                        while len(buffer) >= 16:
                            total_len = struct.unpack(">I", buffer[0:4])[0]
                            if total_len < 16 or len(buffer) < total_len:
                                break

                            frame_data = buffer[:total_len]
                            buffer = buffer[total_len:]

                            event_type, event_payload = _parse_event_frame(frame_data)

                            if event_type == "assistantResponseEvent" and event_payload:
                                content = event_payload.get("content", "")
                                if content:
                                    yield content

                            elif event_type == "codeEvent" and event_payload:
                                content = event_payload.get("content", "")
                                if content:
                                    yield content

                            elif event_type == "toolUseEvent" and event_payload:
                                # Kiro sends partial tool calls across multiple frames
                                tool_uses = event_payload if isinstance(event_payload, list) else [event_payload]
                                for tu in tool_uses:
                                    tid = tu.get("toolUseId", "")
                                    name = tu.get("name", "")
                                    inp = tu.get("input", {})

                                    if tid and tid not in pending_tool_calls:
                                        pending_tool_calls[tid] = {
                                            "id": tid,
                                            "name": name,
                                            "args_json": "",
                                        }
                                    if tid:
                                        # Accumulate arguments as JSON string
                                        if isinstance(inp, dict) and inp:
                                            pending_tool_calls[tid]["args_json"] += json.dumps(inp)
                                        elif isinstance(inp, str):
                                            pending_tool_calls[tid]["args_json"] += inp
                                        if name and not pending_tool_calls[tid]["name"]:
                                            pending_tool_calls[tid]["name"] = name

                            elif event_type == "messageStopEvent":
                                # Emit accumulated tool calls
                                if pending_tool_calls:
                                    tool_calls = []
                                    for tid, tc_data in pending_tool_calls.items():
                                        # Try to parse accumulated args
                                        args_str = tc_data["args_json"]
                                        # Try to fix concatenated JSON: {"a": 1}{"b": 2} → {"a": 1, "b": 2}
                                        if args_str.count("{") > 1:
                                            try:
                                                # Remove }{ and wrap
                                                merged = args_str.replace("}{", ", ")
                                                json.loads(merged)
                                                args_str = merged
                                            except Exception:
                                                pass
                                        if not args_str:
                                            args_str = "{}"
                                        tool_calls.append({
                                            "id": tc_data["id"],
                                            "type": "function",
                                            "function": {
                                                "name": tc_data["name"],
                                                "arguments": args_str,
                                            }
                                        })
                                    yield {"type": "tool_calls", "tool_calls": tool_calls}
                                    pending_tool_calls = {}

                    # If we have pending tool calls that weren't flushed by messageStopEvent
                    if pending_tool_calls:
                        tool_calls = []
                        for tid, tc_data in pending_tool_calls.items():
                            args_str = tc_data["args_json"] or "{}"
                            if args_str.count("{") > 1:
                                try:
                                    args_str = args_str.replace("}{", ", ")
                                    json.loads(args_str)
                                except Exception:
                                    pass
                            tool_calls.append({
                                "id": tc_data["id"],
                                "type": "function",
                                "function": {
                                    "name": tc_data["name"],
                                    "arguments": args_str,
                                }
                            })
                        yield {"type": "tool_calls", "tool_calls": tool_calls}

                    return  # Успех

                elif response.status_code == 429:
                    # Rate limited — set cooldown and signal for retry
                    self._cooldown_until = time.time() + 30
                    logger.warning("[%s] API returned 429 — cooldown 30s", self.account_id)
                    yield "[RETRY]"
                    return

                elif response.status_code in (401, 403) and attempt == 0:
                    logger.warning("[%s] API got %d — refreshing token",
                                   self.account_id, response.status_code)
                    self._last_token_refresh = 0
                    continue

                else:
                    body_preview = response.text[:200] if response.text else "(empty)"
                    logger.error("[%s] API failed: HTTP %s — %s",
                                 self.account_id, response.status_code, body_preview)
                    yield "[ERROR] Service temporarily unavailable"
                    return

            except Exception as e:
                logger.error("[%s] Stream exception: %s", self.account_id, e)
                yield "[ERROR] Service error, please try again"
                return
