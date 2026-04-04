#!/bin/bash
set -e

echo "[Entrypoint] Initializing headless Moodle..."

# Ensure moodledata directory exists at runtime (tmpfs /tmp is wiped between builds and boots)
MOODLE_DATA="${MOODLE_DATA_DIR:-/tmp/moodle_data}"
echo "[Entrypoint] Ensuring dataroot exists at $MOODLE_DATA..."
mkdir -p "$MOODLE_DATA"
chmod 777 "$MOODLE_DATA"

# Give the DB a few seconds to map if this is a fresh start
sleep 5

echo "[Entrypoint] Running database seeders..."

# Run seed scripts only if they exist and are accessible

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
