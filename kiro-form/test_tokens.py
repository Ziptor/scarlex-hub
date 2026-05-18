"""
Тест токенов kiro-1 и kiro-2: проверяем полный цикл
refresh_token → CSRF → GetToken → access_token → тестовый запрос
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

if not accounts:
    print("accounts.json пуст!")
    sys.exit(1)

headers_base = {
    "accept": "application/cbor",
    "content-type": "application/cbor",
    "smithy-protocol": "rpc-v2-cbor",
    "user-agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "origin": "https://app.kiro.dev",
}

for acc in accounts:
    aid = acc["account_id"]
    print(f"\n{'='*50}")
    print(f"  {aid} | {acc.get('login_method', '?')} | {acc.get('email', '?')}")
    print(f"{'='*50}")

    session = requests.Session()

    # 1. Ставим куки
    session.cookies.set("RefreshToken", acc["refresh_token"], domain="app.kiro.dev")
    session.cookies.set("AccessToken", acc["access_token"], domain="app.kiro.dev")
    session.cookies.set("Idp", acc.get("login_method", "Google"), domain="app.kiro.dev")

    # 2. GET главная → CSRF
    print("\n[1] GET app.kiro.dev → ищем CSRF...")
    try:
        res = session.get("https://app.kiro.dev/", timeout=15)
        print(f"    HTTP {res.status_code}, длина HTML: {len(res.text)}")
    except Exception as e:
        print(f"    ❌ Ошибка запроса: {e}")
        continue

    csrf = None
    match = re.search(r'name=["\']csrf-token["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
    if not match:
        match = re.search(r'content=["\']([^"\']+)["\'][^>]*name=["\']csrf-token["\']', res.text, re.IGNORECASE)
    
    if match:
        csrf = match.group(1)
        print(f"    ✅ CSRF найден: {csrf[:25]}...")
    else:
        # Проверим, есть ли комментарий об отсутствии CSRF
        if "CSRF token not available" in res.text:
            print("    ❌ CSRF не выдан (unauthenticated) — refresh_token протух!")
        else:
            print("    ❌ CSRF не найден в HTML")
            # Покажем meta-теги для диагностики
            for meta in re.findall(r'<meta[^>]*>', res.text, re.IGNORECASE):
                if 'csrf' in meta.lower() or 'user' in meta.lower() or 'status' in meta.lower():
                    print(f"        {meta.strip()}")
        continue

    # Вытащим user-status
    status_match = re.search(r'name=["\']user-status["\'][^>]*content=["\']([^"\']+)["\']', res.text, re.IGNORECASE)
    if status_match:
        print(f"    User status: {status_match.group(1)}")

    # 3. GetToken → обновляем access_token
    print("\n[2] GetToken → обновляем токены...")
    headers = headers_base.copy()
    headers["x-csrf-token"] = csrf
    
    payload = {"profileArn": acc["profile_arn"]}
    try:
        r = session.post(
            "https://app.kiro.dev/service/KiroWebPortalService/operation/GetToken",
            data=cbor2.dumps(payload),
            headers=headers,
            timeout=15
        )
        print(f"    HTTP {r.status_code}")
        
        if r.status_code == 200:
            print("    ✅ Токены обновлены!")
            new_access = session.cookies.get("AccessToken")
            new_refresh = session.cookies.get("RefreshToken")
            if new_access:
                print(f"    AccessToken: {new_access[:40]}...")
            if new_refresh and new_refresh != acc["refresh_token"]:
                print(f"    ⚠️ RefreshToken ИЗМЕНИЛСЯ: {new_refresh[:40]}...")
            
            # Попробуем декодировать ответ
            try:
                data = cbor2.loads(r.content)
                print(f"    Response keys: {list(data.keys()) if isinstance(data, dict) else '(not dict)'}")
            except:
                pass
        else:
            try:
                data = cbor2.loads(r.content)
                print(f"    ❌ Ошибка: {data}")
            except:
                print(f"    ❌ Ошибка: {r.text[:200]}")
            continue
    except Exception as e:
        print(f"    ❌ Exception: {e}")
        continue

    # 4. Тестовый запрос — GetUserInfo
    print("\n[3] GetUserInfo → проверяем аккаунт...")
    access = session.cookies.get("AccessToken", acc["access_token"])
    headers_auth = headers.copy()
    headers_auth["authorization"] = f"Bearer {access}"
    
    try:
        r = session.post(
            "https://app.kiro.dev/service/KiroWebPortalService/operation/GetUserInfo",
            data=cbor2.dumps({"profileArn": acc["profile_arn"]}),
            headers=headers_auth,
            timeout=15
        )
        print(f"    HTTP {r.status_code}")
        if r.content:
            try:
                data = cbor2.loads(r.content)
                if isinstance(data, dict):
                    # Покажем основные поля
                    for k, v in data.items():
                        val = str(v)[:80]
                        print(f"    {k}: {val}")
            except:
                print(f"    Raw: {r.content[:100]}")
    except Exception as e:
        print(f"    ❌ Exception: {e}")

    print(f"\n{'='*50}")
    print(f"  {aid}: {'✅ ЖИВОЙ' if csrf else '❌ МЁРТВЫЙ'}")
    print(f"{'='*50}")
