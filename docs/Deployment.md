# Руководство по развёртыванию

## Требования

| Компонент | Версия |
|-----------|---------|
| Docker Engine | 24+ |
| Docker Compose | v2 |
| PHP (в контейнере) | 8.4 |
| PostgreSQL | 16 |
| Redis | 7 |

**Минимальные ресурсы сервера:** 2 vCPU, 2 GB RAM (Horizon и вызовы Claude API чувствительны к памяти).

Shared hosting без Redis, PostgreSQL и постоянных воркеров **не поддерживается**.

---

## Развёртывание в Docker

### Сервисы

| Контейнер | Роль |
|-----------|------|
| `reputation-app` | PHP-FPM приложение |
| `reputation-nginx` | Веб-сервер (порт 8080 по умолчанию) |
| `reputation-postgres` | PostgreSQL 16 |
| `reputation-redis` | Redis 7 |
| `reputation-horizon` | Queue worker (Horizon) |
| `reputation-scheduler` | Цикл Laravel scheduler |

### Быстрый старт

```bash
git clone <repository-url>
cd reputation-project
cp .env.example .env
# Отредактируйте .env — см. Required Variables ниже
docker compose up -d --build
```

При первом запуске контейнер `app` автоматически:

1. Ожидает PostgreSQL и Redis
2. Устанавливает зависимости Composer
3. Генерирует `APP_KEY`, если отсутствует
4. Выполняет migrations

URL приложения: `http://localhost:8080` (или настроенный `NGINX_PORT`).

Health check: `GET /up`

---

## Обязательные переменные `.env`

### Приложение

| Переменная | Обязательна | Описание |
|----------|----------|-------------|
| `APP_NAME` | Нет | Имя приложения |
| `APP_ENV` | Да | `production` для боевого развёртывания |
| `APP_KEY` | Да | Генерируется автоматически при первом запуске |
| `APP_DEBUG` | Да | В production должно быть `false` |
| `APP_URL` | Да | Публичный HTTPS URL (например, `https://reputation.example.com`) |

### База данных (значения по умолчанию для Docker)

| Переменная | По умолчанию | Описание |
|----------|---------|-------------|
| `DB_CONNECTION` | `pgsql` | Драйвер БД |
| `DB_HOST` | `postgres` | Имя Docker-сервиса |
| `DB_PORT` | `5432` | Порт PostgreSQL |
| `DB_DATABASE` | `reputation` | Имя базы данных |
| `DB_USERNAME` | `reputation` | Пользователь БД |
| `DB_PASSWORD` | `secret` | **Измените в production** |

### Redis / Queue

| Переменная | По умолчанию | Описание |
|----------|---------|-------------|
| `REDIS_HOST` | `redis` | Имя Docker-сервиса |
| `REDIS_PORT` | `6379` | Порт Redis |
| `QUEUE_CONNECTION` | `redis` | Должно быть `redis` |
| `CACHE_STORE` | `redis` | Драйвер кэша |
| `SESSION_DRIVER` | `redis` | Драйвер сессий |

### Ingest API

| Переменная | Обязательна | Описание |
|----------|----------|-------------|
| `INGEST_API_TOKEN` | Да | Bearer token для webhook-эндпоинтов |
| `INGEST_IDEMPOTENCY_LOCK_TTL` | Нет | TTL блокировки Redis в секундах (по умолчанию: 300) |

### Claude / Anthropic

| Переменная | Обязательна | Описание |
|----------|----------|-------------|
| `ANTHROPIC_API_KEY` | Да | API key Anthropic |
| `CLAUDE_MODEL` | Нет | ID модели (по умолчанию: `claude-sonnet-4-6`) |
| `CLAUDE_TEMPERATURE` | Нет | По умолчанию: `0` |
| `CLAUDE_MAX_TOKENS` | Нет | По умолчанию: `1024` |

### Telegram

| Переменная | Обязательна | Описание |
|----------|----------|-------------|
| `TELEGRAM_BOT_TOKEN` | Да | Bot token от BotFather |
| `TELEGRAM_CHAT_IDS` | Да | Chat ID через запятую |
| `TELEGRAM_CHAT_ID` | Нет | Устаревший одиночный chat ID (обратная совместимость) |
| `TELEGRAM_WEBHOOK_SECRET` | Да | Secret для аутентификации webhook |

### Brand24 (API client / verification)

| Переменная | Обязательна | Описание |
|----------|----------|-------------|
| `BRAND24_API_KEY` | Для API-команд | Brand24 API key |
| `BRAND24_BASE_URL` | Нет | По умолчанию: `https://api-data.brand24.com` |
| `BRAND24_ACCOUNT_ID` | Для API-команд | Brand24 account ID |

### Docker

| Переменная | По умолчанию | Описание |
|----------|---------|-------------|
| `NGINX_PORT` | `8080` | Host-порт, проброшенный на Nginx |

