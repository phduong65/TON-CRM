#!/bin/bash
# Deploy script chạy TRÊN SERVER cPanel (gọi qua SSH từ GitHub Actions — xem .github/workflows/deploy.yml).
# Các bước: khoá chống chạy song song → backup DB → kéo code → composer → migrate → xoá/cache cấu hình.
# Bất kỳ bước nào lỗi (set -e) đều dừng ngay; backup DB lỗi thì KHÔNG migrate.
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/hr.toncapital.net/source}"
PHP_BIN="${PHP_BIN:-/usr/local/bin/ea-php83}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/hr-db}"
KEEP_BACKUPS="${KEEP_BACKUPS:-7}"
BRANCH="${BRANCH:-main}"
LOG_DIR="$HOME/deploy-logs"

mkdir -p "$LOG_DIR" "$BACKUP_DIR"

# Toàn bộ thân script nằm trong main() để vừa in ra màn hình (Actions) vừa ghi log file
# mà không cần process substitution (shell của hosting không có /dev/fd).
main() {
echo "=== Deploy bắt đầu $(date '+%F %T') ==="

# Chỉ cho 1 lần deploy chạy tại một thời điểm.
exec 9>"$HOME/.deploy-hr.lock"
if ! flock -n 9; then
    echo "Có lần deploy khác đang chạy — bỏ qua."
    exit 1
fi

cd "$APP_DIR"
[ -d .git ] || { echo "Thiếu thư mục .git trong $APP_DIR"; exit 1; }
[ -f .env ] || { echo "Thiếu file .env trong $APP_DIR"; exit 1; }

env_value() {
    # Đọc 1 khoá từ .env (bỏ dấu nháy bao ngoài), không in ra màn hình.
    grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'\$//"
}

# ── 1. Backup DB ────────────────────────────────────────────────────────────
DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"
DB_HOST="$(env_value DB_HOST)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(env_value DB_PORT)"; DB_PORT="${DB_PORT:-3306}"

CNF="$(umask 077 && mktemp)"
trap 'rm -f "$CNF"' EXIT
{
    echo "[client]"
    echo "user=$DB_USER"
    echo "password=$DB_PASS"
    echo "host=$DB_HOST"
    echo "port=$DB_PORT"
} > "$CNF"

BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"
echo "Backup DB -> $BACKUP_FILE"
mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --routines --no-tablespaces "$DB_NAME" | gzip > "$BACKUP_FILE"
[ -s "$BACKUP_FILE" ] || { echo "Backup DB rỗng — dừng deploy."; rm -f "$BACKUP_FILE"; exit 1; }
# Giữ N bản backup gần nhất.
ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -n +"$((KEEP_BACKUPS + 1))" | xargs -r rm -f

# ── 2. Kéo code ─────────────────────────────────────────────────────────────
PREV="$(git rev-parse --short HEAD 2>/dev/null || echo none)"
git fetch --prune origin "$BRANCH"
git reset --hard "origin/$BRANCH"
echo "Code: $PREV -> $(git rev-parse --short HEAD)"

# ── 3. Phụ thuộc PHP ────────────────────────────────────────────────────────
# Hosting chưa bật ext-sodium; chỉ cần cho JWT Ed25519, Firebase dùng RS256 nên bỏ qua kiểm tra này.
"$PHP_BIN" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction --prefer-dist --ignore-platform-req=ext-sodium

# ── 4. Migrate DB (bật maintenance trong lúc chạy, luôn bật lại site khi thoát) ──
"$PHP_BIN" artisan down --retry=30 || true
trap '"$PHP_BIN" artisan up || true; rm -f "$CNF"' EXIT
"$PHP_BIN" artisan migrate --force

# ── 5. Cache & queue ────────────────────────────────────────────────────────
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart || true

echo "=== Deploy xong $(date '+%F %T') ==="
}

main 2>&1 | tee -a "$LOG_DIR/deploy-$(date +%Y%m%d).log"
exit "${PIPESTATUS[0]}"
