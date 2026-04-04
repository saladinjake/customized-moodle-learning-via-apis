#!/bin/bash
set -e

echo "[Entrypoint] Initializing headless Moodle..."

# Ensure moodledata dir exists and is writable
MOODLE_DATA="${MOODLE_DATA_DIR:-/var/moodledata}"
echo "[Entrypoint] Ensuring dataroot at $MOODLE_DATA..."
mkdir -p "$MOODLE_DATA"
chmod 777 "$MOODLE_DATA"

# -------------------------------------------------------------------------
# STEP 1: Wait for the database to actually be reachable
# Retries every 3 seconds, up to 60 attempts (3 minutes max)
# -------------------------------------------------------------------------
echo "[Entrypoint] Waiting for database to be ready..."
MAX_RETRIES=60
RETRY=0

until php -r "
  \$url = getenv('DATABASE_URL');
  if (\$url) {
    \$p = parse_url(\$url);
    \$host = \$p['host'];
    \$port = \$p['port'] ?? 5432;
    \$db   = ltrim(\$p['path'], '/');
    \$user = urldecode(\$p['user']);
    \$pass = urldecode(\$p['pass']);
  } else {
    \$host = getenv('DB_HOST');
    \$port = getenv('DB_PORT') ?: 5432;
    \$db   = getenv('DB_NAME') ?: 'moodle';
    \$user = getenv('DB_USER');
    \$pass = getenv('DB_PASS');
  }
  \$conn = @pg_connect(\"host=\$host port=\$port dbname=\$db user=\$user password=\$pass connect_timeout=3\");
  exit(\$conn ? 0 : 1);
" 2>/dev/null; do
  RETRY=$((RETRY + 1))
  if [ "$RETRY" -ge "$MAX_RETRIES" ]; then
    echo "[Entrypoint] ERROR: Database unreachable after $MAX_RETRIES attempts. Aborting."
    exit 1
  fi
  echo "[Entrypoint] DB not ready yet (attempt $RETRY/$MAX_RETRIES)... retrying in 3s"
  sleep 3
done

echo "[Entrypoint] Database is ready!"

# -------------------------------------------------------------------------
# STEP 2: Install Moodle database schema (creates all mdl_* tables)
# Exits 0 on success, non-zero if already installed — both are fine.
# -------------------------------------------------------------------------
echo "[Entrypoint] Running Moodle database installer..."
ADMIN_PASS="${MOODLE_ADMIN_PASS:-Admin1234!}"

set +e  # allow installer to return non-zero without aborting script
php /var/www/html/admin/cli/install_database.php \
    --agree-license \
    --fullname="Lumina LMS" \
    --shortname="lumina" \
    --adminuser="admin" \
    --adminpass="$ADMIN_PASS" \
    --adminemail="admin@lumina.com"
INSTALL_EXIT=$?
set -e

if [ "$INSTALL_EXIT" -eq 0 ]; then
  echo "[Entrypoint] Moodle installed successfully."
else
  echo "[Entrypoint] Installer exited with code $INSTALL_EXIT (already installed or non-fatal — continuing)."
fi

# -------------------------------------------------------------------------
# STEP 3: Seed data (categories → cohorts → courses → grades/messages)
# -------------------------------------------------------------------------
echo "[Entrypoint] Running database seeders..."

if [ -f "/var/www/html/seed_categories.php" ]; then
    php /var/www/html/seed_categories.php || echo "Warn: seed_categories failed."
fi

if [ -f "/var/www/html/seed_cohorts.php" ]; then
    php /var/www/html/seed_cohorts.php || echo "Warn: seed_cohorts failed."
fi

if [ -f "/var/www/html/seed_moodle.php" ]; then
    php /var/www/html/seed_moodle.php || echo "Warn: seed_moodle failed."
fi

if [ -f "/var/www/html/seed_grades_messages.php" ]; then
    php /var/www/html/seed_grades_messages.php || echo "Warn: seed_grades_messages failed."
fi

echo "[Entrypoint] Seeding complete. Starting Apache..."
exec "$@"
