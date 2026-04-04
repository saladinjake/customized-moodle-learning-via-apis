#!/bin/bash
# 🚀 Headless Moodle Dev Launcher
# Run this from ANY directory — it always resolves to the correct project root.

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
PUBLIC_DIR="$ROOT_DIR/public"
FRONTEND_DIR="$ROOT_DIR/frontend"

echo "📍 Project root: $ROOT_DIR"
echo "📂 PHP document root: $PUBLIC_DIR"

# Kill any stale PHP server on port 8000
EXISTING=$(lsof -ti :8000)
if [ ! -z "$EXISTING" ]; then
    echo "⚠️  Killing existing process on port 8000 (PID: $EXISTING)..."
    kill $EXISTING
    sleep 1
fi

# Start PHP built-in server on the correct root
echo "🐘 Starting PHP server on localhost:8000..."
php -S localhost:8000 -t "$PUBLIC_DIR" &
PHP_PID=$!
echo "✅ PHP server started (PID: $PHP_PID)"

# Verify it's working
sleep 1
RESULT=$(curl -s "http://localhost:8000/local/api/index.php?action=ping")
if echo "$RESULT" | grep -q "pong\|status"; then
    echo "✅ API responding correctly!"
else
    echo "⚠️  API response: $RESULT"
fi

# Start Vite dev server
echo "⚡ Starting React frontend on localhost:5173..."
cd "$FRONTEND_DIR" && npm run dev

# Wait for PHP server on exit
wait $PHP_PID
