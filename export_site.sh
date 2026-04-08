#!/bin/bash

# ==============================================================================
# Lumina Academy API Server - Export Script
# This script exports the current PostgreSQL database and the moodledata folder.
# ==============================================================================

# 1. Configuration (Updating paths to match your current local setup)
DB_NAME="moodle"
DB_USER="postgres"
DB_PASS="saladin123"
DB_HOST="localhost"
DB_PORT="5432"
PG_DUMP_PATH="/Library/PostgreSQL/18/bin/pg_dump" # Detected local path

# Moodle Data Directory (From your config.php)
MOODLE_DATA_DIR="/Users/test1/Downloads/moodle_data"
EXPORT_DIR="./exports_$(date +%Y%m%d_%H%M%S)"

echo "🚀 Starting export for Lumina Academy..."
mkdir -p "$EXPORT_DIR"

# 2. Exporting Database (Including all seeded courses and users)
echo "🐘 Exporting Database: $DB_NAME..."
export PGPASSWORD="$DB_PASS"
"$PG_DUMP_PATH" -U "$DB_USER" -h "$DB_HOST" -p "$DB_PORT" -d "$DB_NAME" -F p -v -f "$EXPORT_DIR/moodle_db_export.sql"

if [ $? -eq 0 ]; then
    echo "✅ Database export complete: $EXPORT_DIR/moodle_db_export.sql"
else
    echo "❌ Database export failed!"
    exit 1
fi

# 3. Archiving moodledata (Contains assets like uploaded files/images)
echo "📂 Archiving Moodle Data: $MOODLE_DATA_DIR..."
if [ -d "$MOODLE_DATA_DIR" ]; then
    zip -r "$EXPORT_DIR/moodledata_archive.zip" "$MOODLE_DATA_DIR" > /dev/null
    echo "✅ Moodle data archive complete: $EXPORT_DIR/moodledata_archive.zip"
else
    echo "⚠️  Moodle Data Directory not found at $MOODLE_DATA_DIR. Skipping."
fi

# 4. Summary
echo "----------------------------------------------------------------"
echo "🎉 Export finished successfully!"
echo "📍 Location: $EXPORT_DIR"
echo "Contents:"
echo "  - moodle_db_export.sql (Schema + Seeded Course Data)"
echo "  - moodledata_archive.zip (Assets/Videos/Files)"
echo "----------------------------------------------------------------"
