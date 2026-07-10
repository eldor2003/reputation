#!/bin/sh
set -e

cd /var/www/html

wait_for_postgres() {
    echo "Waiting for PostgreSQL..."
    until php -r "
        try {
            new PDO(
                'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD')
            );
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        sleep 2
    done
    echo "PostgreSQL is ready."
}

wait_for_redis() {
    echo "Waiting for Redis..."
    until php -r "
        try {
            \$redis = new Redis();
            \$redis->connect(getenv('REDIS_HOST'), (int) getenv('REDIS_PORT'));
            exit(0);
        } catch (Throwable \$e) {
            exit(1);
        }
    " 2>/dev/null; do
        sleep 2
    done
    echo "Redis is ready."
}

bootstrap_application() {
    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    composer install --no-interaction --prefer-dist

    php artisan optimize:clear --no-interaction

    if ! grep -q '^APP_KEY=' .env; then
        echo 'APP_KEY=' >> .env
    fi

    if ! grep -qE '^APP_KEY=base64:[A-Za-z0-9+/=]+$' .env; then
        sed -i '/^APP_KEY=/d' .env
        echo 'APP_KEY=' >> .env
        php artisan key:generate --force --no-interaction
    fi

    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache

    php artisan migrate --force --no-interaction
}

if [ ! -f .env ]; then
    cp .env.example .env
fi

wait_for_postgres
wait_for_redis
bootstrap_application

case "${SERVICE_MODE:-fpm}" in
    fpm)
        exec docker-php-entrypoint "$@"
        ;;
    horizon)
        exec php artisan horizon
        ;;
    scheduler)
        chmod +x /var/www/html/docker/scheduler/entrypoint.sh
        exec /var/www/html/docker/scheduler/entrypoint.sh
        ;;
    *)
        echo "Unknown SERVICE_MODE: ${SERVICE_MODE}"
        exit 1
        ;;
esac
