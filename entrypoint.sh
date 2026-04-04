#!/bin/bash
set -e

echo "[Entrypoint] Initializing headless Moodle..."

# Ensure moodledata dir exists and is writable
MOODLE_DATA="${MOODLE_DATA_DIR:-/var/moodledata}"
echo "[Entrypoint] Ensuring dataroot at $MOODLE_DATA..."
mkdir -p "$MOODLE_DATA"
chmod 777 "$MOODLE_DATA"

# -------------------------------------------------------------------------
# STEP 0: Environment Audit (Debugging)
# -------------------------------------------------------------------------
echo "[Entrypoint] --- Environment Audit ---"
echo "DATABASE_URL: $([ -n "$DATABASE_URL" ] && echo "PRESENT (masked)" || echo "MISSING")"
echo "DB_HOST: ${DB_HOST:-MISSING}"
echo "DB_USER: ${DB_USER:-MISSING}"
echo "RENDER_EXTERNAL_URL: ${RENDER_EXTERNAL_URL:-NOT SET}"
echo "---------------------------------------"

if [ -z "$DATABASE_URL" ] && [ -z "$DB_HOST" ]; then
  echo "[Entrypoint] ERROR: No database configuration found (DATABASE_URL and DB_HOST are empty)."
  echo "[Entrypoint] Please ensure the Render Blueprint has correctly linked the database."
  exit 1
fi

# -------------------------------------------------------------------------
# STEP 1: Wait for the database to actually be reachable
# -------------------------------------------------------------------------
echo "[Entrypoint] Waiting for database to be ready..."
MAX_RETRIES=60
RETRY=0

# Derive host and port for pg_isready network probe
if [ -n "$DATABASE_URL" ]; then
    # Extract host:port from postgres://user:pass@host:port/db
    DB_P_HOST=$(echo "$DATABASE_URL" | sed -E 's/.*@([^:\/]+).*/\1/')
    DB_P_PORT=$(echo "$DATABASE_URL" | sed -E 's/.*:([0-9]+)\/.*/\1/' | grep -E '^[0-9]+$' || echo "5432")
else
    DB_P_HOST="dpg-d78oelia214c73acn1gg-a"
    DB_P_PORT="5432"
fi

echo "[Entrypoint] Probing network path to $DB_P_HOST:$DB_P_PORT..."
until pg_isready -h "$DB_P_HOST" -p "$DB_P_PORT" -t 5; do
  RETRY=$((RETRY + 1))
  if [ "$RETRY" -ge "$MAX_RETRIES" ]; then
    echo "[Entrypoint] ERROR: Network path unreachable after $MAX_RETRIES attempts."
    exit 1
  fi
  echo "[Entrypoint] Network path not open yet (attempt $RETRY/$MAX_RETRIES)..."
  sleep 3
done

echo "[Entrypoint] Network path is OPEN. Proceeding to credential handshake..."
RETRY=0
until php -r "
  \$url = getenv('DATABASE_URL') ?: 'postgresql://moodle_db_user:6eymxyyd44m2qyOtdGn2hPzIidilc7Du@dpg-d78oelia214c73acn1gg-a/moodle_db_950m';
  if (\$url && (\$p = parse_url(\$url))) {
    \$host = \$p['host'] ?? 'dpg-d78oelia214c73acn1gg-a';
    \$port = \$p['port'] ?? 5432;
    \$db   = ltrim(\$p['path'] ?? 'moodle_db_950m', '/');
    \$user = urldecode(\$p['user'] ?? 'moodle_db_user');
    \$pass = urldecode(\$p['pass'] ?? '6eymxyyd44m2qyOtdGn2hPzIidilc7Du');
  } else {
    \$host = 'dpg-d78oelia214c73acn1gg-a';
    \$port = 5432;
    \$db   = 'moodle_db_950m';
    \$user = 'moodle_db_user';
    \$pass = '6eymxyyd44m2qyOtdGn2hPzIidilc7Du';
  }
  
  if (empty(\$host)) {
    fwrite(STDERR, \"PHP Error: Derived host is empty\n\");
    exit(1);
  }

  \$con_string = \"host='\$host' port='\$port' dbname='\$db' user='\$user' password='\$pass' connect_timeout=3 sslmode=require\";
  \$conn = pg_connect(\$con_string);
  if (!\$conn) {
     fwrite(STDERR, \"PHP Error: \" . pg_last_error() . \"\n\");
     exit(1);
  }
  exit(0);
"; do
  RETRY=$((RETRY + 1))
  if [ "$RETRY" -ge "$MAX_RETRIES" ]; then
    echo "[Entrypoint] ERROR: Credential handshake failed after $MAX_RETRIES attempts."
    exit 1
  fi
  echo "[Entrypoint] DB handshake failed (attempt $RETRY/$MAX_RETRIES)..."
  sleep 3
done

echo "[Entrypoint] Database is fully ready!"


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
