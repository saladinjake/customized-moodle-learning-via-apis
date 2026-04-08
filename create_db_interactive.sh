#!/bin/bash

# ==============================================================================
# Lumina Academy API Server - Interactive Database Creator
# This script prompts the user for all necessary credentials before creation.
# ==============================================================================

# Paths to PostgreSQL tools
CREATEDB="/Library/PostgreSQL/18/bin/createdb"

echo "✨ Moodle Database Initialization Helper"
echo "----------------------------------------"

# 1. Prompt for Database Name
read -p "Enter Database Name to create [moodle]: " DB_NAME
DB_NAME=${DB_NAME:-moodle}

# 2. Prompt for Database User
read -p "Enter PostgreSQL Username [postgres]: " DB_USER
DB_USER=${DB_USER:-postgres}

# 3. Prompt for Password (Secretly)
echo -n "Enter password for '$DB_USER': "
read -s DB_PASS
echo "" # New line after secret input

# 4. Confirmation
echo "⚠️  You are about to create a new database named '$DB_NAME' owned by '$DB_USER'."
read -p "Proceed? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Operation cancelled."
    exit 1
fi

export PGPASSWORD="$DB_PASS"

# 5. Create Database
echo "🐘 Connecting to PostgreSQL and creating '$DB_NAME'..."
"$CREATEDB" -U "$DB_USER" -h localhost -p 5432 "$DB_NAME"

if [ $? -eq 0 ]; then
    echo "✅ Success! Database '$DB_NAME' created and ready for use."
else
    echo "❌ Creation failed. Ensure the user '$DB_USER' has permission to create databases."
    exit 1
fi
