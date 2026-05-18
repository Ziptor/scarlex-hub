"""
Probe Kiro credits/usage via web portal.
Tries both KiroWebPortalService and direct Zorn API approaches.
"""
import cbor2
import requests
import re
import json
import sys
import os

# Use first account from accounts.json
accounts_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'accounts.json')
with open(accounts_path, 'r', encoding='utf-8') as f:
    accounts = json.load(f)

acc = accounts[0]
print(f"Using account: {acc['account_id']}")

session = requests.Session()

# Init web session
session.cookies.set("RefreshToken", acc["refresh_token"], domain="app.kiro.dev")
session.cookies.set("AccessToken", acc["access_token"], domain="app.kiro.dev")
session.cookies.set("Idp", acc.get("login_method", "Google"), domain="app.kiro.dev")

res = session.get("https://app.kiro.dev/")
match = re.search(r'name=["\']csrf-token["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
if not match:
    match = re.search(r'content=["\']([^"\']+)["\'][^>]*name=["\']csrf-token["\']', res.text, re.IGNORECASE)

if not match:
    print("CSRF token not found!")
    sys.exit(1)

csrf = match.group(1)
print(f"CSRF: {csrf[:20]}...")

# Extract userId from HTML
uid_match = re.search(r'name=["\']user-id["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
if not uid_match:
    uid_match = re.search(r'content=["\']([^"\']+)["\'][^>]*name=["\']user-id["\']', res.text, re.IGNORECASE)
if uid_match:
    user_id = uid_match.group(1)
    print(f"User ID: {user_id}")
else:
    print("User ID not found in HTML")
    # Try to extract from access token cookie
    user_id = None

# Update tokens first
headers_base = {
    "accept": "application/cbor",
    "content-type": "application/cbor",
    "smithy-protocol": "rpc-v2-cbor",
    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "origin": "https://app.kiro.dev",
    "x-csrf-token": csrf,
}

print("\n--- Refreshing tokens ---")
payload = {"profileArn": acc["profile_arn"]}
r = session.post(
    "https://app.kiro.dev/service/KiroWebPortalService/operation/GetToken",
    data=cbor2.dumps(payload),
    headers=headers_base,
)
print(f"GetToken: {r.status_code}")
if r.status_code == 200:
    try:
        data = cbor2.loads(r.content)
        print(f"  Response keys: {list(data.keys()) if isinstance(data, dict) else type(data)}")
    except:
        pass

# Now try various operations that might return usage info
access = session.cookies.get("AccessToken", acc["access_token"])

operations = [
    "GetSubscription",
    "GetUsage", 
    "GetUsageMetrics",
    "ListUsages",
    "GetCredits",
    "GetBillingInfo",
    "GetUserProfile",
    "GetUserInfo",
    "DescribeProfile",
    "GetCredentials",
]

headers_auth = headers_base.copy()
headers_auth["authorization"] = f"Bearer {access}"

for op in operations:
    url = f"https://app.kiro.dev/service/KiroWebPortalService/operation/{op}"
    try:
        payload = {"profileArn": acc["profile_arn"]}
        r = session.post(url, data=cbor2.dumps(payload), headers=headers_auth, timeout=10)
        status = r.status_code
        body = ""
        if r.content:
            try:
                decoded = cbor2.loads(r.content)
                body = json.dumps(decoded, default=str)[:200]
            except:
                body = r.content[:100].hex()
        print(f"  {op}: {status} -> {body}")
    except Exception as e:
        print(f"  {op}: ERROR -> {e}")

# Try GetCredentials with scope for usage widget
print("\n--- Trying GetCredentials with scope ---")
for scope in ["widget:cmc_usage_widget", "widget:cmc_manage_overage_widget"]:
    try:
        payload = {"scope": scope, "profileArn": acc["profile_arn"]}
        r = session.post(
            "https://app.kiro.dev/service/KiroWebPortalService/operation/GetCredentials",
            data=cbor2.dumps(payload),
            headers=headers_auth,
            timeout=10
        )
        print(f"  GetCredentials({scope}): {r.status_code}")
        if r.content:
            try:
                decoded = cbor2.loads(r.content)
                keys = list(decoded.keys()) if isinstance(decoded, dict) else type(decoded)
                print(f"    Keys: {keys}")
                if "credentialToken" in decoded:
                    cred = json.loads(decoded["credentialToken"])
                    print(f"    Cred keys: {list(cred.keys())}")
            except:
                print(f"    Raw: {r.content[:100].hex()}")
    except Exception as e:
        print(f"  GetCredentials({scope}): ERROR -> {e}")
