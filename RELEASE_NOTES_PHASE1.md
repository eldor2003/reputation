# Release Notes — Фаза 1 (MVP)

**Дата релиза:** июль 2026  
**Версия:** Phase 1 MVP  
**Стек:** Laravel 13, PHP 8.4, PostgreSQL 16, Redis 7, Docker Compose

---

## Реализованная функциональность

### Core Pipeline

- [x] Webhook ingest для YouScan и Brand24
- [x] Bearer token authentication на ingest-эндпоинтах
- [x] Идемпотентная обработка webhook (database + Redis lock + unique constraint)
- [x] Асинхронная обработка через `ProcessMentionJob` и Redis queue
- [x] Провайдер-специфичная нормализация (YouScan, Brand24)
- [x] Точная дедупликация по source + external ID
- [x] AI-классификация Claude (Anthropic) со структурированным JSON-выводом
- [x] Один повтор при невалидном ответе Claude
- [x] MVP routing — уведомление при негативной тональности
- [x] Доставка Telegram-оповещений с форматированными сообщениями
- [x] Поддержка нескольких Telegram-чатов
- [x] Inline-модерация (Approve / Reject / Skip)
- [x] Telegram webhook с secret token authentication
- [x] Доменные события для всех этапов pipeline

### Интеграции

- [x] Anthropic Claude API client
- [x] Telegram Bot API client с retry
- [x] Brand24 Data API client (connectivity, projects, mentions)

### Инфраструктура

- [x] Docker Compose с 6 сервисами (app, nginx, postgres, redis, horizon, scheduler)
- [x] Автоматический bootstrap (migrations, генерация APP_KEY)
- [x] Laravel Horizon для мониторинга очередей
- [x] Health check endpoint (`/up`)
- [x] Удобные команды Makefile

### Команды проверки

- [x] `php artisan brand24:test` — подключение к Brand24 API
- [x] `php artisan claude:test` — подключение к Claude API
- [x] `php artisan telegram:test` — доставка через Telegram bot
- [x] `php artisan pipeline:verify-e2e` — полный pipeline с реальными API

---

## Результаты тестирования

| Метрика | Значение |
|--------|-------|
| **Всего тестов** | 109 |
| **Всего assertions** | 535 |
| **Статус** | Все проходят |

### Разбивка по suite

| Suite | Тесты |
|-------|-------|
| Unit | 44 |
| Feature | 63 |
| E2E scenarios | 5 |

Области покрытия: ingest, normalization, deduplication, classification, routing, Telegram notifications, moderation, idempotency, Brand24 API client, integration commands.

---

## Текущие интеграции

| Интеграция | Статус | Конфигурация |
|-------------|--------|---------------|
| YouScan (webhook ingest) | Ready | `INGEST_API_TOKEN`, source UUID в БД |
| Brand24 (webhook ingest) | Ready | `INGEST_API_TOKEN`, source UUID в БД |
| Brand24 (API client) | Verified | `BRAND24_API_KEY`, `BRAND24_BASE_URL`, `BRAND24_ACCOUNT_ID` |
| Anthropic Claude | Verified | `ANTHROPIC_API_KEY`, `CLAUDE_MODEL` |
| Telegram Bot | Verified | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_IDS`, `TELEGRAM_WEBHOOK_SECRET` |

Проверка полного end-to-end pipeline успешно завершена (Task #22).

---

## Известные ограничения

- MVP router уведомляет **только негативные** упоминания; нейтральные и позитивные сохраняются, но не оповещаются
- **Только точная дедупликация** — нет нечёткого или content-based сопоставления
- **Нет admin UI** — проекты и sources управляются через базу данных
- **Нет auto-polling Brand24** — упоминания поступают только через webhook
- **Модерация только с логированием** — нет автоматизированных follow-up workflows
- **Horizon dashboard** не защищён для production из коробки
- **Нет API статуса обработки** — клиенты не могут опрашивать статус упоминания через REST

---

## Следующая запланированная фаза

### Фаза 2 (запланировано)

- Admin panel для управления проектами и sources
- Расширенные правила маршрутизации (пороги severity, categories, time windows)
- API-эндпоинты статуса обработки
- Production-авторизация Horizon
- Автоматический polling упоминаний Brand24
- Расширенный мониторинг и alerting

Полную roadmap см. в [docs/Phase1-MVP.md](docs/Phase1-MVP.md).

---

## Документация

| Документ | Описание |
|----------|-------------|
| [docs/Phase1-MVP.md](docs/Phase1-MVP.md) | Объём MVP и функциональность |
| [docs/Architecture.md](docs/Architecture.md) | Архитектура системы |
| [docs/Deployment.md](docs/Deployment.md) | Руководство по развёртыванию |
| [docs/API.md](docs/API.md) | Справочник API |
| [README.md](README.md) | Быстрый старт и обзор проекта |
