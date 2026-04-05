#!/bin/bash
set -e

echo "[Entrypoint] Initializing headless Moodle..."

# Ensure moodledata dir exists and is writable
MOODLE_DATA="${MOODLE_DATA_DIR:-/var/moodledata}"
echo "[Entrypoint] Ensuring dataroot at $MOODLE_DATA..."
mkdir -p "$MOODLE_DATA"
chmod 777 "$MOODLE_DATA"

# -------------------------------------------------------------------------
# STEP 0: Load .env if present (Sync bash with app env)
# -------------------------------------------------------------------------
if [ -f "/var/www/html/.env" ]; then
  echo "[Entrypoint] Loading variables from .env..."
  export $(grep -v '^#' /var/www/html/.env | xargs)
fi

echo "[Entrypoint] --- Environment Audit ---"
echo "DATABASE_URL: $([ -n "$DATABASE_URL" ] && echo "PRESENT (masked)" || echo "MISSING")"
echo "DB_HOST: ${DB_HOST:-MISSING}"
echo "DB_USER: ${DB_USER:-MISSING}"
echo "RENDER_EXTERNAL_URL: ${RENDER_EXTERNAL_URL:-NOT SET}"
echo "---------------------------------------"

# -------------------------------------------------------------------------
# STEP 0b: Configure Apache to listen on Render's dynamic $PORT
# Render injects $PORT (usually 10000). Apache defaults to 80.
# Without this Render's health checker never sees an open port.
# -------------------------------------------------------------------------
APACHE_PORT="${PORT:-80}"
echo "[Entrypoint] Binding Apache to port $APACHE_PORT..."
# Override the Listen directive for the main apache config
echo "Listen $APACHE_PORT" > /etc/apache2/ports.conf
# Rewrite the VirtualHost port in the default site
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${APACHE_PORT}>/g" \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/sites-available/default-ssl.conf 2>/dev/null || true

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

# FORCED: Using new Oregon region credentials
DB_P_HOST="dpg-d791f8lactks73ctvgag-a"
DB_P_PORT="5432"

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
  \$host = 'dpg-d791f8lactks73ctvgag-a';
  \$port = 5432;
  \$db   = 'moodle_mnm7';
  \$user = 'moodle_mnm7_user';
  \$pass = 'wi0n2hFg025lR8V79TZGknzAjltcYcL1';
  
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
  \$host = 'dpg-d791f8lactks73ctvgag-a';
  \$port = 5432;
  \$db   = 'moodle_mnm7';
  \$user = 'moodle_mnm7_user';
  \$pass = 'wi0n2hFg025lR8V79TZGknzAjltcYcL1';
  
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
# STEP 3: Seed data in the BACKGROUND so Apache starts immediately.
# Render requires a port to be open within ~30s of container start.
# Running 500-course seeding synchronously blocks exec and kills the deploy.
# -------------------------------------------------------------------------
echo "[Entrypoint] Launching seeders in background. Starting Apache immediately..."

(
  echo "[Seeder] Background seeding started at $(date)."

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

  echo "[Seeder] Background seeding complete at $(date)."
) &

echo "[Entrypoint] Seeders running in background (PID $!). Starting Apache on port $APACHE_PORT..."
exec "$@"
