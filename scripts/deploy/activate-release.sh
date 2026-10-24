#!/usr/bin/env bash
#
# Runs ON THE SERVER (called by .github/workflows/deploy.yml over SSH) after a
# new release has been uploaded to $APP_DIR/releases/$RELEASE.
#
#   bash activate-release.sh <app_dir> <release> [php_bin] [docroot]
#
# Layout (see docs/DEPLOY.md):
#   <app_dir>/releases/<release>/   uploaded code, vendor and built assets
#   <app_dir>/shared/.env           real secrets, created by hand, chmod 600
#   <app_dir>/shared/storage/       receipts, logs, cache (kept between releases)
#   <app_dir>/current -> releases/<release>
#   <docroot> -> <app_dir>/current/public   (public_html/asuh.galeriilmu.com.my)
#
# The code lives outside public_html, so .env and the source are never
# reachable from the web; only public/ is exposed through the symlink.

set -euo pipefail

APP_DIR="${1:?app dir required}"
RELEASE="${2:?release name required}"
PHP="${3:-php}"
DOCROOT="${4:-}"

REL="$APP_DIR/releases/$RELEASE"
SHARED="$APP_DIR/shared"

fail() { echo "DEPLOY ERROR: $*" >&2; exit 1; }

[ -d "$REL" ] || fail "release folder $REL not found"

"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
    || fail "$PHP is $("$PHP" -r 'echo PHP_VERSION;'); PHP 8.3+ is required. Set the DEPLOY_PHP variable (e.g. /opt/cpanel/ea-php83/root/usr/bin/php)."

# --- Shared files ------------------------------------------------------------

mkdir -p \
    "$SHARED/storage/app/private/receipts" \
    "$SHARED/storage/framework/cache/data" \
    "$SHARED/storage/framework/sessions" \
    "$SHARED/storage/framework/views" \
    "$SHARED/storage/logs"
chmod -R u+rwX,g+rwX "$SHARED/storage"

[ -f "$SHARED/.env" ] || fail "$SHARED/.env is missing. Create it from .env.production.example (docs/DEPLOY.md, step 6)."
chmod 600 "$SHARED/.env"

ln -sfn "$SHARED/.env" "$REL/.env"
rm -rf "$REL/storage"
ln -sfn "$SHARED/storage" "$REL/storage"
mkdir -p "$REL/bootstrap/cache"

# --- Document root (checked before anything changes) -------------------------

if [ -n "$DOCROOT" ]; then
    if [ -L "$DOCROOT" ]; then
        : # already a symlink; repointed below
    elif [ -d "$DOCROOT" ]; then
        # Only replace a folder cPanel created empty (it may hold cgi-bin or
        # .well-known from AutoSSL). Never touch a folder with real content.
        leftovers="$(find "$DOCROOT" -mindepth 1 -maxdepth 1 ! -name cgi-bin ! -name .well-known | head -n 1)"
        [ -z "$leftovers" ] || fail "$DOCROOT is not empty ($leftovers). Empty it by hand, then deploy again."
        mv "$DOCROOT" "$DOCROOT.cpanel-original-$(date +%Y%m%d%H%M%S)"
    elif [ -e "$DOCROOT" ]; then
        fail "$DOCROOT exists but is not a folder or symlink"
    fi
fi

# --- Application -------------------------------------------------------------

cd "$REL"

if grep -qE '^APP_KEY=[[:space:]]*$' "$SHARED/.env"; then
    echo "APP_KEY is empty: generating one (first deploy)."
    "$PHP" artisan key:generate --force
fi

"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class='Database\Seeders\PlanSeeder' --force
"$PHP" artisan optimize
"$PHP" artisan filament:optimize

# --- Switch traffic to the new release (atomic rename) ------------------------

ln -sfn "$REL" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$APP_DIR/current"

if [ -n "$DOCROOT" ]; then
    ln -sfn "$APP_DIR/current/public" "$DOCROOT"
fi

"$PHP" artisan queue:restart

# Keep the five newest releases for quick rollback.
find "$APP_DIR/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
    | sort -rn | tail -n +6 | cut -d' ' -f2- | xargs -r rm -rf

echo "Release $RELEASE is live."
