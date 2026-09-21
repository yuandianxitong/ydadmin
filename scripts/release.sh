#!/bin/sh
set -eu

LIST=0
SKIP_BUILD=0
SOURCE=""
ROOT=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)

while [ $# -gt 0 ]; do
  case "$1" in
    --list) LIST=1; shift ;;
    --skip-build) SKIP_BUILD=1; shift ;;
    --source) SOURCE=$2; shift 2 ;;
    *) echo "unknown arg: $1" >&2; exit 2 ;;
  esac
done

if [ -z "$SOURCE" ]; then
  SOURCE="$ROOT/server"
fi

VERSION=$(php -r 'echo (require $argv[1])["version"];' "$SOURCE/config/version.php")
PREFIX="ydadmin-${VERSION}/server"

copy_tree() {
  dest=$1
  mkdir -p "$dest"
  for name in app core config resource public scripts support start.php windows.php windows.bat webman composer.json composer.lock .env.example; do
    if [ -e "$SOURCE/$name" ]; then
      cp -R "$SOURCE/$name" "$dest/$name"
    fi
  done
  if [ -d "$SOURCE/database/install" ]; then
    mkdir -p "$dest/database"
    cp -R "$SOURCE/database/install" "$dest/database/install"
  fi
  if [ -d "$SOURCE/database/updates" ]; then
    mkdir -p "$dest/database"
    cp -R "$SOURCE/database/updates" "$dest/database/updates"
  fi
  # 丢掉误拷的生成物
  rm -rf "$dest/database/generated" "$dest/vendor" "$dest/tests" "$dest/runtime"
  find "$dest" -name '.env' -type f -delete
  find "$dest" -name '.env.*' -type f ! -name '.env.example' -delete
}

if [ "$SKIP_BUILD" -eq 0 ]; then
  (cd "$ROOT/admin" && pnpm build)
  (cd "$ROOT/pc" && pnpm generate)
fi

STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/$PREFIX"
copy_tree "$STAGE/$PREFIX"

if [ "$LIST" -eq 1 ]; then
  (cd "$STAGE" && find "ydadmin-${VERSION}" -type f | sort)
  exit 0
fi

mkdir -p "$ROOT/dist"
ZIP="$ROOT/dist/ydadmin-${VERSION}.zip"
rm -f "$ZIP"
(cd "$STAGE" && zip -qr "$ZIP" "ydadmin-${VERSION}")
echo "$ZIP"
