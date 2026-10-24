#!/usr/bin/env bash
#
# Run ON THE SERVER (cPanel Terminal) to switch back to the previous release:
#
#   bash ~/asuh-app/current/scripts/deploy/rollback.sh ~/asuh-app [php_bin]
#
# Database migrations are NOT reversed; Phase A migrations only add tables and
# columns, so older code keeps working against the newer schema.

set -euo pipefail

APP_DIR="${1:?app dir required}"
PHP="${2:-php}"

current="$(readlink -f "$APP_DIR/current")"
previous="$(find "$APP_DIR/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
    | sort -rn | cut -d' ' -f2- | grep -vxF "$current" | head -n 1 || true)"

[ -n "$previous" ] || { echo "No earlier release to roll back to." >&2; exit 1; }

# Fresh mtime so OPcache recompiles index.php for the release we switch to.
touch "$previous/public/index.php"

ln -sfn "$previous" "$APP_DIR/current.tmp"
mv -Tf "$APP_DIR/current.tmp" "$APP_DIR/current"

cd "$APP_DIR/current"
"$PHP" artisan optimize
"$PHP" artisan queue:restart

echo "Rolled back from $(basename "$current") to $(basename "$previous")."
