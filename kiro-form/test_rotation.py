"""
Проверяем: ротирует ли GetToken refresh_token?
Смотрим Set-Cookie заголовки в ответе.
Используем kiro-3 (живой, не жалко).
"""
import cbor2
import requests
import re
import json
import os

accounts_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'accounts.json')
script_dir = os.path.dirname(os.path.abspath(__file__))

# Сначала пробуем из accounts.json, если нет — из SQLite
try:
    with open(accounts_path, 'r', encoding='utf-8') as f:
        accounts = json.load(f)
    acc = next(a for a in accounts if a["account_id"] == "kiro-1")
except (StopIteration, FileNotFoundError):
    # kiro-1 нет в accounts.json — читаем напрямую из SQLite
    import sqlite3
    db_path = os.path.join(script_dir, "kiro-1", "data.sqlite3")
    conn = sqlite3.connect(db_path)
    rows = conn.execute("SELECT * FROM auth_kv").fetchall()
    conn.close()
    acc = {"account_id": "kiro-1", "login_method": "Google", "refresh_token": None, "access_token": None,
           "profile_arn": "arn:aws:codewhisperer:us-east-1:699475941385:profile/EHGA3GRVQMUK"}
    for row in rows:
        for col in row:
            if isinstance(col, str) and '{' in col:
                try:
                    data = json.loads(col)
                    if isinstance(data, dict):
                        acc["refresh_token"] = data.get("refresh_token") or data.get("refreshToken") or acc["refresh_token"]
                        acc["access_token"] = data.get("access_token") or data.get("accessToken") or acc["access_token"]
                        if "provider" in data:
                            acc["login_method"] = data["provider"].capitalize()
                except json.JSONDecodeError:
                    pass
print(f"Тестируем: {acc['account_id']}")
print(f"Старый refresh: {acc['refresh_token'][:50]}...")

session = requests.Session()
session.cookies.set("RefreshToken", acc["refresh_token"], domain="app.kiro.dev")
session.cookies.set("AccessToken", acc["access_token"], domain="app.kiro.dev")
session.cookies.set("Idp", acc.get("login_method", "Google"), domain="app.kiro.dev")

# GET → CSRF
res = session.get("https://app.kiro.dev/", timeout=15)
csrf = None
match = re.search(r'name=["\']csrf-token["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
if not match:
    match = re.search(r'content=["\']([^"\']+)["\'][^>]*name=["\']csrf-token["\']', res.text, re.IGNORECASE)
if match:
    csrf = match.group(1)
    print(f"CSRF: OK")
else:
    print("CSRF не найден!")
    exit(1)

# GetToken
headers = {
    "accept": "application/cbor",
    "content-type": "application/cbor",
    "smithy-protocol": "rpc-v2-cbor",
    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "origin": "https://app.kiro.dev",
    "x-csrf-token": csrf,
}

payload = {"profileArn": acc["profile_arn"]}
r = session.post(
    "https://app.kiro.dev/service/KiroWebPortalService/operation/GetToken",
    data=cbor2.dumps(payload),
    headers=headers,
    timeout=15
)

print(f"\nGetToken: HTTP {r.status_code}")

# Смотрим Set-Cookie заголовки
print("\n--- Set-Cookie заголовки ---")
for header, value in r.headers.items():
    if header.lower() == 'set-cookie':
        # Маскируем значение
        if 'RefreshToken' in value:
            print(f"  ⚠️  RefreshToken ЕСТЬ в Set-Cookie!")
            # Вытащим значение
            rt_match = re.search(r'RefreshToken=([^;]+)', value)
            if rt_match:
                new_rt = rt_match.group(1)
                old_rt = acc['refresh_token']
                if new_rt == old_rt:
                    print(f"     → ТОЖЕ САМОЕ значение (не ротируется!)")
                else:
                    print(f"     → НОВОЕ значение! (ротация!)")
                    print(f"     Старый: {old_rt[:50]}...")
                    print(f"     Новый:  {new_rt[:50]}...")
        elif 'AccessToken' in value:
            print(f"  ✅ AccessToken обновлён в Set-Cookie")
        else:
            cookie_name = value.split('=')[0] if '=' in value else value[:30]
            print(f"  📋 {cookie_name}")

# Проверяем cookies в сессии после запроса
print("\n--- Cookies после GetToken ---")
new_refresh = session.cookies.get("RefreshToken")
new_access = session.cookies.get("AccessToken")

if new_refresh == acc["refresh_token"]:
    print(f"✅ RefreshToken НЕ изменился — ротации НЕТ!")
elif new_refresh:
    print(f"⚠️  RefreshToken ИЗМЕНИЛСЯ — ротация ЕСТЬ!")
    print(f"   Старый: {acc['refresh_token'][:50]}...")
    print(f"   Новый:  {new_refresh[:50]}...")
else:
    print(f"❓ RefreshToken не найден в cookies")

if new_access and new_access != acc["access_token"]:
    print(f"✅ AccessToken обновлён")
elif new_access:
    print(f"⚠️  AccessToken не изменился")
