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
    DB_P_HOST="dpg-d78vuapr0fns73e6n420-a"
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
  \$url = getenv('DATABASE_URL') ?: 'postgresql://moodle_d4ws_user:fJrpXS36Yc2ynQmPUUC0zGMOLI5PA22b@dpg-d78vuapr0fns73e6n420-a/moodle_d4ws';
  if (\$url && (\$p = parse_url(\$url))) {
    \$host = \$p['host'] ?? 'dpg-d78vuapr0fns73e6n420-a';
    \$port = \$p['port'] ?? 5432;
    \$db   = ltrim(\$p['path'] ?? 'moodle_d4ws', '/');
    \$user = urldecode(\$p['user'] ?? 'moodle_d4ws_user');
    \$pass = urldecode(\$p['pass'] ?? 'fJrpXS36Yc2ynQmPUUC0zGMOLI5PA22b');
  } else {
    \$host = 'dpg-d78vuapr0fns73e6n420-a';
    \$port = 5432;
    \$db   = 'moodle_d4ws';
    \$user = 'moodle_d4ws_user';
    \$pass = 'fJrpXS36Yc2ynQmPUUC0zGMOLI5PA22b';
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
# STEP 2: Optional Install (Skip if database already exists)
# -------------------------------------------------------------------------
# Check if Moodle tables already exist specifically in mdl_config
echo "[Entrypoint] Checking if Moodle is already installed..."
ALREADY_INSTALLED=$(php -r "
  \$url = getenv('DATABASE_URL') ?: 'postgresql://moodle_d4ws_user:fJrpXS36Yc2ynQmPUUC0zGMOLI5PA22b@dpg-d78vuapr0fns73e6n420-a/moodle_d4ws';
  \$p = parse_url(\$url);
  \$host = \$p['host'];
  \$port = \$p['port'] ?? 5432;
  \$db   = ltrim(\$p['path'] ?? 'moodle_d4ws', '/');
  \$user = urldecode(\$p['user'] ?? 'moodle_d4ws_user');
  \$pass = urldecode(\$p['pass'] ?? 'fJrpXS36Yc2ynQmPUUC0zGMOLI5PA22b');
  
  \$conn = @pg_connect(\"host=\$host port=\$port dbname=\$db user=\$user password=\$pass connect_timeout=3 sslmode=require\");
  if (!\$conn) exit(0); // If can't connect yet, assume not installed
  \$res = @pg_query(\$conn, \"SELECT 1 FROM information_schema.tables WHERE table_name = 'mdl_config' LIMIT 1\");
  \$row = pg_fetch_row(\$res);
  exit(\$row ? 1 : 0);
"; echo $?)

if [ "$ALREADY_INSTALLED" -eq 1 ]; then
  echo "[Entrypoint] SKIPPING INSTALLER: Moodle database schema detected."
else
  echo "[Entrypoint] Running Moodle database installer (this may take up to 15 minutes)..."
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
    echo "[Entrypoint] Installer exited with code $INSTALL_EXIT (likely already installed — continuing)."
  fi
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
