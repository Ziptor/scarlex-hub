"""Quick test: refresh token via Kiro auth service (omniroute approach)."""
import json, os, requests

accounts_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "accounts.json")
with open(accounts_path) as f:
    accounts = json.load(f)

acc = accounts[0]  # kiro-1
print(f"Testing: {acc['account_id']}")
print(f"Refresh token: {acc['refresh_token'][:20]}...")

r = requests.post(
    "https://prod.us-east-1.auth.desktop.kiro.dev/refreshToken",
    json={"refreshToken": acc["refresh_token"]},
    headers={"Content-Type": "application/json", "Accept": "application/json"},
    timeout=15,
)

print(f"\nHTTP {r.status_code}")
if r.status_code == 200:
    data = r.json()
    print(f"✅ Got accessToken: {data.get('accessToken', '')[:30]}...")
    print(f"   profileArn: {data.get('profileArn', '')}")
    print(f"   refreshToken changed: {data.get('refreshToken', '') != acc['refresh_token']}")
else:
    print(f"❌ Error: {r.text[:300]}")
