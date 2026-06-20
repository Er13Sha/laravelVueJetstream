#!/usr/bin/env bash
set -e

echo "=== Сборка ==="
IMAGE_ID=$(docker build --target assets -q .)
CONTAINER_ID=$(docker create "$IMAGE_ID")
mkdir -p public/build
docker cp "$CONTAINER_ID:/app/public/build/." ./public/build/
docker rm "$CONTAINER_ID"
docker rmi "$IMAGE_ID"

echo "=== Сборка php-образа ==="
docker compose build php

echo "=== Запуск сервисов ==="
docker compose up -d db php nginx

echo ""
echo "✅ Готово: http://localhost:8088"
