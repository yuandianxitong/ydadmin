#!/bin/sh
set -eu
ENV_FILE="${1:-.env}"
EXAMPLE="${2:-.env.example}"
DIR=$(dirname "$ENV_FILE")
if [ ! -f "$ENV_FILE" ]; then
  if [ ! -f "$EXAMPLE" ]; then
    echo "missing $EXAMPLE" >&2
    exit 1
  fi
  cp "$EXAMPLE" "$ENV_FILE"
fi
TMP=$(mktemp)
awk '
  BEGIN { db=0; rd=0; proxy=0 }
  $1=="DB_HOST" { print "DB_HOST = mysql"; db=1; next }
  $1=="REDIS_HOST" { print "REDIS_HOST = redis"; rd=1; next }
  $1=="TRUSTED_PROXIES" && ENVIRON["DOCKER_TRUSTED_PROXIES"]!="" {
    print "TRUSTED_PROXIES = " ENVIRON["DOCKER_TRUSTED_PROXIES"]; proxy=1; next
  }
  { print }
  END {
    if (!db) print "DB_HOST = mysql"
    if (!rd) print "REDIS_HOST = redis"
    if (!proxy && ENVIRON["DOCKER_TRUSTED_PROXIES"]!="") print "TRUSTED_PROXIES = " ENVIRON["DOCKER_TRUSTED_PROXIES"]
  }
' "$ENV_FILE" > "$TMP"
mv "$TMP" "$ENV_FILE"
