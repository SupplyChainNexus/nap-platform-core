#!/bin/sh

echo "?? Launching 24/7 Catalog Scraper Agent in background..."
php -d extension=pdo_sqlite bin/worker-scraper-agent.php > /var/log/worker.log 2>&1 &

echo "?? Starting PHP Web Server / Application..."
exec "$@"
