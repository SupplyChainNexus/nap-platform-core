#!/bin/sh

echo "===================================================="
echo "🤖 Launching 24/7 Catalog Scraper Agent in Background..."
echo "===================================================="

# Touch log file to ensure it exists
mkdir -p /var/log
touch /var/log/worker.log

# Launch background scraper worker
php -d extension=pdo_sqlite bin/worker-scraper-agent.php >> /var/log/worker.log 2>&1 &

echo "🌐 Starting Web Application Server..."
exec "$@"