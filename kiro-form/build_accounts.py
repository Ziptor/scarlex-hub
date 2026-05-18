import os
import sqlite3
import json
import re
import uuid
import requests


AUTH_ENDPOINT = "https://prod.us-east-1.auth.desktop.kiro.dev/refreshToken"
Q_SERVICE_URL = "https://q.us-east-1.amazonaws.com"


def check_token_status(refresh_token, access_token, login_method="Google"):
    """
    Проверяет валидность токенов через ПРЯМОЙ auth API (не web portal!).
    Возвращает (status, auth_data, error).
    """
    try:
        r = requests.post(
            AUTH_ENDPOINT,
            json={"refreshToken": refresh_token},
            headers={"Content-Type": "application/json", "Accept": "application/json"},
            timeout=15,
        )
        if r.status_code == 200:
            return "active", r.json(), None
        else:
            return "stale", None, None
    except Exception as e:
        return "error", None, str(e)


def fetch_email(access_token, profile_arn):
    """
    Получает email через Q Service API (getUsageLimits).
    Безопасный API — НЕ убивает refresh_token.
    """
    machine_id = str(uuid.uuid4())
    arn_param = f"&profileArn={requests.utils.quote(profile_arn)}" if profile_arn else ""
    url = f"{Q_SERVICE_URL}/getUsageLimits?isEmailRequired=true&origin=AI_EDITOR{arn_param}&resourceType=AGENTIC_REQUEST"
    headers = {
        "Authorization": f"Bearer {access_token}",
        "accept": "application/json",
        "user-agent": f"KiroIDE 0.6.18 {machine_id}",
        "x-amz-user-agent": f"KiroIDE 0.6.18 {machine_id}",
        "amz-sdk-invocation-id": str(uuid.uuid4()),
        "amz-sdk-request": "attempt=1; max=1",
    }
    try:
        r = requests.get(url, headers=headers, timeout=15)
        if r.status_code == 200:
            return r.json().get("userInfo", {}).get("email", "")
    except Exception:
        pass
    return ""


