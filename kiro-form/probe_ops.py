"""
Полный вывод кредитов kiro-1 через GetUserUsageAndLimits.
"""
import cbor2
import requests
import re
import json
import os
import sys

accounts_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'accounts.json')
with open(accounts_path, 'r', encoding='utf-8') as f:
    accounts = json.load(f)

acc = next((a for a in accounts if a["account_id"] == "kiro-1"), None)
if not acc:
    print("❌ kiro-1 не найден")
    sys.exit(1)

HEADERS_BASE = {
    "accept": "application/cbor",
    "content-type": "application/cbor",
    "smithy-protocol": "rpc-v2-cbor",
    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "origin": "https://app.kiro.dev",
}

session = requests.Session()
session.cookies.set("RefreshToken", acc["refresh_token"], domain="app.kiro.dev")
session.cookies.set("AccessToken", acc["access_token"], domain="app.kiro.dev")
session.cookies.set("Idp", acc.get("login_method", "Google"), domain="app.kiro.dev")

res = session.get("https://app.kiro.dev/", timeout=15)
m = re.search(r'name=["\']csrf-token["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
csrf = m.group(1) if m else None

headers = HEADERS_BASE.copy()
headers["x-csrf-token"] = csrf
r = session.post(
    "https://app.kiro.dev/service/KiroWebPortalService/operation/GetToken",
    data=cbor2.dumps({"profileArn": acc["profile_arn"]}),
    headers=headers, timeout=15
)
access = session.cookies.get("AccessToken", acc["access_token"])

headers_auth = HEADERS_BASE.copy()
headers_auth["x-csrf-token"] = csrf
headers_auth["authorization"] = f"Bearer {access}"

# GetUserUsageAndLimits
r = session.post(
    "https://app.kiro.dev/service/KiroWebPortalService/operation/GetUserUsageAndLimits",
    data=cbor2.dumps({}),
    headers=headers_auth, timeout=15
)

if r.status_code == 200:
    data = cbor2.loads(r.content)
    print(json.dumps(data, default=str, indent=2, ensure_ascii=False))
else:
    print(f"❌ HTTP {r.status_code}")
