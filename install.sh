#!/usr/bin/env bash
#
# install.sh — Erstinstallation von TIAMO Pronto (pronto.tiamo-pizza.de) auf
# einem Ubuntu-Server, auf dem nginx, PHP 8.4, PostgreSQL, Node und
# Supervisor laufen dürfen (fehlende Pakete werden nachinstalliert).
#
# Richtet ein: Datenbank + Benutzer, Repository, .env (inkl. Reverb- und
# VAPID-Schlüsseln), nginx-vHost mit WebSocket-Proxy für Reverb, Supervisor
# für Queue-Worker und Reverb, Cron für den Scheduler, Let's-Encrypt-
# Zertifikat und den ersten Admin-Zugang. Den App-Build macht deploy.sh.
#
# Andere Seiten auf dem Server bleiben unberührt (eigener vHost, eigene
# Supervisor-Programme, eigener Cron-Eintrag).
#
# Verwendung:
#   bash install.sh                 Wizard
#   bash install.sh --non-interactive
#   bash install.sh --no-tls        ohne Zertifikat
#
# Erneutes Ausführen ist unkritisch: jeder Schritt prüft erst, ob er schon
# erledigt ist. Vergebene Passwörter und Schlüssel werden nicht überschrieben.
#
set -euo pipefail

DOMAIN="${DOMAIN:-pronto.tiamo-pizza.de}"
APP_DIR="${APP_DIR:-/var/www/pronto}"
REPO_URL="${REPO_URL:-git@github.com:tmerle24/pronto.git}"
BRANCH="${BRANCH:-main}"
DB_NAME="${DB_NAME:-pronto}"
DB_USER="${DB_USER:-pronto}"
DB_PASSWORD="${DB_PASSWORD:-}"
PHP_VERSION="${PHP_VERSION:-8.4}"
WEB_USER="${WEB_USER:-www-data}"
REVERB_PORT="${REVERB_PORT:-8048}"
TLS_EMAIL="${TLS_EMAIL:-team@tiamo-pizza.de}"
ADMIN_EMAIL="${ADMIN_EMAIL:-team@tiamo-pizza.de}"
ADMIN_NAME="${ADMIN_NAME:-Till}"
SUPERVISOR_PREFIX="pronto"

INTERACTIVE=true
WITH_TLS=true
for arg in "$@"; do
    case "$arg" in
        --non-interactive|-y) INTERACTIVE=false ;;
        --no-tls)             WITH_TLS=false ;;
        --help|-h)            sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *)                    echo "Unbekannte Option: $arg" >&2; exit 1 ;;
    esac
done

