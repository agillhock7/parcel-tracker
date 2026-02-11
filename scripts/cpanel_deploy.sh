#!/bin/bash
set -euo pipefail

echo "[deploy] starting"

if [[ ! -d public ]]; then
  echo "[deploy] ERROR: missing public/"
  exit 1
fi

# Adjust these candidates if your subdomain DocumentRoot differs.
candidate1="/home/ajgill/tb4"
candidate2="/home/ajgill/public_html/tb4"

DEPLOYPATH=""
if [[ -d "$candidate1" ]]; then
  DEPLOYPATH="$candidate1"
elif [[ -d "$candidate2" ]]; then
  DEPLOYPATH="$candidate2"
else
  echo "[deploy] ERROR: deploy path not found."
  echo "[deploy] Expected one of:"
  echo "[deploy]   $candidate1"
  echo "[deploy]   $candidate2"
  exit 1
fi

echo "[deploy] DEPLOYPATH=$DEPLOYPATH"

/bin/mkdir -p "$DEPLOYPATH"

echo "[deploy] copying public/ -> webroot"
/bin/cp -a public/. "$DEPLOYPATH/"

# Current scaffold copies app code into the webroot and blocks access via .htaccess.
# If you later configure DocumentRoot to point to public/, remove these copies.
for d in src views sql config; do
  if [[ -d "$d" ]]; then
    echo "[deploy] copying $d/"
    /bin/cp -a "$d" "$DEPLOYPATH/"
  fi
done

echo "[deploy] ensuring storage/"
/bin/mkdir -p "$DEPLOYPATH/storage/logs"

echo "[deploy] done"

