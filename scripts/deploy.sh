#!/bin/bash
#
# Syncs this git checkout into the live docroot. Run this after every
# `git pull` instead of copying changed files across by hand - manual
# file-by-file copying is what caused a typo'd filename and a whole skipped
# file to silently break the live site in the past.
#
# Usage:
#   scripts/deploy.sh /path/to/togetherincouncil            # dry run, shows what would change
#   scripts/deploy.sh /path/to/togetherincouncil --apply     # actually copies the files
#
# config/database.php and config/config.php are deliberately left alone on
# the live side: they hold the real production DB credentials and BASE_URL,
# not this repo's dev-placeholder values, and must never be overwritten by
# a sync from the repo.
#
# This script does not touch the database - after syncing, still check
# database/migration_*.sql for any new migrations that need running by hand
# against the live database (see CLAUDE.md).

set -euo pipefail

if [ -z "${1:-}" ]; then
    echo "Usage: $0 /path/to/togetherincouncil [--apply]"
    exit 1
fi

LIVE_DIR="$1"
APPLY="${2:-}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ ! -d "$LIVE_DIR" ]; then
    echo "Error: $LIVE_DIR does not exist"
    exit 1
fi

RSYNC_ARGS=(
    -av
    --exclude='.git'
    --exclude='.gitignore'
    --exclude='config/database.php'
    --exclude='config/config.php'
    --exclude='uploads/'
)

if [ "$APPLY" = "--apply" ]; then
    echo "Syncing $REPO_DIR/ -> $LIVE_DIR/ ..."
    rsync "${RSYNC_ARGS[@]}" "$REPO_DIR/" "$LIVE_DIR/"
    echo
    echo "Done. Reminder: this does not run database migrations - check"
    echo "database/migration_*.sql for anything new that needs running by"
    echo "hand against the live database."
else
    echo "Dry run (nothing will be changed) - files that would be updated:"
    echo
    rsync "${RSYNC_ARGS[@]}" --dry-run "$REPO_DIR/" "$LIVE_DIR/"
    echo
    echo "Re-run with --apply to actually copy these files, e.g.:"
    echo "  $0 $LIVE_DIR --apply"
fi