STEP=0
TOTAL=10
step() { STEP=$((STEP + 1)); printf '\n\033[1;31m═══ %s/%s %s ═══\033[0m\n' "$STEP" "$TOTAL" "$1"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$1"; }
skip() { printf '  \033[2m→ %s\033[0m\n' "$1"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$1"; }
die()  { printf '\n\033[31m✗ %s\033[0m\n' "$1" >&2; exit 1; }
ask()  {
    local answer
    if [ "$INTERACTIVE" = false ]; then printf '%s' "$2"; return; fi
    read -r -p "  $1 [$2]: " answer </dev/tty
    printf '%s' "${answer:-$2}"
}

[ "$(id -u)" -eq 0 ] && die "Bitte nicht als root ausführen, sondern als Benutzer mit sudo-Rechten."
sudo -n true 2>/dev/null || sudo true || die "sudo-Rechte fehlen."

cat <<BANNER

  ╭──────────────────────────────────────────────╮
  │  TI♥AMO Pronto — Erstinstallation             │
  ╰──────────────────────────────────────────────╯

BANNER

DOMAIN=$(ask "Domain" "$DOMAIN")
APP_DIR=$(ask "Installationsverzeichnis" "$APP_DIR")
ADMIN_EMAIL=$(ask "Erster Admin-Zugang (E-Mail)" "$ADMIN_EMAIL")
REVERB_PORT=$(ask "Interner Port für Reverb (nur 127.0.0.1)" "$REVERB_PORT")

if ss -ltn | awk '{print $4}' | grep -qE "[:.]$REVERB_PORT\$" && ! sudo supervisorctl status "${SUPERVISOR_PREFIX}-reverb" >/dev/null 2>&1; then
    die "Port $REVERB_PORT ist schon belegt. Mit REVERB_PORT=... einen freien wählen."
fi

# ── 1. Pakete ───────────────────────────────────────────────────────
step "Pakete"
missing=()
for pkg in nginx postgresql supervisor git unzip "php$PHP_VERSION-fpm" "php$PHP_VERSION-pgsql" \
    "php$PHP_VERSION-mbstring" "php$PHP_VERSION-xml" "php$PHP_VERSION-curl" "php$PHP_VERSION-zip" \
    "php$PHP_VERSION-gd" "php$PHP_VERSION-intl" "php$PHP_VERSION-bcmath"; do
    dpkg -s "$pkg" >/dev/null 2>&1 || missing+=("$pkg")
done
if [ ${#missing[@]} -gt 0 ]; then
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq "${missing[@]}" >/dev/null
    ok "nachinstalliert: ${missing[*]}"
else
    skip "alles da"
fi
command -v composer >/dev/null || die "Composer fehlt (https://getcomposer.org)."
command -v node >/dev/null || die "Node fehlt (Node 22 empfohlen)."
ok "PHP $("php$PHP_VERSION" -r 'echo PHP_VERSION;'), Node $(node -v)"

# ── 2. Datenbank ────────────────────────────────────────────────────
step "Datenbank"
ENV_EXISTS=false
[ -f "$APP_DIR/.env" ] && ENV_EXISTS=true
DB_PASSWORD_KNOWN=false
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'" | grep -q 1; then
    [ -n "$DB_PASSWORD" ] || DB_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)
    sudo -u postgres psql -q <<SQL
CREATE ROLE "$DB_USER" LOGIN PASSWORD '$DB_PASSWORD';
SQL
    DB_PASSWORD_KNOWN=true
    ok "Rolle $DB_USER angelegt"
elif [ "$ENV_EXISTS" = false ]; then
    DB_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)
    sudo -u postgres psql -q <<SQL
ALTER ROLE "$DB_USER" WITH LOGIN PASSWORD '$DB_PASSWORD';
SQL
    DB_PASSWORD_KNOWN=true
    warn "Rolle $DB_USER existierte ohne .env — Passwort neu gesetzt"
else
    skip "Rolle $DB_USER existiert"
fi
if sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1; then
    skip "Datenbank $DB_NAME existiert"
else
    sudo -u postgres createdb -O "$DB_USER" -E UTF8 "$DB_NAME"
    ok "Datenbank $DB_NAME angelegt"
fi
sudo -u postgres psql -q -d "$DB_NAME" -c "ALTER SCHEMA public OWNER TO \"$DB_USER\";"

# ── 3. Repository ───────────────────────────────────────────────────
step "Repository"
sudo mkdir -p "$APP_DIR"
sudo chown "$(id -un):$(id -gn)" "$APP_DIR"
if [ -d "$APP_DIR/.git" ]; then
    skip "$APP_DIR ist bereits ein Git-Repo"
else
    if ! git ls-remote "$REPO_URL" >/dev/null 2>&1; then
        [ -f ~/.ssh/id_ed25519 ] || ssh-keygen -t ed25519 -N '' -C "$(id -un)@$(hostname)" -f ~/.ssh/id_ed25519 >/dev/null
        warn "GitHub kennt diesen Server für $REPO_URL noch nicht. Als Deploy-Key hinterlegen:"
        echo "    https://github.com/${REPO_URL#git@github.com:} → Settings → Deploy keys"
        sed 's/^/    /' ~/.ssh/id_ed25519.pub
        [ "$INTERACTIVE" = true ] && read -r -p "  Danach Enter drücken … " _ </dev/tty || die "Deploy-Key fehlt."
    fi
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
    ok "geklont"
fi
cd "$APP_DIR"

# ── 4. .env ─────────────────────────────────────────────────────────
step "Konfiguration"
set_env() {
    python3 - "$1" "$2" <<'PY'
import re, sys
key, value = sys.argv[1], sys.argv[2]
lines = open('.env').read().splitlines()
rendered = f'{key}="{value}"' if (value == '' or re.search(r'[\s"#\'$]', value)) else f'{key}={value}'
for i, line in enumerate(lines):
    if re.match(rf'^\s*#?\s*{re.escape(key)}=', line):
        lines[i] = rendered
        break
else:
    lines.append(rendered)
open('.env', 'w').write('\n'.join(lines) + '\n')
PY
}
get_env() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '"'; }
rand() { openssl rand -hex "$1"; }

APP_URL="https://$DOMAIN"
[ "$WITH_TLS" = false ] && APP_URL="http://$DOMAIN"

if [ -f .env ]; then
    skip ".env existiert — nur fehlende Schlüssel werden ergänzt"
else
    cp .env.example .env
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "$APP_URL"
    set_env LOG_STACK daily
    set_env LOG_LEVEL info
    set_env DB_CONNECTION pgsql
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 5432
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env SESSION_SECURE_COOKIE "$([ "$WITH_TLS" = true ] && echo true || echo false)"
    set_env QUEUE_CONNECTION database
    set_env CACHE_STORE database
    set_env BROADCAST_CONNECTION reverb
    # server -> Reverb intern, Browser -> nginx (443) -> Reverb
    set_env REVERB_HOST 127.0.0.1
    set_env REVERB_PORT "$REVERB_PORT"
    set_env REVERB_SCHEME http
    set_env REVERB_SERVER_HOST 127.0.0.1
    set_env REVERB_SERVER_PORT "$REVERB_PORT"
    set_env REVERB_PUBLIC_HOST "$DOMAIN"
    set_env REVERB_PUBLIC_PORT "$([ "$WITH_TLS" = true ] && echo 443 || echo 80)"
    set_env REVERB_PUBLIC_SCHEME "$([ "$WITH_TLS" = true ] && echo https || echo http)"
    set_env REVERB_ALLOWED_ORIGINS "$DOMAIN"
    chmod 640 .env
    ok ".env geschrieben"
fi
[ "$DB_PASSWORD_KNOWN" = true ] && set_env DB_PASSWORD "$DB_PASSWORD"
grep -qE '^APP_KEY=base64:' .env || { set_env APP_KEY "base64:$(openssl rand -base64 32)"; ok "APP_KEY erzeugt"; }
[ -n "$(get_env REVERB_APP_ID)" ] || { set_env REVERB_APP_ID "$((RANDOM * 100 + RANDOM))"; set_env REVERB_APP_KEY "$(rand 10)"; set_env REVERB_APP_SECRET "$(rand 20)"; ok "Reverb-Schlüssel erzeugt"; }
[ -n "$(get_env WHATSAPP_VERIFY_TOKEN)" ] || set_env WHATSAPP_VERIFY_TOKEN "$(rand 16)"

cat > .deploy.conf <<CONF
# Von install.sh erzeugt, von deploy.sh gelesen.
APP_DIR="$APP_DIR"
REPO_URL="$REPO_URL"
BRANCH="$BRANCH"
WEB_USER="$WEB_USER"
PHP_BIN="php$PHP_VERSION"
SUPERVISOR_PREFIX="$SUPERVISOR_PREFIX"
CONF
ok ".deploy.conf geschrieben"

# ── 5. nginx ────────────────────────────────────────────────────────
step "nginx"
NGINX_SITE="/etc/nginx/sites-available/$DOMAIN"
if [ -f "$NGINX_SITE" ]; then
    skip "vHost existiert — bleibt unverändert"
else
    sudo tee "$NGINX_SITE" >/dev/null <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $APP_DIR/public;

    index index.php;
    charset utf-8;
    client_max_body_size 8m;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/javascript application/json image/svg+xml;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Reverb (WebSocket): Queue-Tablets, Display, Statusseiten
    location ~ ^/(app|apps)/ {
        proxy_pass http://127.0.0.1:$REVERB_PORT;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 120s;
    }

    location ^~ /build/ {
        add_header Cache-Control "public, max-age=31536000, immutable" always;
        access_log off;
        try_files \$uri =404;
    }

    # Service Worker nie cachen, sonst bleiben Tablets auf alter Fassung
    location = /sw.js {
        add_header Cache-Control "no-cache" always;
        try_files \$uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php$PHP_VERSION-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60s;
    }

    # Versteckte Dateien dicht, außer .well-known (ACME, Apple Pay)
    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /var/log/nginx/$DOMAIN.access.log;
    error_log  /var/log/nginx/$DOMAIN.error.log;
}
NGINX
    ok "vHost angelegt"
