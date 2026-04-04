#!/bin/bash
set -e

echo "[Entrypoint] Initializing headless Moodle..."

# Ensure moodledata dir exists and is writable (safety net — already baked in Dockerfile)
MOODLE_DATA="${MOODLE_DATA_DIR:-/var/moodledata}"
echo "[Entrypoint] Ensuring dataroot exists at $MOODLE_DATA..."
mkdir -p "$MOODLE_DATA"
chmod 777 "$MOODLE_DATA"

# Give the DB a few seconds to be fully ready on first boot
sleep 10

# -------------------------------------------------------------------------
# STEP 1: Install Moodle database (creates all mdl_* tables)
# Only runs if the core config table doesn't exist yet (idempotent).
# -------------------------------------------------------------------------
echo "[Entrypoint] Checking if Moodle database is installed..."

ADMIN_PASS="${MOODLE_ADMIN_PASS:-Admin1234!}"
SITE_URL="${RENDER_EXTERNAL_URL:-http://localhost:8000}"

php /var/www/html/public/admin/cli/install_database.php \
    --agree-license \
    --fullname="Lumina LMS" \
    --shortname="lumina" \
    --adminuser="admin" \
    --adminpass="$ADMIN_PASS" \
    --adminemail="admin@lumina.com" \
    && echo "[Entrypoint] Moodle database installed successfully." \
    || echo "[Entrypoint] Warn: install_database returned non-zero (may already be installed — continuing)."

# -------------------------------------------------------------------------
# STEP 2: Seed data (categories → cohorts → courses → grades/messages)
# -------------------------------------------------------------------------
echo "[Entrypoint] Running database seeders..."

if [ -f "/var/www/html/seed_categories.php" ]; then
    php /var/www/html/seed_categories.php || echo "Warn: seed_categories failed or threw an error."
fi

if [ -f "/var/www/html/seed_cohorts.php" ]; then
    php /var/www/html/seed_cohorts.php || echo "Warn: seed_cohorts failed or threw an error."
fi

if [ -f "/var/www/html/seed_moodle.php" ]; then
    php /var/www/html/seed_moodle.php || echo "Warn: seed_moodle failed or threw an error."
fi

if [ -f "/var/www/html/seed_grades_messages.php" ]; then
    php /var/www/html/seed_grades_messages.php || echo "Warn: seed_grades_messages failed or threw an error."
fi

echo "[Entrypoint] Seeding complete. Starting Apache..."

# Execute the default CMD (apache2-foreground)
exec "$@"
