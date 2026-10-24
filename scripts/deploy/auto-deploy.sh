#!/usr/bin/env bash
#
# Runs ON THE SERVER from cron (every 5 minutes, see docs/DEPLOY.md):
#
#   bash ~/repositories/asuh-digital/scripts/deploy/auto-deploy.sh >> ~/asuh-app/deploy.log 2>&1
#
# Checks whether GitHub Actions published a new release on deploy-build and,
# if so, pulls and deploys it. Nothing reaches deploy-build without the
# owner's approval in chat and passing CI, so this only removes the manual
# "pull + Deploy HEAD Commit" clicks.
#
# A failed deploy is not retried automatically (the clone is already at the
# new commit); fix the cause, then deploy again from cPanel or wait for the
# next release.

# Everything lives in main() and runs from the last line: `git pull` below
# rewrites this very file, and bash must have read all of it beforehand.
main() {
    set -euo pipefail

    local repo="${AUTO_DEPLOY_REPO:-$HOME/repositories/asuh-digital}"
    local branch="${AUTO_DEPLOY_BRANCH:-deploy-build}"
    local lock="${AUTO_DEPLOY_LOCK:-$HOME/asuh-app/.auto-deploy.lock}"

    # One run at a time (cron fires again while a slow deploy is running).
    if ! mkdir "$lock" 2> /dev/null; then
        # Stale lock from a crashed run older than an hour: clear it.
        if [ -n "$(find "$lock" -maxdepth 0 -mmin +60 2> /dev/null)" ]; then
            rmdir "$lock"
            mkdir "$lock"
        else
            exit 0
        fi
    fi
    # Expand $lock now: the EXIT trap runs after main() returns, when its
    # local variables no longer exist (set -u would abort the cleanup).
    # shellcheck disable=SC2064
    trap "rmdir '$lock' 2> /dev/null || true" EXIT

    git -C "$repo" fetch --quiet origin "$branch"

    local current target
    current="$(git -C "$repo" rev-parse HEAD)"
    target="$(git -C "$repo" rev-parse "origin/$branch")"

    # Quiet when there is nothing to do, so the log only records deploys.
    [ "$current" != "$target" ] || exit 0

    echo "=== $(date '+%Y-%m-%d %H:%M:%S') release baharu: ${current:0:7} -> ${target:0:7}"
    git -C "$repo" merge --quiet --ff-only "origin/$branch"

    if bash "$repo/scripts/deploy/cpanel-deploy.sh"; then
        echo "=== $(date '+%Y-%m-%d %H:%M:%S') BERJAYA: ${target:0:7} kini live"
    else
        echo "=== $(date '+%Y-%m-%d %H:%M:%S') GAGAL: ${target:0:7}. Laman kekal pada release sebelumnya."
        return 1
    fi
}

main "$@"
