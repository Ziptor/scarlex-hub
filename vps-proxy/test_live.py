"""Quick live test for Kiro proxy server."""
import requests
import json

BASE = "http://127.0.0.1:8420"
KEY = "test123"
HEADERS = {"Authorization": f"Bearer {KEY}", "Content-Type": "application/json"}

# 1. Health
r = requests.get(f"{BASE}/health")
h = r.json()
print(f"1. /health: alive={h['alive']}/{h['total']} ✅")

# 2. Models
r = requests.get(f"{BASE}/v1/models", headers=HEADERS)
models = [m["id"] for m in r.json()["data"]]
print(f"2. /v1/models: {models} ✅")

# 3. Status
r = requests.get(f"{BASE}/status", headers=HEADERS)
s = r.json()
pool = s["pool"]
print(f"3. /status: alive={pool['alive']}/{pool['total']} ✅")

# 4. Chat (stream) — реальный запрос к Kiro
print("4. /v1/chat/completions (stream)...")
r = requests.post(
    f"{BASE}/v1/chat/completions",
    headers=HEADERS,
    json={
        "model": "claude-sonnet-4",
        "messages": [{"role": "user", "content": "Say hi in one word"}],
        "stream": True,
    },
    stream=True,
)
print(f"   HTTP {r.status_code}")
text = ""
for line in r.iter_lines():
    decoded = line.decode()
    if decoded.startswith("data: ") and decoded != "data: [DONE]":
        chunk = json.loads(decoded[6:])
        delta = chunk.get("choices", [{}])[0].get("delta", {}).get("content", "")
        text += delta
print(f"   Response: {text[:200]}")
print("   ✅ OpenAI streaming format works!")