fi
sudo ln -sfn "$NGINX_SITE" "/etc/nginx/sites-enabled/$DOMAIN"
sudo nginx -t >/dev/null 2>&1 || die "nginx-Konfiguration fehlerhaft — 'sudo nginx -t' zeigt Details."
sudo systemctl reload nginx
ok "nginx neu geladen"

# ── 6. App bauen ────────────────────────────────────────────────────
step "App bauen (deploy.sh)"
bash "$APP_DIR/deploy.sh" --no-pull

# VAPID braucht vendor/, deshalb erst nach dem Build
if [ -z "$(get_env VAPID_PUBLIC_KEY)" ]; then
    KEYS=$("php$PHP_VERSION" artisan pronto:vapid)
    set_env VAPID_PUBLIC_KEY "$(echo "$KEYS" | sed -n 's/^VAPID_PUBLIC_KEY=//p')"
    set_env VAPID_PRIVATE_KEY "$(echo "$KEYS" | sed -n 's/^VAPID_PRIVATE_KEY=//p')"
    "php$PHP_VERSION" artisan config:cache >/dev/null
    ok "VAPID-Schlüssel erzeugt"
fi

# ── 7. Supervisor ───────────────────────────────────────────────────
step "Supervisor (Queue-Worker, Reverb)"
sudo tee "/etc/supervisor/conf.d/${SUPERVISOR_PREFIX}-worker.conf" >/dev/null <<CONF
[program:${SUPERVISOR_PREFIX}-worker]
process_name=%(program_name)s_%(process_num)02d
command=php$PHP_VERSION $APP_DIR/artisan queue:work --sleep=1 --tries=3 --max-time=3600
directory=$APP_DIR
autostart=true
autorestart=true
user=$WEB_USER
numprocs=1
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/worker.log
stopwaitsecs=60
CONF
sudo tee "/etc/supervisor/conf.d/${SUPERVISOR_PREFIX}-reverb.conf" >/dev/null <<CONF
[program:${SUPERVISOR_PREFIX}-reverb]
command=php$PHP_VERSION $APP_DIR/artisan reverb:start --host=127.0.0.1 --port=$REVERB_PORT
directory=$APP_DIR
autostart=true
autorestart=true
user=$WEB_USER
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/reverb.log
stopwaitsecs=10
minfds=10000
CONF
sudo supervisorctl reread >/dev/null
sudo supervisorctl update >/dev/null
ok "$(sudo supervisorctl status | grep "^${SUPERVISOR_PREFIX}" | awk '{print $1" "$2}' | tr '\n' ' ')"

