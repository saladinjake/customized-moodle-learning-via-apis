<?php
$filepath = 'entrypoint.sh';
$content = file_get_contents($filepath);

$new_content_start = <<<EOT
#!/bin/bash
set -e

echo "[Entrypoint] Initializing headless Moodle..."

# Ensure moodledata dir exists and is writable
MOODLE_DATA="\${MOODLE_DATA_DIR:-/var/moodledata}"
echo "[Entrypoint] Ensuring dataroot at \$MOODLE_DATA..."
mkdir -p "\$MOODLE_DATA"
chmod 777 "\$MOODLE_DATA"

# -------------------------------------------------------------------------
# STEP 0: Load .env if present (Sync bash with app env)
# -------------------------------------------------------------------------
if [ -f "/var/www/html/.env" ]; then
  echo "[Entrypoint] Loading variables from .env..."
  export \$(grep -v '^#' /var/www/html/.env | xargs)
fi

# Parse DATABASE_URL if present, otherwise use defaults
if [ -n "\$DATABASE_URL" ]; then
    PROTO="\$(echo "\$DATABASE_URL" | grep :// | sed -e's,^\(.*\://\).*,\1,g')"
    URL="\${DATABASE_URL/\$PROTO/}"
    USERPASS="\$(echo "\$URL" | grep @ | cut -d@ -f1)"
    if [ -n "\$USERPASS" ]; then
        export DB_P_PASS="\${USERPASS#*:}"
        export DB_P_USER="\${USERPASS%:*}"
    fi
    HOSTPORT="\$(echo "\${URL/\$USERPASS@/}" | cut -d/ -f1)"
    export DB_P_HOST="\${HOSTPORT%:*}"
    
    # Handle port extract correctly
    if [[ "\$HOSTPORT" == *":"* ]]; then
        export DB_P_PORT="\${HOSTPORT#*:}"
    else
        export DB_P_PORT="5432"
    fi
    
    DBPATH="\$(echo "\$URL" | grep / | cut -d/ -f2-)"
    export DB_P_NAME="\${DBPATH%%\?*}"
else
    export DB_P_HOST="\${DB_HOST:-localhost}"
    export DB_P_PORT="\${DB_PORT:-5432}"
    export DB_P_NAME="\${DB_NAME:-moodle}"
    export DB_P_USER="\${DB_USER:-postgres}"
    export DB_P_PASS="\${DB_PASS:-saladin123}"
fi

echo "[Entrypoint] --- Environment Audit ---"
echo "DATABASE_URL: \$([ -n "\$DATABASE_URL" ] && echo "PRESENT (masked)" || echo "MISSING")"
echo "DB_P_HOST: \${DB_P_HOST}"
echo "DB_P_USER: \${DB_P_USER}"
echo "DB_P_NAME: \${DB_P_NAME}"
echo "RENDER_EXTERNAL_URL: \${RENDER_EXTERNAL_URL:-NOT SET}"
echo "---------------------------------------"

# -------------------------------------------------------------------------
# STEP 0b: Configure Apache to listen on Render's dynamic \$PORT
# Render injects \$PORT (usually 10000). Apache defaults to 80.
# Without this Render's health checker never sees an open port.
# -------------------------------------------------------------------------
APACHE_PORT="\${PORT:-80}"
echo "[Entrypoint] Binding Apache to port \$APACHE_PORT..."
# Override the Listen directive for the main apache config
echo "Listen \$APACHE_PORT" > /etc/apache2/ports.conf
# Rewrite the VirtualHost port in the default site
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:\${APACHE_PORT}>/g" \\
    /etc/apache2/sites-available/000-default.conf \\
    /etc/apache2/sites-available/default-ssl.conf 2>/dev/null || true

# -------------------------------------------------------------------------
# STEP 1: Wait for the database to actually be reachable
# -------------------------------------------------------------------------
echo "[Entrypoint] Waiting for database to be ready..."
MAX_RETRIES=60
RETRY=0

echo "[Entrypoint] Probing network path to \$DB_P_HOST:\$DB_P_PORT..."
until pg_isready -h "\$DB_P_HOST" -p "\$DB_P_PORT" -t 5; do
  RETRY=\$((RETRY + 1))
  if [ "\$RETRY" -ge "\$MAX_RETRIES" ]; then
    echo "[Entrypoint] ERROR: Network path unreachable after \$MAX_RETRIES attempts."
    exit 1
  fi
  echo "[Entrypoint] Network path not open yet (attempt \$RETRY/\$MAX_RETRIES)..."
  sleep 3
done

