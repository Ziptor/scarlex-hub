"""Test remote VPS proxy."""
import requests
import json

BASE = "http://62.109.20.160"

# 1. Health
r = requests.get(f"{BASE}/health", timeout=10)
print(f"1. /health: {r.json()}")

# 2. Chat (stream)
print("2. Chat (stream)...")
r = requests.post(
    f"{BASE}/v1/chat/completions",
    json={
        "model": "claude-sonnet-4",
        "messages": [{"role": "user", "content": "Say hello in one word"}],
        "stream": True,
    },
    stream=True,
    timeout=30,
)
print(f"   HTTP {r.status_code}")
text = ""
for line in r.iter_lines():
    decoded = line.decode()
    if decoded.startswith("data: {"):
        chunk = json.loads(decoded[6:])
        delta = chunk.get("choices", [{}])[0].get("delta", {}).get("content", "")
        text += delta
print(f"   Response: {text[:200]}")
print("   Done!")
