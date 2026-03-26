#!/bin/bash

echo "Starting Redis server in the background..."
redis-server --daemonize yes

echo "Ensuring Git Repo exists at $GIT_REPO_PATH..."
if [ ! -d "$GIT_REPO_PATH/.git" ]; then
    echo "⚠️  Warning: Direktori $GIT_REPO_PATH belum berupa repository Git."
    echo "    Memulai proses clone otomatis..."
    # Memastikan direktori kosong untuk di-clone ke dalamnya (meskipun Papuyu telah mounting foldernya)
    rm -rf "$GIT_REPO_PATH"/* "$GIT_REPO_PATH"/.* 2>/dev/null
    git clone https://github.com/basoro/mlite "$GIT_REPO_PATH"
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
