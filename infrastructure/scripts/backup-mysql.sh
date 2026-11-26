#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/regain}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
CONTAINER_NAME="${MYSQL_CONTAINER:-regain-mysql}"
DB_NAME="${DB_DATABASE:-regain}"
DB_USER="${DB_USERNAME:-regain_user}"
DB_PASS="${DB_PASSWORD:?DB_PASSWORD is required}"

mkdir -p "${BACKUP_DIR}"
TIMESTAMP=$(TZ=Asia/Jakarta date +"%Y%m%d_%H%M%S")
OUT_FILE="${BACKUP_DIR}/regain_${TIMESTAMP}.sql.gz"

docker exec "${CONTAINER_NAME}" mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  -u"${DB_USER}" \
  -p"${DB_PASS}" \
  "${DB_NAME}" | gzip > "${OUT_FILE}"

find "${BACKUP_DIR}" -type f -name "regain_*.sql.gz" -mtime +"${RETENTION_DAYS}" -delete

echo "Backup completed: ${OUT_FILE}"
