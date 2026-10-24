#!/usr/bin/env bash
#
# Runs IN CI. Commits a packed release (see package-release.sh) on top of the
# deploy-build branch, which the cPanel Git Version Control clone pulls.
#
#   GITHUB_TOKEN=... bash scripts/deploy/publish-build-branch.sh <release.tgz> [branch]
#
# Never force-pushes: cPanel's "Update from Remote" is a plain pull and would
# fail on rewritten history.

set -euo pipefail

TARBALL="${1:?release tarball required}"
BRANCH="${2:-deploy-build}"
: "${GITHUB_TOKEN:?GITHUB_TOKEN required}"
: "${GITHUB_REPOSITORY:?GITHUB_REPOSITORY required}"

REMOTE="https://x-access-token:${GITHUB_TOKEN}@github.com/${GITHUB_REPOSITORY}.git"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

if git ls-remote --exit-code --heads "$REMOTE" "$BRANCH" > /dev/null 2>&1; then
    git clone --quiet --depth 1 --branch "$BRANCH" "$REMOTE" "$WORK"
else
    git init --quiet "$WORK"
    git -C "$WORK" checkout --quiet -b "$BRANCH"
    git -C "$WORK" remote add origin "$REMOTE"
fi

# Replace the whole tree with the new release.
find "$WORK" -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf {} +
tar -xzf "$TARBALL" -C "$WORK"

cd "$WORK"
git config user.name "github-actions[bot]"
git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
# --force: vendor/ and public/build/ are git-ignored on main but must ship here.
git add --all --force

if git diff --cached --quiet; then
    echo "Nothing changed since the last build."
    exit 0
fi

git commit --quiet -m "Release ${GITHUB_SHA:0:7} dari main" -m "Dibina oleh GitHub Actions run ${GITHUB_RUN_ID:-local}. Jangan sunting cawangan ini secara manual."
git push --quiet origin "HEAD:$BRANCH"
echo "Published ${GITHUB_SHA:0:7} to $BRANCH."
