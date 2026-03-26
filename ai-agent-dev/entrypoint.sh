#!/bin/bash

echo "Starting Redis server in the background..."
redis-server --daemonize yes

echo "Ensuring Git Repo exists at $GIT_REPO_PATH..."
if [ ! -d "$GIT_REPO_PATH/.git" ]; then
    echo "⚠️  Warning: Direktori $GIT_REPO_PATH belum berupa repository Git."
    echo "    Harap mount volume repositori mlite ke /data/mlite atau lakukan git clone."
    # Opsional: Jika Anda ingin clone otomatis jika kosong, aktifkan baris di bawah:
    # git clone <url-repo-anda> $GIT_REPO_PATH
else
    echo "✅ Git Repo found!"
    # Konfigurasi Git Safe Directory
    git config --global --add safe.directory "$GIT_REPO_PATH"
fi

echo "Starting Worker Process..."
node worker.js &
WORKER_PID=$!

echo "Starting Telegram Bot Process..."
node index.js &
BOT_PID=$!

# Tangkap sinyal terminasi untuk mematikan proses dengan bersih
trap "kill -15 $WORKER_PID $BOT_PID; exit" SIGINT SIGTERM

# Tunggu proses selesai
wait $WORKER_PID
wait $BOT_PID
