#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# deploy.sh  —  Push files to production via the local Suger Sync API
#
# Usage:
#   ./deploy.sh                        # push all new build assets
#   ./deploy.sh build                  # npm run build + push all assets
#   ./deploy.sh file path/to/file.php  # push a single specific file
#   ./deploy.sh files file1 file2 ...  # push multiple specific files
#
# Requirements: the local dev server must be running (php artisan serve)
# ─────────────────────────────────────────────────────────────────────────────

KEY="32e697230b3a0d42ed0e204048f68b369ad3c39d19f6e8d193f6d48a8fb1b14f"
BASE="http://127.0.0.1:8000/sync/api"
PASS=0
FAIL=0

# URL-encode a string
urlencode() {
    python3 -c "import urllib.parse, sys; print(urllib.parse.quote(sys.argv[1]))" "$1"
}

# Push a single file to prod via the local sync API
push_file() {
    local file="$1"
    if [ ! -f "$file" ]; then
        echo "  ✗ NOT FOUND: $file"
        ((FAIL++))
        return
    fi
    local encoded
    encoded=$(urlencode "$file")
    printf "  Pushing %-60s " "$file..."
    local result
    result=$(curl -s --max-time 30 "${BASE}/filepush?key=${KEY}&file=${encoded}")
    if echo "$result" | python3 -c "import sys,json; d=json.load(sys.stdin); sys.exit(0 if d.get('status')=='ok' else 1)" 2>/dev/null; then
        local size
        size=$(echo "$result" | python3 -c "import sys,json; d=json.load(sys.stdin); print(f\"{d.get('size',0)//1024}KB\")" 2>/dev/null)
        echo "✓ ${size}"
        ((PASS++))
    else
        local err
        err=$(echo "$result" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('error',repr(d))[:80])" 2>/dev/null || echo "$result")
        echo "✗ ${err}"
        ((FAIL++))
    fi
}

# ── Commands ──────────────────────────────────────────────────────────────────

cmd="${1:-assets}"

case "$cmd" in
    build)
        echo "→ Building assets..."
        npm --prefix . run build
        echo ""
        echo "→ Pushing all build assets to prod..."
        push_file "public/build/manifest.json"
        for f in public/build/assets/*.js public/build/assets/*.css; do
            push_file "$f"
        done
        ;;

    file)
        shift
        echo "→ Pushing file(s) to prod..."
        for f in "$@"; do
            push_file "$f"
        done
        ;;

    files)
        shift
        echo "→ Pushing files to prod..."
        for f in "$@"; do
            push_file "$f"
        done
        ;;

    assets|*)
        echo "→ Pushing all build assets to prod..."
        push_file "public/build/manifest.json"
        for f in public/build/assets/*.js public/build/assets/*.css; do
            push_file "$f"
        done
        ;;
esac

echo ""
echo "─────────────────────────────────"
echo "  Done: ${PASS} succeeded, ${FAIL} failed"
echo "─────────────────────────────────"
