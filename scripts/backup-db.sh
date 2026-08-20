#!/usr/bin/env bash
# HELAL CORP — MySQL dump (gzipped .sql.gz). Intended for cron on the VPS.
# Does not print the database password.
set -eu
set -o pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

umask 077

ENV_FILE=""
if [ -f /opt/magdyHelalCorporation/.env ]; then
  ENV_FILE=/opt/magdyHelalCorporation/.env
elif [ -f "$REPO_DIR/.env" ]; then
  ENV_FILE="$REPO_DIR/.env"
fi

if [ -n "$ENV_FILE" ]; then
  set -a
  # shellcheck disable=SC1090
  . "$ENV_FILE"
  set +a
fi

MYSQL_DATABASE="${MYSQL_DATABASE:-magdi_hilal}"
MYSQL_USER="${MYSQL_USER:-magdi}"
: "${MYSQL_PASSWORD:?MYSQL_PASSWORD is not set (put it in .env, never in this script)}"

PRIMARY_DIR=/var/backups/helal-mysql
if mkdir -p "$PRIMARY_DIR" 2>/dev/null && [ -w "$PRIMARY_DIR" ]; then
  BACKUP_DIR="$PRIMARY_DIR"
  chmod 700 "$BACKUP_DIR" 2>/dev/null || true
else
  BACKUP_DIR="$REPO_DIR/backups/mysql"
  mkdir -p "$BACKUP_DIR"
  chmod 700 "$BACKUP_DIR"
fi

LOG=/var/log/helal-mysql-backup.log
if { [ -e "$LOG" ] && [ -w "$LOG" ]; } || { [ ! -e "$LOG" ] && [ -w /var/log ]; }; then
  :
else
  LOG="$BACKUP_DIR/backup.log"
fi

log() {
  printf '%s %s\n' "$(date -Is)" "$*" >>"$LOG" 2>/dev/null || true
}

fail() {
  log "ERROR: $*"
  echo "backup-db: $*" >&2
  exit 1
}

cd "$REPO_DIR"

COMPOSE_FILE="$REPO_DIR/docker-compose.server.yml"
if [ -f "$COMPOSE_FILE" ]; then
  compose() { docker compose -f "$COMPOSE_FILE" "$@"; }
else
  compose() { docker compose "$@"; }
fi

command -v docker >/dev/null 2>&1 || fail "docker not found"
command -v gzip >/dev/null 2>&1 || fail "gzip not found"

STAMP="$(TZ=Africa/Cairo date +%Y-%m-%d_%H%M)"
OUT="$BACKUP_DIR/helal-${STAMP}.sql.gz"
TMP="$OUT.tmp"

# MYSQL_PWD is read by mysqldump inside the container; it is never echoed.
if ! compose exec -T -e MYSQL_PWD="$MYSQL_PASSWORD" db \
  mysqldump \
    -u"$MYSQL_USER" \
    --single-transaction \
    --routines \
    --triggers \
    --no-tablespaces \
    --default-character-set=utf8mb4 \
    "$MYSQL_DATABASE" \
  | gzip -9 >"$TMP"
then
  rm -f "$TMP"
  fail "mysqldump failed for database ${MYSQL_DATABASE}"
fi

bytes="$(wc -c <"$TMP")"
if [ ! -s "$TMP" ] || [ "$bytes" -lt 64 ]; then
  rm -f "$TMP"
  fail "dump file is empty or too small"
fi

mv "$TMP" "$OUT"
chmod 600 "$OUT"
log "OK wrote $OUT ($(wc -c <"$OUT") bytes)"

# Keep the newest 6 dumps (~3 months at the 1st and 16th).
KEEP=6
mapfile -t dumps < <(find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'helal-*.sql.gz' -o -name 'helal-*.sql' \) -printf '%T@\t%p\n' | sort -nr | cut -f2-)
if [ "${#dumps[@]}" -gt "$KEEP" ]; then
  for stale in "${dumps[@]:KEEP}"; do
    rm -f "$stale"
    log "removed old dump $stale"
  done
fi
