#!/usr/bin/env bash
#
# deploy.sh — Update von TIAMO Pronto (pronto.tiamo-pizza.de).
#
# Erkennt selbst, ob unter APP_DIR schon ein Git-Repo liegt:
#   nein → klont den Branch frisch (Erstinstallation ohne install.sh)
#   ja   → normales Update (fetch, reset, composer, npm, migrate, caches)
#
# Für einen frischen Server ist install.sh der richtige Weg - es richtet
# zusätzlich Datenbank, nginx inkl. Reverb-Proxy, Supervisor, Cron und TLS ein.
#
# Verwendung:
#   bash deploy.sh                — volles Update
#   bash deploy.sh --no-npm       — ohne Frontend-Build
#   bash deploy.sh --no-migrate   — ohne Migrationen
#   bash deploy.sh --no-pull      — ohne git pull (Code liegt schon da)
#
# Während des Markts: Tablets puffern Statuswechsel und senden nach, die
# Wartungsseite dauert meist unter einer Minute. Trotzdem lieber vorher.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[ -f "$SCRIPT_DIR/.deploy.conf" ] && . "$SCRIPT_DIR/.deploy.conf"

APP_DIR="${APP_DIR:-/var/www/pronto}"
REPO_URL="${REPO_URL:-git@github.com:tmerle24/pronto.git}"
BRANCH="${BRANCH:-main}"
WEB_USER="${WEB_USER:-www-data}"
PHP_BIN="${PHP_BIN:-php8.4}"
SUPERVISOR_PREFIX="${SUPERVISOR_PREFIX:-pronto}"

SKIP_NPM=false
SKIP_MIGRATE=false
NO_PULL="${NO_PULL:-false}"
for arg in "$@"; do
    case $arg in
        --no-npm)     SKIP_NPM=true ;;
        --no-migrate) SKIP_MIGRATE=true ;;
        --no-pull)    NO_PULL=true ;;
        -h|--help)    sed -n '2,20p' "$0"; exit 0 ;;
    esac
done

if [ ! -d "$APP_DIR/.git" ]; then
    echo "═══ Kein Git-Repo unter $APP_DIR — klone $BRANCH ═══"
    sudo mkdir -p "$APP_DIR"
    sudo chown "$(whoami):$(whoami)" "$APP_DIR"
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
    cd "$APP_DIR"
    if [ ! -f .env ]; then
        cp .env.example .env
        echo
        echo "⚠  STOPP: .env befüllen (oder install.sh benutzen), dann erneut ausführen."
        exit 0
    fi
    FIRST_INSTALL=true
else
    cd "$APP_DIR"
    FIRST_INSTALL=false
fi

artisan() { "$PHP_BIN" artisan "$@"; }

echo "═══ 1/9 Wartungsmodus AN ═══"
[ -d vendor ] && { artisan down --retry=15 || true; }
# Ab hier darf nichts mehr hart abbrechen, ohne den Wartungsmodus zu lösen.
trap '[ -d vendor ] && "$PHP_BIN" artisan up >/dev/null 2>&1 || true' EXIT

if [ "$FIRST_INSTALL" = false ] && [ "$NO_PULL" = false ]; then
    echo "═══ 2/9 Git Pull ($BRANCH) ═══"
    # bash liest das Skript stückweise: ändert der Pull es, mit neuer Fassung neu starten.
    DEPLOY_HASH=$(sha1sum "$APP_DIR/deploy.sh" | cut -d' ' -f1)
    git fetch origin "$BRANCH"
    git reset --hard "origin/$BRANCH"
    if [ -z "${DEPLOY_RESTARTED:-}" ] && [ "$(sha1sum "$APP_DIR/deploy.sh" | cut -d' ' -f1)" != "$DEPLOY_HASH" ]; then
        echo "  → deploy.sh wurde aktualisiert, starte mit der neuen Fassung neu"
        trap - EXIT
        DEPLOY_RESTARTED=1 exec bash "$APP_DIR/deploy.sh" "$@"
    fi
else
    echo "═══ 2/9 Kein Pull ═══"
fi

echo "═══ 3/9 Composer ═══"
# Bewusst bedingungslos (siehe mietordnung): sonst neuer Code mit altem vendor/.
"$PHP_BIN" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction

if [ "$SKIP_NPM" = false ]; then
    echo "═══ 4/9 Frontend-Build ═══"
    npm ci --no-audit --no-fund
    export NODE_OPTIONS="--max-old-space-size=3072"
    npm run build
else
    echo "═══ 4/9 Frontend-Build übersprungen ═══"
fi

if [ "$SKIP_MIGRATE" = false ]; then
    echo "═══ 5/9 Migrationen ═══"
    artisan migrate --force
else
    echo "═══ 5/9 Migrationen übersprungen ═══"
fi

echo "═══ 6/9 Startinhalte ═══"
# Legt nur an, was fehlt (Karte, erstes Event). Im Admin geänderte Werte bleiben.
artisan db:seed --force

echo "═══ 7/9 Caches ═══"
artisan config:clear
artisan config:cache
artisan route:cache
artisan view:clear
artisan view:cache
artisan event:cache
artisan filament:optimize

echo "═══ 8/9 Rechte ═══"
sudo chown -R "$(whoami):$WEB_USER" "$APP_DIR"
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} \;
sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \;
sudo chmod 640 "$APP_DIR/.env"

echo "═══ 9/9 Dienste neu laden ═══"
# Worker und Reverb laden den neuen Code nur nach einem Neustart.
sudo supervisorctl restart "${SUPERVISOR_PREFIX}-worker:*" 2>/dev/null \
    || echo "  → Kein Worker eingerichtet (install.sh), übersprungen"
sudo supervisorctl restart "${SUPERVISOR_PREFIX}-reverb" 2>/dev/null \
    || echo "  → Kein Reverb eingerichtet (install.sh), übersprungen"

# OPcache hält sonst den alten Bytecode inkl. gecachter Routen.
sudo systemctl reload "${PHP_BIN}-fpm" 2>/dev/null \
    && echo "  → ${PHP_BIN}-fpm neu geladen" \
    || echo "  ⚠  ${PHP_BIN}-fpm konnte nicht neu geladen werden"

trap - EXIT
artisan up

echo
echo "✅ Deploy fertig."
