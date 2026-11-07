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
#   scripts/deploy.sh /path/to/togetherincouncil --done      # after applying the config/migration
#                                                            # warnings by hand: stop repeating them
#
# vendor/ and composer.json/.lock are also left alone: Composer packages are
# installed in the live folder itself (see README).
#
# config/database.php and config/config.php are deliberately left alone on
# the live side: they hold the real production DB credentials and BASE_URL,
# not this repo's dev-placeholder values, and must never be overwritten by
# a sync from the repo.
#
# Because those two files are never synced and are edited by hand, every run
# first checks the live copies with `php -l` (a typo there takes the whole
# site down with a 500), then warns when the repo's copies have changed in
# ways the live copies may be missing:
#   - constants define()'d in the repo's config file but not the live one
#     (checked on every run);
#   - any change to config/*.php or new database/migration_*.sql files since
#     you last confirmed them with --done. These warnings repeat on every run
#     until you've applied them by hand and run --done (the confirmed commit
#     is recorded in .git/, so it's never synced or served). Set
#     DEPLOY_SINCE=<commit> on the first run to say which commit the live
#     site was last deployed from; it's remembered until --done.
#
# This script does not touch the database - after syncing, still check
# database/migration_*.sql for any new migrations that need running by hand
# against the live database (see CLAUDE.md).

set -euo pipefail

if [ -z "${1:-}" ]; then
    echo "Usage: $0 /path/to/togetherincouncil [--apply|--done]"
    exit 1
fi

LIVE_DIR="$1"
APPLY="${2:-}"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ ! -d "$LIVE_DIR" ]; then
    echo "Error: $LIVE_DIR does not exist"
    exit 1
fi