---

## Шаги production-развёртывания

### 1. Подготовка сервера

- Подготовьте VPS с установленным Docker
- Настройте DNS A-запись на IP сервера
- Откройте порты 80 и 443 (настройте reverse proxy или обновите Nginx)

### 2. Настройка окружения

```bash
cp .env.example .env
```

Установите production-значения:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://reputation.example.com

DB_PASSWORD=<strong-password>

INGEST_API_TOKEN=<random-secure-token>
ANTHROPIC_API_KEY=sk-ant-...
TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHAT_IDS=-1001234567890,987654321
TELEGRAM_WEBHOOK_SECRET=<random-secret>
```

### 3. Сборка и запуск

```bash
docker compose up -d --build
```

Проверьте контейнеры:

```bash
docker compose ps
```

Все шесть сервисов должны быть в статусе `Up`.

### 4. Проверка интеграций

```bash
docker compose exec app php artisan brand24:test
docker compose exec app php artisan claude:test
docker compose exec app php artisan telegram:test
docker compose exec app php artisan pipeline:verify-e2e
```

### 5. Настройка webhook

**YouScan / Brand24 ingest:**

```
POST https://reputation.example.com/api/v1/ingest/youscan
POST https://reputation.example.com/api/v1/ingest/brand24

Authorization: Bearer <INGEST_API_TOKEN>
```

**Telegram bot webhook:**

```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://reputation.example.com/api/v1/telegram/webhook",
    "secret_token": "<TELEGRAM_WEBHOOK_SECRET>"
  }'
```

### 6. Создание sources

Зарегистрируйте записи `Project` и `Source` в базе данных (через seeder, tinker или прямой SQL). Каждому source нужен UUID, на который ссылаются webhook payload как `source_uuid`.

Пример через tinker:

```bash
docker compose exec app php artisan tinker
```

### 7. SSL (рекомендуется)

Разместите reverse proxy (Caddy, Traefik или host Nginx) перед Docker-контейнером Nginx или расширьте `docker-compose.yml` SSL-terminating proxy.

---

## Horizon

Horizon работает в контейнере `reputation-horizon` (`SERVICE_MODE=horizon`).

- **Dashboard:** `https://your-domain/horizon`
- **Logs:** `docker compose logs -f horizon`
- **Configuration:** `config/horizon.php`

Для production настройте авторизацию Horizon в `app/Providers/HorizonServiceProvider.php` перед публичным доступом к dashboard.

Перезапуск после изменения конфигурации:

```bash
docker compose restart horizon
```

---

## Scheduler

Контейнер `reputation-scheduler` выполняет `php artisan schedule:run` каждые 60 секунд.

Просмотр логов:

```bash
docker compose logs -f scheduler
```

В Фазе 1 нет scheduled tasks сверх стандартного Laravel skeleton. Контейнер scheduler подготовлен для будущих фаз.

---

## Очередь

| Параметр | Значение |
|---------|-------|
| Driver | Redis |
| Job | `ProcessMentionJob` |
| Worker | Horizon |

Мониторинг failed jobs через Horizon dashboard или:

```bash
docker compose exec app php artisan horizon:status
```

Повтор failed jobs:

```bash
docker compose exec app php artisan queue:retry all
```

---

## Команды Makefile

| Команда | Описание |
|---------|-------------|
| `make up` | Запуск всех контейнеров |
| `make down` | Остановка и удаление контейнеров |
| `make restart` | Перезапуск всех контейнеров |
| `make build` | Пересборка Docker-образов |
| `make migrate` | Выполнение migrations |
| `make fresh` | Удаление всех таблиц и повторный migrate |
| `make artisan <cmd>` | Выполнение Artisan-команды |
| `make composer <cmd>` | Выполнение Composer-команды |
| `make horizon` | Просмотр логов Horizon |
| `make logs` | Просмотр всех логов |

---

## Рекомендации по резервному копированию

| Данные | Метод |
|------|--------|
| PostgreSQL | `docker compose exec postgres pg_dump -U reputation reputation > backup.sql` |
| Redis | AOF persistence включён (`appendonly yes`); volume `redis-data` |
| `.env` | Храните безопасно вне репозитория |

---

## Устранение неполадок

| Проблема | Решение |
|-------|----------|
| Контейнер app перезапускается | Проверьте логи: `docker compose logs app` — часто это failed migration |
| Очередь не обрабатывается | Проверьте Horizon: `docker compose ps horizon` |
| 401 на ingest | Проверьте `INGEST_API_TOKEN` и заголовок `Authorization: Bearer` |
| Telegram не отправляет | Выполните `php artisan telegram:test`; проверьте `TELEGRAM_CHAT_IDS` |
| Claude 404 | Проверьте, что `CLAUDE_MODEL` соответствует доступной модели для вашего API key |