# ── 8. Scheduler ────────────────────────────────────────────────────
step "Scheduler (Verfall, Datenlöschung)"
CRON_FILE="/etc/cron.d/${SUPERVISOR_PREFIX}-scheduler"
echo "* * * * * $WEB_USER cd $APP_DIR && php$PHP_VERSION artisan schedule:run >> /dev/null 2>&1" | sudo tee "$CRON_FILE" >/dev/null
sudo chmod 644 "$CRON_FILE"
ok "$CRON_FILE"

# ── 9. Admin ────────────────────────────────────────────────────────
step "Admin-Zugang"
RESULT=$("php$PHP_VERSION" artisan pronto:user "$ADMIN_EMAIL" --name="$ADMIN_NAME" --admin | tail -n 1)
case "$RESULT" in
    EXISTS)    skip "$ADMIN_EMAIL existiert" ;;
    CREATED\ *)
        # not on screen: terminals and logs keep it forever
        umask 077
        printf '%s\n%s\n' "$ADMIN_EMAIL" "${RESULT#CREATED }" > "$HOME/pronto-admin.txt"
        ok "Zugang $ADMIN_EMAIL angelegt, Passwort in ~/pronto-admin.txt (nach dem Notieren löschen)" ;;
    *)         warn "Zugang ließ sich nicht anlegen: php artisan pronto:user $ADMIN_EMAIL --admin" ;;
esac

# ── 10. Zertifikat ──────────────────────────────────────────────────
step "Zertifikat"
if [ "$WITH_TLS" = false ]; then
    skip "übersprungen (--no-tls)"
elif sudo test -d "/etc/letsencrypt/live/$DOMAIN"; then
    skip "Zertifikat existiert"
else
    command -v certbot >/dev/null || sudo apt-get install -y -qq certbot python3-certbot-nginx >/dev/null
    if sudo certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$TLS_EMAIL" --redirect >/dev/null 2>&1; then
        ok "Zertifikat ausgestellt, HTTP leitet auf HTTPS um"
    else
        warn "certbot ist gescheitert (DNS?). Später: sudo certbot --nginx -d $DOMAIN"
    fi
fi

echo
echo "✅ Pronto läuft unter $APP_URL"
echo "   Noch offen (README, Abschnitt 'Produktion'): Stripe-Keys + Webhook, Apple-Pay-Domain,"
echo "   BUSINESS_* für den Beleg, optional WhatsApp/SMS. Danach: bash deploy.sh"
