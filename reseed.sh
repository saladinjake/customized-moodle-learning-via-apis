#!/bin/bash
# ============================================================
# Lumina Moodle — Manual Reseed Script
# ============================================================
# Run this from Render's Shell console (or SSH) when the
# background seeder was cut off during deployment.
#
# Usage (from /var/www/html):
#   bash reseed.sh            # runs ALL seeders
#   bash reseed.sh categories # specific step only
#   bash reseed.sh cohorts
#   bash reseed.sh courses     # ← 500 courses (takes ~5–15 min)
#   bash reseed.sh grades
# ============================================================

set -euo pipefail

STEP="${1:-all}"
BASE_DIR="$(cd "$(dirname "$0")" && pwd)"

log() { echo "[RESEED $(date '+%H:%M:%S')] $*"; }

run_seeder() {
  local name="$1"
  local file="$BASE_DIR/$2"

  log "---- Starting: $name ----"

  if [ ! -f "$file" ]; then
    log "SKIP: $file not found."
    return
  fi

  # Clear any stale upgrade lock before each PHP seeder to be safe
  PGPASSWORD='83Ide1Yyu7Pg5l4T9f2YYbdO0tE81iti' psql \
    "host=dpg-d7922lk50q8c73f9u2m0-a.oregon-postgres.render.com port=5432 dbname=moodle_databases user=moodle_databases_user sslmode=require" \
    -c "DELETE FROM mdl_config WHERE name = 'upgraderunning';" 2>/dev/null \
    && log "Upgrade lock cleared." \
    || log "Warn: Could not clear lock (safe to ignore)."

  php "$file" 2>&1
  EXIT=$?

  if [ "$EXIT" -eq 0 ]; then
    log "OK: $name completed successfully."
  else
    log "WARN: $name exited with code $EXIT — check output above."
  fi

  log "---- Done: $name ----"
  echo ""
}

log "============================================"
log " LUMINA MANUAL RESEED — Step: $STEP"
log "============================================"
echo ""

case "$STEP" in
  all)
    run_seeder "Categories"     "seed_categories.php"
    run_seeder "Cohorts"        "seed_cohorts.php"
    run_seeder "Courses (500)"  "seed_moodle.php"
    run_seeder "Grades/Msgs"    "seed_grades_messages.php"
    ;;
  categories)
    run_seeder "Categories"     "seed_categories.php"
    ;;
  cohorts)
    run_seeder "Cohorts"        "seed_cohorts.php"
    ;;
  courses)
    run_seeder "Courses (500)"  "seed_moodle.php"
    ;;
  grades)
    run_seeder "Grades/Msgs"    "seed_grades_messages.php"
    ;;
  *)
    echo "ERROR: Unknown step '$STEP'. Valid: all | categories | cohorts | courses | grades"
    exit 1
    ;;
esac

log "============================================"
log " ALL DONE."
log "============================================"
