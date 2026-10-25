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
REPLACE_DOCROOT_FOLDER=0

REL="$APP_DIR/releases/$RELEASE"
SHARED="$APP_DIR/shared"

fail() { echo "DEPLOY ERROR: $*" >&2; exit 1; }

[ -d "$REL" ] || fail "release folder $REL not found"

"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
    || fail "$PHP is $("$PHP" -r 'echo PHP_VERSION;'); PHP 8.3+ is required. Set the DEPLOY_PHP variable (e.g. /opt/cpanel/ea-php83/root/usr/bin/php)."

# The CLI binary can have different extensions from the web PHP; check the ones Laravel and Filament need.
missing="$("$PHP" -r '
    $need = ["bcmath", "ctype", "curl", "dom", "fileinfo", "intl", "mbstring", "openssl", "pdo_mysql", "tokenizer", "xml", "zip"];
    echo implode(" ", array_filter($need, fn ($ext) => ! extension_loaded($ext)));
')"
[ -z "$missing" ] || fail "PHP extensions missing for $PHP: $missing. Enable them in cPanel > Select PHP Version / MultiPHP INI Editor."

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

# --- cPanel-generated .htaccess blocks ---------------------------------------
#
# MultiPHP Manager (and the MultiPHP INI Editor) write blocks such as
#   # php -- BEGIN cPanel-generated handler, do not edit ... END ...
# into the docroot's .htaccess. That block is what makes the site run PHP 8.3
# instead of the server default (7.3). Once the docroot is a symlink, cPanel
# writes into current/public/.htaccess, which the next release would replace,
# so the blocks are carried forward into every new release.

CPANEL_BLOCKS="$SHARED/cpanel-generated.htaccess"
cpanel_blocks() { sed -n '/BEGIN cPanel-generated/,/END cPanel-generated/p' "$1"; }

if [ -n "$DOCROOT" ]; then
    for source in "$DOCROOT/.htaccess" "$APP_DIR/current/public/.htaccess"; do
        if [ -f "$source" ] && [ -n "$(cpanel_blocks "$source")" ]; then
            cpanel_blocks "$source" > "$CPANEL_BLOCKS"
            break
        fi
    done
fi

if [ -s "$CPANEL_BLOCKS" ]; then
    { cat "$CPANEL_BLOCKS"; echo; cat "$REL/public/.htaccess"; } > "$REL/public/.htaccess.new"
    mv "$REL/public/.htaccess.new" "$REL/public/.htaccess"
    echo "Carried cPanel-generated .htaccess blocks into the release."
fi

# The MultiPHP INI Editor may also write .user.ini / php.ini (upload limits and
# the like) into current/public. Carry them forward too; the live release is the
# source of truth, so a file removed in cPanel stays removed.
mkdir -p "$SHARED/cpanel-ini"
for ini in .user.ini php.ini; do
    if [ -f "$APP_DIR/current/public/$ini" ]; then
        cp "$APP_DIR/current/public/$ini" "$SHARED/cpanel-ini/$ini"
    elif [ -e "$APP_DIR/current" ]; then
        rm -f "$SHARED/cpanel-ini/$ini"
    fi
    if [ -f "$SHARED/cpanel-ini/$ini" ]; then
        cp "$SHARED/cpanel-ini/$ini" "$REL/public/$ini"
        echo "Carried cPanel $ini into the release."
    fi
done

# --- Document root (checked before anything changes) -------------------------

if [ -n "$DOCROOT" ]; then
    if [ -L "$DOCROOT" ]; then
        : # already a symlink; repointed below
    elif [ -d "$DOCROOT" ]; then
        # Only replace a folder cPanel created empty (it may hold cgi-bin,
        # .well-known from AutoSSL, and a .htaccess holding nothing but
        # cPanel-generated blocks). Never touch a folder with real content.
        leftovers="$(find "$DOCROOT" -mindepth 1 -maxdepth 1 ! -name cgi-bin ! -name .well-known ! -name .htaccess | head -n 1)"
        [ -z "$leftovers" ] || fail "$DOCROOT is not empty ($leftovers). Empty it by hand, then deploy again."

        if [ -f "$DOCROOT/.htaccess" ]; then
            own_rules="$(sed '/BEGIN cPanel-generated/,/END cPanel-generated/d' "$DOCROOT/.htaccess" | grep -vE '^[[:space:]]*$' || true)"
            [ -z "$own_rules" ] || fail "$DOCROOT/.htaccess has rules that are not cPanel-generated. Review it by hand, then deploy again."
        fi

        # Moved aside only at switch time below, so a failed migration never
        # leaves the subdomain without a document root.
        REPLACE_DOCROOT_FOLDER=1
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
# Book covers live on the public disk (shared storage); expose them as public/storage.
"$PHP" artisan storage:link --force

# --- Switch traffic to the new release (atomic rename) ------------------------

# The web server reaches the app through the docroot -> current symlink.
# With revalidate_path off, OPcache keys index.php by that unchanging path but
# checks freshness against the file it first compiled: the PREVIOUS release's
# index.php. Touching that file after the switch makes OPcache recompile
# index.php through the symlink, so __DIR__ points at the new release
# (verified on the live server, 2026-10-25).
previous_rel="$(readlink -f "$APP_DIR/current" 2> /dev/null || true)"
touch "$REL/public/index.php"

ln -sfn "$REL" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$APP_DIR/current"

if [ -n "$previous_rel" ] && [ -f "$previous_rel/public/index.php" ]; then
    touch "$previous_rel/public/index.php"
fi

if [ -n "$DOCROOT" ]; then
    if [ "${REPLACE_DOCROOT_FOLDER:-0}" = 1 ]; then
        mv "$DOCROOT" "$DOCROOT.cpanel-original-$(date +%Y%m%d%H%M%S)"
    fi
    ln -sfn "$APP_DIR/current/public" "$DOCROOT"
fi

"$PHP" artisan queue:restart

# Keep the five newest releases for quick rollback.
find "$APP_DIR/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
    | sort -rn | tail -n +6 | cut -d' ' -f2- | xargs -r rm -rf

echo "Release $RELEASE is live."
