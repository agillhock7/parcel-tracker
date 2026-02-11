#!/bin/bash
set -euo pipefail

echo "[deploy] starting"

if [[ ! -d public ]]; then
  echo "[deploy] ERROR: missing public/"
  exit 1
fi

home_dir="${HOME:-/home/ajgill}"
subdomain="tb4"
root_domain="alexander.quest"

DEPLOYPATH="${DEPLOYPATH:-}"

if [[ -z "$DEPLOYPATH" ]]; then
  # Common cPanel patterns. Prefer exact matches before globs.
  candidates=(
    "$home_dir/$subdomain"
    "$home_dir/public_html/$subdomain"
    "$home_dir/$subdomain.$root_domain"
    "$home_dir/public_html/$subdomain.$root_domain"
  )

  for p in "${candidates[@]}"; do
    if [[ -d "$p" ]]; then
      DEPLOYPATH="$p"
      break
    fi
  done
fi

if [[ -z "$DEPLOYPATH" ]]; then
  matches=()
  for p in "$home_dir/${subdomain}"* "$home_dir/public_html/${subdomain}"*; do
    [[ -d "$p" ]] && matches+=("$p")
  done

  if [[ ${#matches[@]} -eq 1 ]]; then
    DEPLOYPATH="${matches[0]}"
  else
    echo "[deploy] ERROR: deploy path not found."
    echo "[deploy] Checked common candidates under $home_dir."
    echo "[deploy] Found these possible matches:"
    if [[ ${#matches[@]} -eq 0 ]]; then
      echo "[deploy]   (none)"
    else
      for m in "${matches[@]}"; do
        echo "[deploy]   $m"
      done
    fi
    echo "[deploy] Fix: set the exact DocumentRoot path in this script (DEPLOYPATH candidates) or export DEPLOYPATH in .cpanel.yml."
    exit 1
  fi
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

commit_sha="$(git rev-parse --short=8 HEAD 2>/dev/null || true)"
if [[ -n "$commit_sha" ]]; then
  app_version="p-$commit_sha"
else
  app_version="p-$(date -u +%Y%m%d%H%M)"
fi
echo "$app_version" > "$DEPLOYPATH/storage/version.txt"
echo "[deploy] version=$app_version"

echo "[deploy] done"
