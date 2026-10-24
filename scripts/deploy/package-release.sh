#!/usr/bin/env bash
#
# Runs IN CI (GitHub runner, where rsync exists). Packs the built app into a
# tarball for upload. The cPanel server has no rsync, so the release is sent as
# one tar stream over SSH and unpacked there.
#
#   bash scripts/deploy/package-release.sh <output.tgz>

set -euo pipefail

OUT="${1:?output tarball path required}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

rsync -a --exclude-from=scripts/deploy/release-exclude.txt ./ "$STAGE/"
tar -C "$STAGE" -czf "$OUT" .

echo "Packed $(du -h "$OUT" | cut -f1) into $OUT"
