#!/usr/bin/env bash
# Run on production after DNS A records for tg-tech-m.dev point to 45.9.149.114
set -euo pipefail

cd /opt/reputation-project
DOMAIN=tg-tech-m.dev

echo "Checking DNS..."
RESOLVED=$(dig +short "$DOMAIN" A | tr '\n' ' ')
echo "tg-tech-m.dev resolves to: $RESOLVED"
if ! echo "$RESOLVED" | grep -q '45.9.149.114'; then
  echo "ERROR: $DOMAIN must resolve to 45.9.149.114 before continuing."
  echo "Ask client to set:  A  tg-tech-m.dev      -> 45.9.149.114"
  echo "                     A  www.tg-tech-m.dev  -> 45.9.149.114"
  exit 1
fi

sed -i 's|production-ssl.conf|production-acme.conf|' docker-compose.yml
docker compose up -d nginx
sleep 2

certbot certonly --webroot \
  -w /opt/reputation-project/public \
  -d tg-tech-m.dev \
  -d www.tg-tech-m.dev \
  --non-interactive --agree-tos \
  -m "admin@${DOMAIN}" \
  --preferred-challenges http

cp docker/nginx/production-ssl-domain.conf docker/nginx/production-ssl.conf
sed -i 's|production-acme.conf|production-ssl.conf|' docker-compose.yml
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env

docker compose up -d nginx app horizon scheduler
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan config:cache

curl -sf "https://${DOMAIN}/up" && echo "HTTPS OK"

source .env
curl -sS -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/setWebhook" \
  -H "Content-Type: application/json" \
  -d "{\"url\":\"https://${DOMAIN}/api/v1/telegram/webhook\",\"secret_token\":\"${TELEGRAM_WEBHOOK_SECRET}\",\"allowed_updates\":[\"callback_query\"]}"

echo "Done. Verify: curl https://${DOMAIN}/up"
