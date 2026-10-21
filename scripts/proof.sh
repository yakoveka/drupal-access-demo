#!/usr/bin/env bash
set -euo pipefail

# Proves that node access is enforced on the node page but ignored by listings.

BASE=${BASE:-https://access-demo.ddev.site}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

ddev drush cr >/dev/null

NID=$(ddev drush sqlq "SELECT nid FROM node_field_data WHERE title LIKE 'Secret note A:%' LIMIT 1" | tr -d '[:space:]')
TID=$(ddev drush sqlq "SELECT tid FROM taxonomy_term_field_data WHERE vid = 'tags' AND name = 'billing' LIMIT 1" | tr -d '[:space:]')

if ! [[ "$NID" =~ ^[0-9]+$ ]] || ! [[ "$TID" =~ ^[0-9]+$ ]]; then
  echo "Missing demo content (node id: '${NID}', term id: '${TID}'): run scripts/seed.php first." >&2
  exit 2
fi

TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT

RESULTS=()
LEAKS=0
FAILS=0

PATH_BODY="$TMP_DIR/node.html"
if ! CODE=$(curl -sk -o "$PATH_BODY" -w '%{http_code}' "$BASE/node/$NID"); then
  CODE="000"
fi
if [[ "$CODE" == "403" ]]; then
  RESULTS+=("PASS|/node/$NID|(403 for anonymous)")
else
  RESULTS+=("FAIL|/node/$NID|(got $CODE)")
  FAILS=$((FAILS + 1))
fi

check_listing() {
  local path="$1"
  local body="$TMP_DIR/listing.html"
  local code visible

  if ! code=$(curl -sk -o "$body" -w '%{http_code}' "$BASE$path"); then
    code="000"
  fi

  if grep -qE 'Secret (note|invoice) [A-Z0-9]' "$body"; then
    visible=$(grep -oE 'Secret (note|invoice) [A-Z0-9]' "$body" | sort -u | wc -l | tr -d ' ')
    LEAKS=$((LEAKS + 1))
    RESULTS+=("LEAK|$path|($visible secret items visible)")
  else
    RESULTS+=("PASS|$path|($code)")
  fi
}

check_listing /rss.xml
check_listing "/search/node?keys=quasar"
check_listing "/taxonomy/term/$TID"

for path in "$@"; do
  check_listing "$path"
done

WIDTH=0
for result in "${RESULTS[@]}"; do
  path="${result#*|}"
  path="${path%%|*}"
  if (( ${#path} > WIDTH )); then
    WIDTH=${#path}
  fi
done

for result in "${RESULTS[@]}"; do
  status="${result%%|*}"
  rest="${result#*|}"
  path="${rest%%|*}"
  detail="${rest#*|}"
  printf '%-5s %-*s  %s\n' "$status" "$WIDTH" "$path" "$detail"
done

echo "Summary: $LEAKS leak(s), $FAILS fail(s)"

if (( FAILS > 0 )); then
  exit 1
fi
exit 0
