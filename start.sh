#!/bin/sh
# Start the outbox worker in the background
php -d extension=pdo_sqlite bin/nap worker &

# Start the web API in the foreground
php -d extension=pdo_sqlite -S 0.0.0.0:8000 -t public

