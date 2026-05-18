"""
Проверка кредитов для одного аккаунта (kiro-1).
Пробуем разные scopes и подходы.
НЕ вызывает GetToken — только GET для проверки + GetCredentials.
"""
import cbor2
import requests
import re
import json
import os
import sys

# Читаем accounts.json
accounts_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'accounts.json')
with open(accounts_path, 'r', encoding='utf-8') as f:
    accounts = json.load(f)

# Берём ТОЛЬКО kiro-1
acc = next((a for a in accounts if a["account_id"] == "kiro-1"), None)
if not acc:
    print("❌ kiro-1 не найден в accounts.json")
    sys.exit(1)

print(f"Аккаунт: {acc['account_id']} | {acc.get('login_method', '?')}")

HEADERS_BASE = {
    "accept": "application/cbor",
    "content-type": "application/cbor",
    "smithy-protocol": "rpc-v2-cbor",
    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "origin": "https://app.kiro.dev",
}

session = requests.Session()

# 1. GET → CSRF + status (НЕ вызываем GetToken!)
print("\n[1] GET app.kiro.dev → проверяем статус...")
session.cookies.set("RefreshToken", acc["refresh_token"], domain="app.kiro.dev")
session.cookies.set("AccessToken", acc["access_token"], domain="app.kiro.dev")
session.cookies.set("Idp", acc.get("login_method", "Google"), domain="app.kiro.dev")

res = session.get("https://app.kiro.dev/", timeout=15)

# user-status
m = re.search(r'name=["\']user-status["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
status = m.group(1) if m else "unknown"
print(f"    Status: {status}")

if status != "active":
    print(f"    ❌ Аккаунт не активен, выходим")
    sys.exit(1)

# CSRF
m = re.search(r'name=["\']csrf-token["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
if not m:
    m = re.search(r'content=["\']([^"\']+)["\'][^>]*name=["\']csrf-token["\']', res.text, re.IGNORECASE)
csrf = m.group(1) if m else None

if not csrf:
    print("    ❌ CSRF не найден")
    sys.exit(1)
print(f"    CSRF: {csrf[:25]}...")

# Проверяем allowlisted-features
m = re.search(r'name=["\']allowlisted-features["\'][^>]*value=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
if m:
    features = m.group(1)
    print(f"    Features: {features}")

# user-id
m = re.search(r'name=["\']user-id["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
if not m:
    m = re.search(r'content=["\']([^"\']+)["\'][^>]*name=["\']user-id["\']', res.text, re.IGNORECASE)
user_id = m.group(1) if m else None
print(f"    User ID: {user_id}")

# 2. GetToken — нужен для свежего access_token
print("\n[2] GetToken → обновляем access_token...")
headers = HEADERS_BASE.copy()
headers["x-csrf-token"] = csrf
payload = {"profileArn": acc["profile_arn"]}

r = session.post(
    "https://app.kiro.dev/service/KiroWebPortalService/operation/GetToken",
    data=cbor2.dumps(payload), headers=headers, timeout=15
)
print(f"    HTTP {r.status_code}")

if r.status_code != 200:
    print(f"    ❌ GetToken failed")
    sys.exit(1)

access = session.cookies.get("AccessToken", acc["access_token"])
print(f"    Access: {access[:40]}...")

# 3. GetUserInfo → featureFlags
print("\n[3] GetUserInfo → проверяем featureFlags...")
headers_auth = HEADERS_BASE.copy()
headers_auth["x-csrf-token"] = csrf
headers_auth["authorization"] = f"Bearer {access}"

r = session.post(
    "https://app.kiro.dev/service/KiroWebPortalService/operation/GetUserInfo",
    data=cbor2.dumps({"profileArn": acc["profile_arn"]}),
    headers=headers_auth, timeout=15
)
print(f"    HTTP {r.status_code}")

if r.status_code == 200:
    info = cbor2.loads(r.content)
    for k, v in info.items():
        print(f"    {k}: {str(v)[:100]}")
    
    flags = info.get("featureFlags", [])
    print(f"\n    enableNewBilling: {'enableNewBilling' in flags}")
else:
    print(f"    ❌ Error")

# 4. Пробуем разные scopes для GetCredentials
print("\n[4] GetCredentials → пробуем разные scopes...")

scopes = [
    "widget:cmc_usage_widget",
    "widget:cmc_manage_overage_widget",
    "cmc_usage_widget",
    "usage",
    "",
]

for scope in scopes:
    headers_cred = HEADERS_BASE.copy()
    headers_cred["x-csrf-token"] = csrf
    headers_cred["authorization"] = f"Bearer {access}"
    
    payload = {"profileArn": acc["profile_arn"]}
    if scope:
        payload["scope"] = scope
    
    r = session.post(
        "https://app.kiro.dev/service/KiroWebPortalService/operation/GetCredentials",
        data=cbor2.dumps(payload), headers=headers_cred, timeout=15
    )
    
    if r.status_code == 200:
        try:
            data = cbor2.loads(r.content)
            print(f"    ✅ scope='{scope}' → keys: {list(data.keys())}")
            # Если есть credentialToken — парсим
            if "credentialToken" in data:
                cred = json.loads(data["credentialToken"])
                print(f"       accessKeyId: {cred.get('accessKeyId', '')[:20]}...")
                print(f"       expiry: {data.get('expiry', 'N/A')}")
        except:
            print(f"    ✅ scope='{scope}' → raw response")
    else:
        try:
            err = cbor2.loads(r.content)
            msg = err.get("message", str(err))[:80]
        except:
            msg = r.text[:80]
        print(f"    ❌ scope='{scope}' → HTTP {r.status_code}: {msg}")

# 5. Пробуем напрямую вытащить кредиты из HTML (может есть meta-теги)
print("\n[5] Проверяем Settings страницу...")
settings_res = session.get("https://app.kiro.dev/settings/usage", timeout=15)
print(f"    HTTP {settings_res.status_code}, длина: {len(settings_res.text)}")

# Смотрим meta-теги
for meta in re.findall(r'<meta[^>]*>', settings_res.text, re.IGNORECASE):
    if any(kw in meta.lower() for kw in ['credit', 'usage', 'billing', 'plan', 'subscription', 'limit']):
        print(f"    {meta.strip()}")
