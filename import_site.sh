#!/bin/bash

# ==============================================================================
# Lumina Academy API Server - Import/Override Script
# ⚠️  WARNING: This script will OVERWRITE your current Moodle database!
# ==============================================================================

# 1. Credentials
DB_NAME="moodle"
DB_USER="postgres"
DB_PASS="saladin123"
DB_HOST="localhost"
DB_PORT="5432"

# Paths to PostgreSQL tools
PSQL="/Library/PostgreSQL/18/bin/psql"
DROPDB="/Library/PostgreSQL/18/bin/dropdb"
CREATEDB="/Library/PostgreSQL/18/bin/createdb"

# 2. Argument Validation
if [ -z "$1" ]; then
    echo " Usage: ./import_site.sh <path_to_sql_file>"
    echo "💡 Example: ./import_site.sh ./exports_20260406_194848/moodle_db_export.sql"
    exit 1
fi

SQL_FILE="$1"

if [ ! -f "$SQL_FILE" ]; then
    echo " Error: File not found: $SQL_FILE"
    exit 1
fi

echo "⚠️  This will PERMANENTLY ERASE the current '$DB_NAME' database and replace it with data from '$SQL_FILE'."
read -p "Are you sure you want to continue? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    exit 1
fi

export PGPASSWORD="$DB_PASS"

# 3. Drop and Re-create Database
echo "🗑️  Dropping existing database: $DB_NAME..."
"$DROPDB" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$DB_NAME" --if-exists

echo "✨ Creating fresh database: $DB_NAME..."
"$CREATEDB" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$DB_NAME"

# 4. Import SQL Dump
echo "🐘 Restoring schema and data from $SQL_FILE..."
echo "⏳ This may take a minute for 450+ tables..."

"$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -f "$SQL_FILE" > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "Restore command successful."
    
    # 5. Verification: Count tables to ensure data was actually imported
    TABLE_COUNT=$("$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';")
    
    # Trim whitespace
    TABLE_COUNT=$(echo $TABLE_COUNT | xargs)
    
    if [ "$TABLE_COUNT" -gt 0 ]; then
        echo " Database override complete!"
        echo " Statistics: $TABLE_COUNT tables successfully imported (including seeded data)."
        echo " Your seeded Moodle instance is now live at http://localhost:8000"
    else
        echo " Warning: Restore finished but 0 tables were found. The SQL file might be empty."
    fi
else
    echo " Import failed! Check the SQL file for corruption or PostgreSQL permissions."
    exit 1
fi