echo "[Entrypoint] Network path is OPEN. Proceeding to credential handshake..."
RETRY=0
until php -r "
  \$host = getenv('DB_P_HOST');
  \$port = getenv('DB_P_PORT');
  \$db   = getenv('DB_P_NAME');
  \$user = getenv('DB_P_USER');
  \$pass = getenv('DB_P_PASS');
  
  \$con_string = \\"host='\$host' port='\$port' dbname='\$db' user='\$user' password='\$pass' connect_timeout=3 sslmode=require\\";
  \$conn = @pg_connect(\$con_string);
  // Fallback to strict DB check if first one fails
  if (!\$conn) {
     \$con_string = \\"host='\$host' port='\$port' dbname='\$db' user='\$user' password='\$pass' connect_timeout=3\\";
     \$conn = @pg_connect(\$con_string);
  }
  if (!\$conn) {
     fwrite(STDERR, \\"PHP Error: \\" . pg_last_error() . \\"\\\\n\\");
     exit(1);
  }
  exit(0);
"; do
  RETRY=\$((RETRY + 1))
  if [ "\$RETRY" -ge "\$MAX_RETRIES" ]; then
    echo "[Entrypoint] ERROR: Credential handshake failed after \$MAX_RETRIES attempts."
    exit 1
  fi
  echo "[Entrypoint] DB handshake failed (attempt \$RETRY/\$MAX_RETRIES)..."
  sleep 3
done

echo "[Entrypoint] Database is fully ready!"


# -------------------------------------------------------------------------
# STEP 2: Optional Install (Skip if database already exists)
# -------------------------------------------------------------------------
# Check if Moodle tables already exist specifically in mdl_config
echo "[Entrypoint] Checking if Moodle is already installed..."
ALREADY_INSTALLED=\$(php -r "
  \$host = getenv('DB_P_HOST');
  \$port = getenv('DB_P_PORT');
  \$db   = getenv('DB_P_NAME');
  \$user = getenv('DB_P_USER');
  \$pass = getenv('DB_P_PASS');
  
  \$conn = @pg_connect(\\"host=\$host port=\$port dbname=\$db user=\$user password=\$pass connect_timeout=3 sslmode=require\\");
  if (!\$conn) {
      \$conn = @pg_connect(\\"host=\$host port=\$port dbname=\$db user=\$user password=\$pass connect_timeout=3\\");
  }
  if (!\$conn) exit(0); // If can't connect yet, assume not installed
  \$res = @pg_query(\$conn, \\"SELECT 1 FROM information_schema.tables WHERE table_name = 'mdl_config' LIMIT 1\\");
  \$row = pg_fetch_row(\$res);
  exit(\$row ? 1 : 0);
"; echo \$?)

if [ "\$ALREADY_INSTALLED" -eq 1 ]; then
  echo "[Entrypoint] SKIPPING INSTALLER: Moodle database schema detected."
else
  echo "[Entrypoint] Running Moodle database installer (this may take up to 15 minutes)..."
  ADMIN_PASS="\${MOODLE_ADMIN_PASS:-Admin1234!}"
  
  set +e  # allow installer to return non-zero without aborting script
  php /var/www/html/admin/cli/install_database.php \\
      --agree-license \\
      --fullname=\\"Lumina LMS\\" \\
      --shortname=\\"lumina\\" \\
      --adminuser=\\"admin\\" \\
      --adminpass=\\"\$ADMIN_PASS\\" \\
      --adminemail=\\"admin@lumina.com\\"
  INSTALL_EXIT=\$?
  set -e
  
  if [ "\$INSTALL_EXIT" -eq 0 ]; then
    echo "[Entrypoint] Moodle installed successfully."
  else
    echo "[Entrypoint] Installer exited with code \$INSTALL_EXIT (likely already installed — continuing)."
  fi
fi

# -------------------------------------------------------------------------
# STEP 2b: Clear any stale Moodle upgrade lock
# -------------------------------------------------------------------------
echo "[Entrypoint] Clearing any stale Moodle upgrade lock..."
PGPASSWORD="\$DB_P_PASS" psql \\
  "host=\$DB_P_HOST port=\$DB_P_PORT dbname=\$DB_P_NAME user=\$DB_P_USER sslmode=require" \\
  -c "DELETE FROM mdl_config WHERE name = 'upgraderunning';" \\
  && echo "[Entrypoint] Upgrade lock cleared." \\
  || echo "[Entrypoint] Warn: Could not clear upgrade lock (table may not exist yet — safe to ignore on first install)."

# -------------------------------------------------------------------------
# STEP 3: Seed data in the BACKGROUND so Apache starts immediately.
# -------------------------------------------------------------------------
echo "[Entrypoint] Launching seeders in background. Starting Apache immediately..."

(
  echo "[Seeder] Background seeding started at \$(date)."

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

  echo "[Seeder] Background seeding complete at \$(date)."
) &

echo "[Entrypoint] Seeders running in background (PID \$!). Starting Apache on port \$APACHE_PORT..."
exec "\$@"
EOT;

file_put_contents($filepath, $new_content_start);
echo "entrypoint.sh updated successfully.";
