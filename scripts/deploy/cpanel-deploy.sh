#!/usr/bin/env bash
#
# Runs ON THE SERVER when you click "Deploy HEAD Commit" in cPanel >
# Git Version Control (see .cpanel.yml). The cPanel clone tracks the
# deploy-build branch, which GitHub Actions fills with a ready-built release
# (vendor/ and public/build/ included, since the server has no Node).
#
# Copies that clone into a new release folder and activates it.
# Optional overrides in ~/asuh-app/shared/deploy.conf:
#   APP_DIR=...  DEPLOY_PHP=...  DEPLOY_DOCROOT=...

set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

APP_DIR="${APP_DIR:-$HOME/asuh-app}"
if [ -f "$APP_DIR/shared/deploy.conf" ]; then
    # shellcheck disable=SC1091
    . "$APP_DIR/shared/deploy.conf"
fi

APP_DIR="${APP_DIR:-$HOME/asuh-app}"
PHP="${DEPLOY_PHP:-/opt/cpanel/ea-php83/root/usr/bin/php}"
DOCROOT="${DEPLOY_DOCROOT:-$HOME/public_html/asuh.galeriilmu.com.my}"

SHA="$(git -C "$REPO" rev-parse --short HEAD 2>/dev/null || echo manual)"
RELEASE="$(date +%Y%m%d%H%M%S)-$SHA"
TARGET="$APP_DIR/releases/$RELEASE"

echo "Deploying $SHA from $REPO to $TARGET"

mkdir -p "$TARGET"
# tar instead of rsync (not installed on this server); the cPanel clone's .git stays behind.
# -m stamps every file with the current time: OPcache decides whether to
# recompile by mtime, and unchanged files (public/index.php especially) would
# otherwise keep their old mtime and keep serving the previous release.
tar -C "$REPO" --exclude=./.git -cf - . | tar -C "$TARGET" -xmf -

bash "$TARGET/scripts/deploy/activate-release.sh" "$APP_DIR" "$RELEASE" "$PHP" "$DOCROOT"
