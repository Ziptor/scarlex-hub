#!/bin/bash
# ──────────────────────────────────────────────────────────────
# Kiro Proxy — Install Script for Ubuntu 24.04
# ──────────────────────────────────────────────────────────────
#
# Использование:
#   chmod +x install.sh
#   sudo ./install.sh
#
# После установки:
#   1. Скопируйте accounts.json в /opt/kiro-proxy/
#   2. Отредактируйте /opt/kiro-proxy/.env (API ключ)
#   3. sudo systemctl start kiro-proxy
#   4. (Опционально) Настройте nginx + certbot для HTTPS
#
# ──────────────────────────────────────────────────────────────

set -euo pipefail

APP_DIR="/opt/kiro-proxy"
APP_USER="kiro"
REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "═══════════════════════════════════════════════════"
echo "  Kiro Proxy — Installer"
echo "═══════════════════════════════════════════════════"
echo ""

# 1. Системные зависимости
echo "[1/7] Installing system dependencies..."
apt-get update -qq
apt-get install -y -qq python3 python3-venv python3-pip nginx certbot python3-certbot-nginx

# 2. Создаём пользователя
echo "[2/7] Creating user '$APP_USER'..."
if ! id "$APP_USER" &>/dev/null; then
    useradd --system --shell /bin/false --create-home --home-dir "$APP_DIR" "$APP_USER"
else
    echo "    User already exists, skipping"
fi

# 3. Создаём директорию
echo "[3/7] Setting up $APP_DIR..."
mkdir -p "$APP_DIR"

# Копируем файлы проекта
cp "$REPO_DIR/kiro_core.py" "$APP_DIR/"
cp "$REPO_DIR/server.py" "$APP_DIR/"
cp "$REPO_DIR/requirements.txt" "$APP_DIR/"

# .env (только если не существует — не затираем настройки)
if [ ! -f "$APP_DIR/.env" ]; then
    cp "$REPO_DIR/deploy/.env.example" "$APP_DIR/.env"
    echo "    Created .env from template — EDIT IT!"
else
    echo "    .env already exists, keeping"
fi

# accounts.json — напоминание
if [ ! -f "$APP_DIR/accounts.json" ]; then
    echo ""
    echo "  ⚠️  accounts.json NOT FOUND in $APP_DIR"
    echo "  Copy it manually: cp /path/to/accounts.json $APP_DIR/"
    echo ""
fi

chown -R "$APP_USER:$APP_USER" "$APP_DIR"

# 4. Python venv
echo "[4/7] Creating Python virtualenv..."
python3 -m venv "$APP_DIR/venv"
"$APP_DIR/venv/bin/pip" install --quiet --upgrade pip
"$APP_DIR/venv/bin/pip" install --quiet -r "$APP_DIR/requirements.txt"

# 5. Systemd unit
echo "[5/7] Installing systemd service..."
cp "$REPO_DIR/deploy/kiro-proxy.service" /etc/systemd/system/kiro-proxy.service
systemctl daemon-reload
systemctl enable kiro-proxy

# 6. Nginx
echo "[6/7] Installing nginx config..."
cp "$REPO_DIR/deploy/nginx.conf" /etc/nginx/sites-available/kiro-proxy
if [ ! -L /etc/nginx/sites-enabled/kiro-proxy ]; then
    ln -s /etc/nginx/sites-available/kiro-proxy /etc/nginx/sites-enabled/kiro-proxy
fi
# Удаляем дефолт если есть
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# 7. Готово
echo "[7/7] Done!"
echo ""
echo "═══════════════════════════════════════════════════"
echo "  Installation complete!"
echo "═══════════════════════════════════════════════════"
echo ""
echo "  Next steps:"
echo ""
echo "  1. Copy accounts.json:"
echo "     scp accounts.json root@server:$APP_DIR/"
echo ""
echo "  2. Edit configuration:"
echo "     nano $APP_DIR/.env"
echo ""
echo "  3. Start the service:"
echo "     sudo systemctl start kiro-proxy"
echo "     sudo systemctl status kiro-proxy"
echo ""
echo "  4. Check logs:"
echo "     journalctl -u kiro-proxy -f"
echo ""
echo "  5. (Optional) Enable HTTPS:"
echo "     sudo certbot --nginx -d your-domain.com"
echo ""
echo "  6. Test:"
echo "     curl http://localhost:8000/status"
echo "     curl http://localhost:8000/health"
echo ""
