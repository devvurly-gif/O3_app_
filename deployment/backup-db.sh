#!/bin/bash
# Daily MySQL backup for O3_app.
#   - mysqldump every non-system DB (central + every tenant DB)
#   - gzip + per-DB file
#   - 14-day local retention
#   - safe if a DB is added/removed between runs (autodiscovers)
#
# Auth via /root/.my.cnf (created by setup-backup.sh from .env).
# Run by systemd unit o3-backup.service (timer fires daily at 03:30).
#
# Usage:
#   bash deployment/backup-db.sh             # normal run
#   bash deployment/backup-db.sh --dry-run   # list what would happen, don't write
set -euo pipefail

BACKUP_ROOT="/var/backups/o3_app"
RETENTION_DAYS=14
DRY_RUN=0
[ "${1:-}" = "--dry-run" ] && DRY_RUN=1

DATE="$(date +%F)"          # YYYY-MM-DD
TARGET="$BACKUP_ROOT/$DATE"
TS="$(date +%H%M%S)"        # in case of multiple runs same day

# 1. Discover non-system databases
mapfile -t DBS < <(mysql -BNe \
    "SELECT schema_name FROM information_schema.schemata
     WHERE schema_name NOT IN ('information_schema','performance_schema','mysql','sys')
     ORDER BY schema_name")

if [ ${#DBS[@]} -eq 0 ]; then
    echo "FATAL: no databases discovered (auth issue? check /root/.my.cnf)" >&2
    exit 1
fi

echo "[backup] $(date -Iseconds) — ${#DBS[@]} DB(s): ${DBS[*]}"
echo "[backup] target: $TARGET"

if [ $DRY_RUN -eq 1 ]; then
    echo "[backup] --dry-run — exiting before writing."
    exit 0
fi

mkdir -p "$TARGET"

# 2. Dump each DB
for db in "${DBS[@]}"; do
    out="$TARGET/${db}-${TS}.sql.gz"
    echo "[backup] dumping $db → $out"
    mysqldump \
        --single-transaction \
        --quick \
        --skip-lock-tables \
        --routines \
        --triggers \
        --events \
        --default-character-set=utf8mb4 \
        "$db" | gzip -9 > "$out"
done

# 3. Retention: drop date-folders older than $RETENTION_DAYS
echo "[backup] pruning folders older than $RETENTION_DAYS days under $BACKUP_ROOT"
find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime +$RETENTION_DAYS -print -exec rm -rf {} +

# 4. Summary
echo "[backup] done. Disk usage:"
du -sh "$BACKUP_ROOT" "$TARGET" 2>/dev/null || true
