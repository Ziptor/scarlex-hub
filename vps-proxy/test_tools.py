"""Test tool calling on remote VPS."""
import requests
import json

BASE = "http://62.109.20.160"

# Tool calling test: ask model to get weather (it should call the tool)
payload = {
    "model": "claude-sonnet-4",
    "messages": [
        {"role": "user", "content": "What's the weather in Moscow? Use the get_weather tool."}
    ],
    "tools": [
        {
            "type": "function",
            "function": {
                "name": "get_weather",
                "description": "Get the current weather for a location",
                "parameters": {
                    "type": "object",
                    "properties": {
                        "location": {
                            "type": "string",
                            "description": "City name"
                        }
                    },
                    "required": ["location"]
                }
            }
        }
    ],
    "stream": False,
}

print("1. Testing tool calling (non-stream)...")
r = requests.post(f"{BASE}/v1/chat/completions", json=payload, timeout=30)
data = r.json()
print(f"   HTTP {r.status_code}")
choice = data["choices"][0]
print(f"   finish_reason: {choice['finish_reason']}")
msg = choice["message"]
print(f"   content: {msg.get('content', '(none)')}")
if msg.get("tool_calls"):
    for tc in msg["tool_calls"]:
        print(f"   tool_call: {tc['function']['name']}({tc['function']['arguments']})")
    print("   ✅ Tool calling works!")
else:
    print("   ❌ No tool_calls in response")

# Test 2: streaming tool calling
print("\n2. Testing tool calling (stream)...")
payload["stream"] = True
r = requests.post(f"{BASE}/v1/chat/completions", json=payload, stream=True, timeout=30)
print(f"   HTTP {r.status_code}")

text = ""
tool_calls = []
finish_reason = None

for line in r.iter_lines():
    decoded = line.decode()
    if not decoded.startswith("data: ") or decoded == "data: [DONE]":
        continue
    chunk = json.loads(decoded[6:])
    delta = chunk["choices"][0].get("delta", {})
    fr = chunk["choices"][0].get("finish_reason")
    if fr:
        finish_reason = fr

    if "content" in delta and delta["content"]:
        text += delta["content"]
    if "tool_calls" in delta:
        for tc in delta["tool_calls"]:
            tool_calls.append(tc)

print(f"   text: {text[:100] if text else '(none)'}")
print(f"   finish_reason: {finish_reason}")
if tool_calls:
    for tc in tool_calls:
        print(f"   tool_call: {tc['function']['name']}({tc['function']['arguments']})")
    print("   ✅ Streaming tool calling works!")
else:
    print("   ❌ No tool_calls in stream")