def _read_token_json(folder):
    """Читает токены из token.json (новый формат от add_account.py / Kiro IDE)."""
    path = os.path.join(folder, 'token.json')
    if not os.path.exists(path):
        return None

    with open(path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    rt = data.get("refresh_token")
    if not rt:
        return None

    return {
        "account_id": folder,
        "login_method": data.get("provider", "Unknown").capitalize(),
        "email": data.get("email", ""),
        "access_token": data.get("access_token"),
        "refresh_token": rt,
        "profile_arn": data.get("profile_arn", "arn:aws:codewhisperer:us-east-1:699475941385:profile/EHGA3GRVQMUK"),
        "proxy": ""
    }


def _read_data_sqlite3(folder):
    """Читает токены из data.sqlite3 (легаси kiro-cli формат)."""
    db_path = os.path.join(folder, 'data.sqlite3')
    if not os.path.exists(db_path):
        return None

    account_data = {
        "account_id": folder,
        "login_method": "Unknown",
        "email": "",
        "access_token": None,
        "refresh_token": None,
        "profile_arn": "arn:aws:codewhisperer:us-east-1:699475941385:profile/EHGA3GRVQMUK",
        "proxy": ""
    }

    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM auth_kv")
    rows = cursor.fetchall()

    for row in rows:
        for col in row:
            if not isinstance(col, str):
                continue

            lower_col = col.lower()

            # 1. Метод логина
            if account_data["login_method"] == "Unknown":
                if 'github' in lower_col and 'github.com' not in lower_col:
                    account_data["login_method"] = "GitHub"
                elif '"google"' in lower_col or "'google'" in lower_col or 'idp=google' in lower_col:
                    account_data["login_method"] = "Google"

            # 2. Токены из JSON
            if '{' in col:
                try:
                    data = json.loads(col)
                    if isinstance(data, dict):
                        if "refresh_token" in data:
                            account_data["refresh_token"] = data["refresh_token"]
                        elif "refreshToken" in data:
                            account_data["refresh_token"] = data["refreshToken"]

                        if "access_token" in data:
                            account_data["access_token"] = data["access_token"]
                        elif "accessToken" in data:
                            account_data["access_token"] = data["accessToken"]

                        if "profile_arn" in data:
                            account_data["profile_arn"] = data["profile_arn"]

                        if "provider" in data:
                            account_data["login_method"] = data["provider"].capitalize()
                except json.JSONDecodeError:
                    pass

            # 3. Email
            if '@' in col:
                match = re.search(r'[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+', col)
                if match and "amazon" not in match.group(0).lower():
                    account_data["email"] = match.group(0)

    conn.close()
    return account_data if account_data["refresh_token"] else None


def extract_accounts():
    accounts = []
    skipped = []

    # Рабочая директория = папка, где лежит сам скрипт
    script_dir = os.path.dirname(os.path.abspath(__file__))
    os.chdir(script_dir)

    # Ищем все папки kiro-N, сортируем по номеру
    folders = []
    for f in os.listdir(script_dir):
        m = re.match(r'^kiro-(\d+)$', f)
        if m and os.path.isdir(os.path.join(script_dir, f)):
            folders.append((int(m.group(1)), f))
    folders.sort()
    folders = [f for _, f in folders]

    if not folders:
        print("❌ Папки kiro-* не найдены в текущей директории.")
        return

    print(f"🚀 Найдено папок: {len(folders)}. Начинаю сбор данных...\n")

    for folder in folders:
        # Приоритет: token.json → data.sqlite3
        try:
            account_data = _read_token_json(folder)
            if not account_data:
                account_data = _read_data_sqlite3(folder)
        except Exception as e:
            print(f"❌ [{folder}] Ошибка чтения: {e}")
            continue

        if not account_data or not account_data.get("refresh_token"):
            print(f"⚠️  [{folder}] Токены не найдены, пропускаем.")
            continue

        # ─── Валидация токенов (через direct auth API) ───
        print(f"🔍 [{folder}] Проверяю токены...", end=" ", flush=True)
        status, auth_data, error = check_token_status(
            account_data["refresh_token"],
            account_data["access_token"],
            account_data["login_method"]
        )

        if error:
            print(f"⚠️  Ошибка сети: {error}")
            accounts.append(account_data)
            continue

        if status == "active":
            # Если email пустой — подтягиваем через Q Service API
            if not account_data.get("email") and auth_data:
                fresh_at = auth_data.get("accessToken", "")
                arn = auth_data.get("profileArn", account_data.get("profile_arn", ""))
                email = fetch_email(fresh_at, arn)
                if email:
                    account_data["email"] = email
                    # Обновляем token.json если он есть
                    token_path = os.path.join(folder, 'token.json')
                    if os.path.exists(token_path):
                        with open(token_path, 'r', encoding='utf-8') as f:
                            td = json.load(f)
                        td["email"] = email
                        with open(token_path, 'w', encoding='utf-8') as f:
                            json.dump(td, f, indent=2, ensure_ascii=False)

            accounts.append(account_data)
            print(f"✅ active | {account_data['login_method']:<6} | {account_data.get('email', '')}")
        elif status == "suspended":
            skipped.append((folder, "suspended"))
            print("🚫 ЗАБЛОКИРОВАН — пропущен")
        elif status == "stale":
            skipped.append((folder, "stale"))
            print("💀 Токен мёртв (stale) — пропущен")
        elif status == "anonymous":
            skipped.append((folder, "anonymous"))
            print("👻 Не авторизован — пропущен")
        else:
            skipped.append((folder, status))
            print(f"❓ Неизвестный статус: {status} — пропущен")

    # Сохраняем только валидные аккаунты
    output_file = 'accounts.json'
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(accounts, f, indent=4, ensure_ascii=False)

    print(f"\n{'='*50}")
    print(f"✅ Валидных аккаунтов: {len(accounts)}")
    if skipped:
        print(f"❌ Пропущено: {len(skipped)}")
        for name, reason in skipped:
            print(f"   — {name}: {reason}")
    print(f"📂 Сохранено в: {output_file}")


if __name__ == "__main__":
    extract_accounts()