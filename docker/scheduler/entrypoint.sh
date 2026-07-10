#!/bin/sh
set -e

echo "Starting Laravel scheduler..."

while true; do
    php artisan schedule:run --verbose --no-interaction
    sleep 60
done