# Where the last commit whose config/migration changes you confirmed (--done)
# is recorded for this live dir
STATE_FILE="$(git -C "$REPO_DIR" rev-parse --git-dir 2>/dev/null || echo "$REPO_DIR/.git")"
case "$STATE_FILE" in /*) ;; *) STATE_FILE="$REPO_DIR/$STATE_FILE" ;; esac
STATE_FILE="$STATE_FILE/deploy-last-commit-$(printf '%s' "$(cd "$LIVE_DIR" && pwd)" | md5sum | cut -c1-12)"
HEAD_COMMIT="$(git -C "$REPO_DIR" rev-parse HEAD 2>/dev/null || true)"
CONFIG_FILES=(config/config.php config/database.php)
WARNINGS=0
PENDING=0   # warnings from the since-last-confirmed diff (cleared by --done)
LINT_ERRORS=0

warn() {
    WARNINGS=$((WARNINGS + 1))
    echo "  ! $*"
}

# Constants the repo defines that the live config doesn't
check_missing_defines() {
    local file="$1" name
    [ -f "$REPO_DIR/$file" ] || return 0
    if [ ! -f "$LIVE_DIR/$file" ]; then
        warn "$file does not exist on the live side - copy it from the repo and fill in the real values."
        return 0
    fi
    for name in $(grep -oE "define\(\s*['\"][A-Za-z0-9_]+['\"]" "$REPO_DIR/$file" | grep -oE "[A-Za-z0-9_]+['\"]\$" | tr -d "'\"" | sort -u); do
        if ! grep -qE "define\(\s*['\"]$name['\"]" "$LIVE_DIR/$file"; then
            warn "$file: live copy has no define('$name', ...) - the repo's does:"
            grep -nE "define\(\s*['\"]$name['\"]" "$REPO_DIR/$file" | sed 's/^/        repo line /'
        fi
    done
}

# PHP syntax check of a hand-edited live config file
check_live_syntax() {
    local file="$1" out
    [ -f "$LIVE_DIR/$file" ] || return 0
    if ! command -v php > /dev/null 2>&1; then
        echo "  (php not found on this machine - skipped the syntax check of live $file)"
        return 0
    fi
    if ! out="$(php -l "$LIVE_DIR/$file" 2>&1)"; then
        LINT_ERRORS=$((LINT_ERRORS + 1))
        warn "SYNTAX ERROR in live $file - every page returns 500 until it's fixed:"
        echo "$out" | grep -v '^Errors parsing' | sed 's/^/        /'
        echo "        (A missing ; or bracket is usually reported on the line AFTER the real mistake.)"
    fi
}

# Things this script never syncs, but which matter for security: the live
# .htaccess (it's excluded, so it must be copied by hand when the repo's
# changes) and leftover scripts that were removed from the repo but would
# still be web-reachable in the live docroot
check_live_hazards() {
    echo "Checking the live docroot for files this script doesn't manage ..."
    local before=$WARNINGS path
    if [ -f "$REPO_DIR/.htaccess" ]; then
        if [ ! -f "$LIVE_DIR/.htaccess" ]; then
            warn ".htaccess is missing on the live side - copy it from the repo (adjust RewriteBase if needed)."
        elif ! diff -q <(grep -v '^\s*RewriteBase' "$REPO_DIR/.htaccess") <(grep -v '^\s*RewriteBase' "$LIVE_DIR/.htaccess") > /dev/null; then
            warn ".htaccess differs from the repo's (ignoring RewriteBase). It is never synced - review and copy by hand:"
            diff -u <(grep -v '^\s*RewriteBase' "$LIVE_DIR/.htaccess") <(grep -v '^\s*RewriteBase' "$REPO_DIR/.htaccess") | tail -n +3 | sed 's/^/        /' || true
        fi
    fi
    for path in libs/index.php libs/tools api/qrcode.php; do
        if [ -e "$LIVE_DIR/$path" ]; then
            warn "$path exists in the live docroot but was removed from the repo (security fix) - delete it: rm -r '$LIVE_DIR/$path'"
        fi
    done
    if [ -f "$LIVE_DIR/database/fix_role_enum.php" ] && ! grep -q "php_sapi_name() !== 'cli'" "$LIVE_DIR/database/fix_role_enum.php"; then
        warn "database/fix_role_enum.php in the live docroot can be run by anyone over the web - delete it, or copy the repo's CLI-only version."
    fi
    if [ "$WARNINGS" -eq "$before" ]; then
        echo "  OK"
    fi
    echo
}

check_config() {
    echo "Checking the files this script never syncs (${CONFIG_FILES[*]}) ..."
    local file since="${DEPLOY_SINCE:-}" since_label
    for file in "${CONFIG_FILES[@]}"; do
        check_live_syntax "$file"
    done
    for file in "${CONFIG_FILES[@]}"; do
        check_missing_defines "$file"
    done

    if [ -n "$since" ]; then
        since_label="DEPLOY_SINCE=$since"
    elif [ -f "$STATE_FILE" ]; then
        since="$(cat "$STATE_FILE")"
        since_label="you last ran --done ($(git -C "$REPO_DIR" log -1 --format='%h %ad' --date=short "$since" 2>/dev/null || echo "${since:0:7}"))"
    fi

    if [ -z "$since" ]; then
        echo "  (No record of a previous --done from this checkout, so only missing"
        echo "   define()s were checked. To also see config changes since a known"
        echo "   commit, re-run with DEPLOY_SINCE=<commit>.)"
    elif ! git -C "$REPO_DIR" cat-file -e "$since^{commit}" 2>/dev/null; then
        warn "Can't compare with $since_label: commit $since isn't in this checkout."
    elif [ -n "$HEAD_COMMIT" ]; then
        for file in "${CONFIG_FILES[@]}"; do
            if ! git -C "$REPO_DIR" diff --quiet "$since" HEAD -- "$file"; then
                PENDING=$((PENDING + 1))
                warn "$file changed in the repo since $since_label. Apply these changes"
                echo "    to $LIVE_DIR/$file by hand, keeping its real credentials/values:"
                git -C "$REPO_DIR" --no-pager diff --no-color "$since" HEAD -- "$file" | sed 's/^/        /'
            fi
        done
        local migrations
        migrations="$(git -C "$REPO_DIR" diff --name-only --diff-filter=A "$since" HEAD -- 'database/migration_*.sql')"
        if [ -n "$migrations" ]; then
            PENDING=$((PENDING + 1))
            warn "New database migrations since $since_label - run each against the live database:"
            echo "$migrations" | sed 's/^/        /'
        fi
    fi

    if [ "$WARNINGS" -eq 0 ]; then
        echo "  OK - nothing to copy by hand."
    fi
    echo
}

RSYNC_ARGS=(
    -av
    --exclude='.git'
    --exclude='.gitignore'
    --exclude='config/database.php'
    --exclude='config/config.php'
    # Legacy local document uploads (pre-SharePoint) may still exist on the
    # live server; leave them alone - .htaccess keeps them unreachable.
    --exclude='uploads/'
    --exclude='libs/'
    --exclude='scripts/'
    --exclude='database/'
    --exclude='.github/'
    --exclude='*.md'
    --exclude='.htaccess'
    --exclude='word macro/'
    # Android app source - built separately, not part of the website
    --exclude='android/'
    # Composer packages (Anthropic SDK, TCPDF, FPDI...) are installed with
    # `composer require` in the live folder itself; never overwrite them
    # from whatever happens to be in this checkout.
    --exclude='vendor/'
    --exclude='composer.json'
    --exclude='composer.lock'
    # Backups/dumps that may be lying around in this checkout - in the
    # docroot they'd be downloadable by anyone
    --exclude='backups/'
    --exclude='backup/'
    --exclude='*.sql'
    --exclude='*.sql.gz'
    --exclude='*.sql.zip'
    --exclude='*.dump'
    --exclude='*.bak'
    --exclude='*.orig'
)

record_confirmed() {
    if [ -n "$HEAD_COMMIT" ]; then
        echo "$HEAD_COMMIT" > "$STATE_FILE"
    fi
}

# DEPLOY_SINCE with nothing recorded yet means "the live site is at this
# commit": save it so the warnings keep showing until --done
if [ -n "${DEPLOY_SINCE:-}" ] && [ ! -f "$STATE_FILE" ] \
    && git -C "$REPO_DIR" rev-parse --verify --quiet "$DEPLOY_SINCE^{commit}" > /dev/null; then
    git -C "$REPO_DIR" rev-parse "$DEPLOY_SINCE^{commit}" > "$STATE_FILE"
fi

if [ "$APPLY" = "--done" ]; then
    record_confirmed
    echo "Recorded $(git -C "$REPO_DIR" log -1 --format='%h' HEAD) as confirmed: config changes and"
    echo "migrations up to here won't be warned about again for $LIVE_DIR."
    exit 0
fi

check_config
check_live_hazards

# First run with nothing to apply by hand: start tracking from here
if [ ! -f "$STATE_FILE" ] && [ -z "${DEPLOY_SINCE:-}" ] && [ "$APPLY" = "--apply" ] && [ "$WARNINGS" -eq 0 ]; then
    record_confirmed
fi

if [ "$APPLY" = "--apply" ]; then
    echo "Syncing $REPO_DIR/ -> $LIVE_DIR/ ..."
    rsync "${RSYNC_ARGS[@]}" "$REPO_DIR/" "$LIVE_DIR/"
    # Nothing pending by hand: move the baseline forward automatically
    if [ -f "$STATE_FILE" ] && [ -z "${DEPLOY_SINCE:-}" ] && [ "$PENDING" -eq 0 ]; then
        record_confirmed
    fi
    echo
    echo "Done. Reminder: this does not run database migrations - check"
    echo "database/migration_*.sql for anything new that needs running by"
    echo "hand against the live database."
    if [ "$((WARNINGS - LINT_ERRORS))" -gt 0 ]; then
        echo
        echo "*** $((WARNINGS - LINT_ERRORS)) warning(s) above about config files or migrations. The live"
        echo "*** config files were NOT updated - scroll up and apply them by hand."
        if [ "$PENDING" -gt 0 ]; then
            echo "*** These will be shown on every run until you've done that and run:"
            echo "***   $0 $LIVE_DIR --done"
        fi
    fi
else
    echo "Dry run (nothing will be changed) - files that would be updated:"
    echo
    rsync "${RSYNC_ARGS[@]}" --dry-run "$REPO_DIR/" "$LIVE_DIR/"
    echo
    echo "Re-run with --apply to actually copy these files, e.g.:"
    echo "  $0 $LIVE_DIR --apply"
fi

if [ "$LINT_ERRORS" -gt 0 ]; then
    echo
    echo "*** $LINT_ERRORS live config file(s) have a PHP syntax error (see above)."
    echo "*** The site is down until they're fixed. Check again with: php -l <file>"
    exit 1
fi
