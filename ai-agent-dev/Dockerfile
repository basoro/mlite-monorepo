FROM node:20-bullseye-slim

# Install dependensi sistem yang dibutuhkan: Git, Redis, dan curl
RUN apt-get update && apt-get install -y \
    git \
    redis-server \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Setup working directory aplikasi
WORKDIR /app

# Copy package.json dan install dependency
COPY package*.json ./
RUN npm install --production

# Copy seluruh source code bot
COPY . .

# Buat direktori data untuk persistent storage (repo mlite dll)
RUN mkdir -p /data/mlite
VOLUME ["/data"]

# Beri hak eksekusi untuk entrypoint
RUN chmod +x entrypoint.sh

# Environment variables default
ENV REDIS_HOST=127.0.0.1
ENV REDIS_PORT=6379
ENV GIT_REPO_PATH=/data/mlite
ENV PORT=3000

EXPOSE $PORT

# Gunakan entrypoint script
ENTRYPOINT ["./entrypoint.sh"]
