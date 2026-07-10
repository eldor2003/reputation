# Reputation Project

Production-ready Laravel-платформа мониторинга репутации. Принимает упоминания из социальных сетей от YouScan и Brand24, классифицирует их с помощью Claude AI и доставляет Telegram-оповещения с inline-модерацией.

**Фаза 1 (MVP)** — см. [docs/Phase1-MVP.md](docs/Phase1-MVP.md) и [RELEASE_NOTES_PHASE1.md](RELEASE_NOTES_PHASE1.md).

## Технологический стек

| Компонент | Версия |
|-----------|---------|
| PHP | 8.4 (php-fpm) |
| Laravel | 13 |
| PostgreSQL | 16 |
| Redis | 7 |
| Laravel Horizon | 5.x |
| Nginx | 1.27 |
| Docker Compose | v2 |

## Требования

- Docker Engine 24+
- Docker Compose v2
- Make (опционально)

Минимальный сервер для production: **2 vCPU, 2 GB RAM**.

## Быстрый старт

```bash
cp .env.example .env
docker compose up -d
```

Откройте [http://localhost:8080](http://localhost:8080).

При первом запуске контейнер `app` автоматически устанавливает зависимости, генерирует `APP_KEY` и выполняет migrations.

## Установка

### 1. Клонирование и настройка

```bash
git clone <repository-url>
cd reputation-project
cp .env.example .env
```

Отредактируйте `.env`, указав API keys и tokens (см. [docs/Deployment.md](docs/Deployment.md#required-env-variables)).

### 2. Запуск контейнеров

```bash
docker compose up -d --build
# или
make up
```

### 3. Проверка интеграций

```bash
docker compose exec app php artisan brand24:test
docker compose exec app php artisan claude:test
docker compose exec app php artisan telegram:test
docker compose exec app php artisan pipeline:verify-e2e
```

### 4. Настройка webhook

Направьте webhook YouScan, Brand24 и Telegram на ваш публичный URL. См. [docs/API.md](docs/API.md).

## Docker

### Сервисы

| Сервис | Контейнер | Описание |
|---------|-----------|-------------|
| app | reputation-app | PHP-FPM приложение |
| nginx | reputation-nginx | Веб-сервер (порт 8080) |
| postgres | reputation-postgres | PostgreSQL 16 |
| redis | reputation-redis | Redis 7 |
| horizon | reputation-horizon | Queue worker |
| scheduler | reputation-scheduler | Task scheduler |

### Частые команды

```bash
docker compose up -d              # Запуск
docker compose down               # Остановка
docker compose logs -f            # Логи
docker compose exec app bash      # Shell
docker compose exec app php artisan migrate
```

### Makefile

| Команда | Описание |
|---------|-------------|
| `make up` | Запуск всех контейнеров |
| `make down` | Остановка контейнеров |
| `make migrate` | Выполнение migrations |
| `make artisan <cmd>` | Запуск Artisan |
| `make test` | Запуск PHPUnit test suite |
| `make horizon` | Просмотр логов Horizon |

## Запуск тестов

```bash
docker compose exec app php artisan test
```

**Текущий статус:** 109 tests, 535 assertions — все проходят.

## Полезные Artisan-команды

| Команда | Описание |
|---------|-------------|
| `brand24:test` | Проверка подключения к Brand24 API и список проектов |
| `claude:test` | Проверка подключения к Anthropic Claude API |
| `telegram:test` | Отправка тестового сообщения во все настроенные Telegram-чаты |
| `pipeline:verify-e2e` | Полный pipeline с реальными Brand24, Claude и Telegram |
| `horizon` | Запуск Horizon (работает в отдельном контейнере) |
| `migrate` | Выполнение database migrations |

## Структура проекта

```
app/
├── Actions/              # Use-case orchestrators (Ingest, Process, Classify, Route)
├── Console/Commands/     # Integration verification commands
├── Contracts/            # Service interfaces
├── DTO/                  # Data transfer objects
├── Enums/                # Domain enums (SourceType, MentionStatus, etc.)
├── Events/               # Domain events (MentionReceived, MentionRouted, etc.)
├── Http/                 # Controllers, middleware, form requests
├── Jobs/                 # ProcessMentionJob
├── Listeners/            # SendTelegramNotificationListener
├── Models/               # Eloquent models
├── Providers/
│   ├── Brand24/          # Brand24 provider + normalizer
│   └── YouScan/          # YouScan provider + normalizer
├── Services/             # Claude, Telegram, dedup, routing, storage
└── Support/              # Mappers, resolvers, helpers

config/                   # Application config
database/migrations/      # PostgreSQL schema (10 domain tables)
docker/                   # Docker, nginx, PHP config
docs/                     # Architecture, deployment, API docs
tests/                    # Unit + feature + E2E tests
```

## Документация

| Документ | Описание |
|----------|-------------|
| [docs/Phase1-MVP.md](docs/Phase1-MVP.md) | Объём MVP и функциональность |
| [docs/Architecture.md](docs/Architecture.md) | Архитектура системы и pipeline |
| [docs/Deployment.md](docs/Deployment.md) | Руководство по production-развёртыванию |
| [docs/API.md](docs/API.md) | Справочник REST API |
| [RELEASE_NOTES_PHASE1.md](RELEASE_NOTES_PHASE1.md) | Release notes Фазы 1 |

## Обзор pipeline

```
YouScan / Brand24 Webhook
        ↓
   Ingest (idempotent)
        ↓
   Queue (Redis + Horizon)
        ↓
   Normalize → Deduplicate → Classify (Claude) → Route
        ↓
   Telegram Notification + Moderation
```

## Окружение

Ключевые настройки в `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=postgres
REDIS_HOST=redis
QUEUE_CONNECTION=redis

INGEST_API_TOKEN=
ANTHROPIC_API_KEY=
CLAUDE_MODEL=claude-sonnet-4-6
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_IDS=
TELEGRAM_WEBHOOK_SECRET=
BRAND24_API_KEY=
BRAND24_BASE_URL=https://api-data.brand24.com
BRAND24_ACCOUNT_ID=
```

Полный справочник переменных: [docs/Deployment.md](docs/Deployment.md).

## Заметки по безопасности

- PostgreSQL и Redis доступны только внутри Docker-сети
- Ingest-эндпоинты требуют Bearer token authentication
- Telegram webhook требует заголовок secret token
- Nginx блокирует доступ к скрытым файлам (`.env` и т.д.)
- Установите `APP_DEBUG=false` в production

## Лицензия

MIT
