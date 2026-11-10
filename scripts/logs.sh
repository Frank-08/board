#!/bin/bash
#
# View the app's PHP error log from the server's command line, for
# troubleshooting. Read-only: it never changes or rotates the log.
#
# Usage:
#   scripts/logs.sh                      # last 50 lines
#   scripts/logs.sh -n 200               # last 200 lines
#   scripts/logs.sh -f                   # follow new lines live (Ctrl+C to stop)
#   scripts/logs.sh -r 3fa9c01b          # find an "error reference" a user was shown
#   scripts/logs.sh -g 'password|2fa'    # lines matching a pattern (case-insensitive)
#   scripts/logs.sh -s                   # most frequent messages, timestamps stripped
#   scripts/logs.sh -L                   # show which log files were found
#
# Options can be combined, e.g. `-g Fatal -n 500` or `-f -g agenda`.
#
# Which log is read (first match wins):
#   1. -l /path/to/file, or $BOARD_ERROR_LOG
#   2. APP_ERROR_LOG in config/config.php of the live dir given with -d
#      (default: this checkout) - see README "Error log"
#   3. the vhost log from the README, then the usual Apache / PHP-FPM logs
#
# -r and -s also search the rotated copies (error.log.1, error.log.2.gz, ...).
# Web server logs are often only readable by root/adm: if the file is found
# but unreadable, re-run with sudo.

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_DIR="$REPO_DIR"
LOG_FILE="${BOARD_ERROR_LOG:-}"
LINES=50
FOLLOW=0
REF=""
PATTERN=""
SUMMARY=0
LIST=0

usage() {
    sed -n '2,/^set -euo/p' "${BASH_SOURCE[0]}" | sed '$d' | sed 's/^# \{0,1\}//'
    exit "${1:-0}"
}

while getopts ":n:fr:g:sl:d:Lh" opt; do
    case "$opt" in
        n) LINES="$OPTARG" ;;
        f) FOLLOW=1 ;;
        r) REF="$OPTARG" ;;
        g) PATTERN="$OPTARG" ;;
        s) SUMMARY=1 ;;
        l) LOG_FILE="$OPTARG" ;;
        d) APP_DIR="$OPTARG" ;;
        L) LIST=1 ;;
        h) usage 0 ;;
        :) echo "Option -$OPTARG needs a value" >&2; usage 1 ;;
        *) echo "Unknown option -$OPTARG" >&2; usage 1 ;;
    esac
done

case "$LINES" in ''|*[!0-9]*) echo "-n needs a number" >&2; exit 1 ;; esac

# APP_ERROR_LOG from config.php, if it is a plain string literal
configured_log() {
    local cfg="$APP_DIR/config/config.php"
    [ -f "$cfg" ] || return 0
    grep -E "^[[:space:]]*define\([[:space:]]*['\"]APP_ERROR_LOG['\"]" "$cfg" 2>/dev/null \
        | head -n1 \
        | sed -E "s/.*APP_ERROR_LOG['\"][[:space:]]*,[[:space:]]*['\"]([^'\"]*)['\"].*/\1/" \
        | grep -v '^[[:space:]]*define' || true
}

candidates() {
    [ -n "$LOG_FILE" ] && echo "$LOG_FILE"
    configured_log
    # PHP's own error_log setting (CLI ini - usually the same file as the web SAPI's)
    php -r 'echo ini_get("error_log");' 2>/dev/null && echo
    echo /var/log/apache2/board_error.log
    echo /var/log/httpd/board_error.log
    echo /var/log/httpd/board_error_log
    echo /var/log/apache2/error.log
    echo /var/log/httpd/error_log
    ls -1 /var/log/php*-fpm.log /var/log/php-fpm/*error.log 2>/dev/null || true
}

if [ "$LIST" = 1 ]; then
    candidates | awk 'NF && !seen[$0]++' | while read -r f; do
        if [ -r "$f" ]; then
            printf 'readable    %8s  %s\n' "$(du -h "$f" | cut -f1)" "$f"
        elif [ -e "$f" ]; then
            printf 'NO ACCESS   %8s  %s  (try sudo)\n' "-" "$f"
        else
            printf 'missing     %8s  %s\n' "-" "$f"
        fi
    done
    exit 0
fi

FILE=""
UNREADABLE=""
while read -r f; do
    [ -n "$f" ] || continue
    if [ -r "$f" ] && [ -f "$f" ]; then FILE="$f"; break; fi
    if [ -e "$f" ] && [ -z "$UNREADABLE" ]; then UNREADABLE="$f"; fi
done < <(candidates | awk 'NF && !seen[$0]++')

if [ -z "$FILE" ]; then
    if [ -n "$UNREADABLE" ]; then
        echo "Found $UNREADABLE but can't read it - re-run with sudo." >&2
    else
        echo "No error log found. Pass one with -l /path/to/error.log, or run -L to see where I looked." >&2
    fi
    exit 1
fi

echo "== $FILE ==" >&2

# The log plus its rotated copies, oldest first
all_rotations() {
    local f
    for f in $(ls -1v "$FILE".* 2>/dev/null | tac); do
        case "$f" in
            *.gz) zcat -f "$f" 2>/dev/null || true ;;
            *) cat "$f" 2>/dev/null || true ;;
        esac
    done
    cat "$FILE"
}

color() {
    if [ -t 1 ]; then
        grep --color=always -i -E "$1|$" || true
    else
        cat
    fi
}

if [ -n "$REF" ]; then
    case "$REF" in *[!0-9a-fA-F]*) echo "An error reference is hex, e.g. 3fa9c01b" >&2; exit 1 ;; esac
    MATCHES="$(all_rotations | grep -i -F "[$REF]" || true)"
    if [ -z "$MATCHES" ]; then
        echo "Reference $REF not found (it may be in an older rotated log that has been deleted)." >&2
        exit 1
    fi
    printf '%s\n' "$MATCHES" | color "$REF"
    exit 0
fi

if [ "$SUMMARY" = 1 ]; then
    # Strip leading [timestamp] [module] [pid] [client] groups, error refs,
    # Apache's ", referer: ..." suffix and numbers so repeats group together.
    all_rotations | { [ -n "$PATTERN" ] && grep -i -E "$PATTERN" || cat; } \
        | sed -E 's/^(\[[^]]*\][[:space:]]*)+//; s/\[[0-9a-f]{8}\][[:space:]]*//; s/, referer: .*$//; s/[0-9]+/N/g' \
        | sort | uniq -c | sort -rn | head -n "$LINES"
    exit 0
fi

if [ "$FOLLOW" = 1 ]; then
    if [ -n "$PATTERN" ]; then
        tail -n "$LINES" -F "$FILE" | grep --line-buffered -i -E "$PATTERN" | color "$PATTERN"
    else
        tail -n "$LINES" -F "$FILE" | color 'Fatal|\[[0-9a-f]{8}\]'
    fi
    exit 0
fi

if [ -n "$PATTERN" ]; then
    grep -i -E "$PATTERN" "$FILE" | tail -n "$LINES" | color "$PATTERN"
else
    tail -n "$LINES" "$FILE" | color 'Fatal|\[[0-9a-f]{8}\]'
fi
