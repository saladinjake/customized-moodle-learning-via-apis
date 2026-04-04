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

# Pre-check for initial values (for logging)
if [ -n "$DATABASE_URL" ]; then
    # Improved extraction for postgres://user:pass@host:port/db
    DB_P_HOST=$(echo "$DATABASE_URL" | sed -E 's/.*@([^:\/]+).*/\1/')
    DB_P_PORT=$(echo "$DATABASE_URL" | sed -E 's/.*:([0-9]+)\/.*/\1/' | grep -E '^[0-9]+$' || echo "5432")
    echo "[Entrypoint] Target host detected from URL: $DB_P_HOST:$DB_P_PORT"
else
    DB_P_HOST="${DB_HOST:-localhost}"
    DB_P_PORT="${DB_PORT:-5432}"
    echo "[Entrypoint] Target host detected from env: $DB_P_HOST:$DB_P_PORT"
fi

# Primary network check using pg_isready (installed in Dockerfile)
echo "[Entrypoint] Probing network availability with pg_isready..."
MAX_NETWORK_RETRIES=10
for i in $(seq 1 $MAX_NETWORK_RETRIES); do
  if pg_isready -h "$DB_P_HOST" -p "$DB_P_PORT" -t 5; then
    echo "[Entrypoint] Network path to database is OPEN."
    break
  fi
  echo "[Entrypoint] pg_isready: server at $DB_P_HOST:$DB_P_PORT not responding (attempt $i/$MAX_NETWORK_RETRIES)..."
  if [ "$i" -eq "$MAX_NETWORK_RETRIES" ]; then
    echo "[Entrypoint] ERROR: Database network path unreachable. Check Render private network / Region settings."
    # We continue to the PHP check anyway to get the full error report
  fi
  sleep 3
done

echo "[Entrypoint] Proceeding to credential and SSL handshake..."
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
  // Remove '@' to show connection errors and add 'sslmode=require'
  \$con_string = \"host='\$host' port='\$port' dbname='\$db' user='\$user' password='\$pass' connect_timeout=3 sslmode=require\";
  \$conn = pg_connect(\$con_string);
  exit(\$conn ? 0 : 1);
"; do
  RETRY=$((RETRY + 1))
  if [ "$RETRY" -ge "$MAX_RETRIES" ]; then
    echo "[Entrypoint] ERROR: Database unreachable after $MAX_RETRIES attempts. Aborting."
    exit 1
  fi
  echo "[Entrypoint] DB not ready yet (attempt $RETRY/$MAX_RETRIES)..."
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
