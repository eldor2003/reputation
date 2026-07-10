# Фаза 1 — MVP

## Назначение

Reputation Project — платформа мониторинга репутации на базе Laravel. Фаза 1 (MVP) принимает упоминания из социальных сетей от внешних провайдеров мониторинга, нормализует и дедуплицирует их, классифицирует тональность и критичность с помощью Claude (Anthropic), направляет негативные упоминания на уведомление и доставляет оповещения в Telegram с inline-кнопками модерации.

Система рассчитана на автоматическую событийно-ориентированную обработку с идемпотентным приёмом webhook и очередями на базе Redis.

---

## Реализованная функциональность

| Область | Описание |
|------|-------------|
| **Приём webhook** | Приём упоминаний от YouScan и Brand24 через аутентифицированные REST-эндпоинты |
| **Идемпотентный приём** | Дубликаты webhook игнорируются с помощью ключей в БД и блокировок Redis |
| **Нормализация** | Провайдер-специфичные payload преобразуются в единый внутренний формат |
| **Дедупликация** | Точная дедупликация по source + external ID (хеш SHA-256) |
| **AI-классификация** | Claude анализирует содержимое упоминания и возвращает структурированный JSON |
| **Маршрутизация** | MVP-маршрутизатор уведомляет только при негативной тональности |
| **Telegram-оповещения** | Форматированные сообщения отправляются в один или несколько chat ID |
| **Telegram-модерация** | Inline-кнопки Approve / Reject / Skip с webhook-callback |
| **Brand24 API client** | Проверка подключения, список проектов и получение упоминаний |
| **Команды интеграционного тестирования** | Artisan-команды для проверки Brand24, Claude, Telegram и полного pipeline |
| **Horizon dashboard** | Мониторинг очередей и управление воркерами |
| **Health check** | Laravel-эндпоинт `/up` |

---

## Завершённый pipeline

```
Webhook (YouScan / Brand24)
        ↓
   Ingest + Idempotency
        ↓
   ProcessMentionJob (Redis queue)
        ↓
   Normalize (Provider Normalizer)
        ↓
   Deduplicate (ExactDeduplicationEngine)
        ↓
   Classify (AnthropicClaudeClient)
        ↓
   Route (MvpMentionRouter)
        ↓
   MentionRouted event
        ↓
   Telegram Notification (+ moderation keyboard)
```

Дубликаты упоминаний пропускают классификацию, маршрутизацию и уведомление. При сбое нормализации или классификации упоминание помечается как `failed`.

---

## Поддерживаемые провайдеры

| Провайдер | Эндпоинт приёма | Normalizer | Примечания |
|----------|-----------------|------------|-------|
| **YouScan** | `POST /api/v1/ingest/youscan` | `YouScanNormalizer` | Основное поле: `text` |
| **Brand24** | `POST /api/v1/ingest/brand24` | `Brand24Normalizer` | Основное поле: `content` |

Оба провайдера используют один и тот же downstream pipeline после приёма.

**Brand24 API client** также доступен для исходящих API-вызовов (проверка подключения, список проектов, получение упоминаний). Используется интеграционными командами, а не самим webhook pipeline.

---

## AI-интеграция

- **Провайдер:** Anthropic Claude API
- **Клиент:** `AnthropicClaudeClient`
- **Модель:** Настраивается через `CLAUDE_MODEL` (по умолчанию: `claude-sonnet-4-6`)
- **Вывод:** Структурированный JSON с полями `summary`, `sentiment`, `severity` (1–5), `language`, `category`, `person`, `confidence` (0–100), `reasoning`
- **Повтор:** Один автоматический повтор при невалидном JSON или значениях вне допустимого диапазона
- **Хранение:** Результаты сохраняются в таблице `ai_results` вместе с сырым ответом API

---

## Telegram-интеграция

- **Исходящие:** `TelegramBotNotifier` отправляет форматированные оповещения с inline-кнопками Approve / Reject / Skip
- **Несколько чатов:** Поддерживается `TELEGRAM_CHAT_IDS` через запятую; устаревший `TELEGRAM_CHAT_ID` по-прежнему поддерживается
- **Входящие:** `POST /api/v1/telegram/webhook` принимает callback query от кнопок модерации
- **Модерация:** Действия записываются в `moderation_logs`; отправляются доменные события (`MentionApproved`, `MentionRejected`, `MentionSkipped`)
- **Повтор:** Один повтор при сбое отправки в Telegram для каждого чата

---

## База данных

PostgreSQL 16 с 10 доменными таблицами:

| Таблица | Назначение |
|-------|---------|
| `projects` | Проекты мониторинга |
| `sources` | Источники провайдеров, привязанные к проектам (маршрутизация приёма по UUID) |
| `mentions` | Нормализованные записи упоминаний со статусом и метаданными дедупликации |
| `mention_raws` | Исходные payload webhook |
| `ai_results` | Результаты классификации Claude |
| `mention_routes` | Решения маршрутизации для каждого упоминания |
| `telegram_notifications` | Статус доставки по каждому чату |
| `moderation_logs` | Действия модерации в Telegram |
| `ingest_idempotency_keys` | Записи дедупликации webhook |

Полную диаграмму схемы см. в [Architecture.md](Architecture.md).

---

## Очередь

- **Драйвер:** Redis (`QUEUE_CONNECTION=redis`)
- **Воркер:** Laravel Horizon (отдельный Docker-контейнер)
- **Основная job:** `ProcessMentionJob` — асинхронно выполняет полный pipeline обработки упоминания после приёма

---

## Дедупликация

Два уровня:

1. **Идемпотентность приёма** — предотвращает повторную обработку webhook через `ingest_idempotency_keys`, блокировки Redis и уникальные ограничения на `(source_id, external_id)`.
2. **Точная дедупликация** — при обработке `ExactDeduplicationEngine` генерирует `SHA-256(source_id|external_id)`. Если оригинальное упоминание с тем же хешем уже существует, новое помечается как дубликат, и обработка останавливается до классификации.

---

## Тестирование

| Категория | Количество | Покрытие |
|----------|-------|----------|
| Unit tests | 44 | Actions, services, normalizers, parsers, router |
| Feature tests | 63 | Ingest, pipeline, classification, routing, Telegram, E2E |
| E2E scenarios | 5 | Full pipeline, duplicate, Claude fail, Telegram fail |
| Integration commands | 4 | `brand24:test`, `claude:test`, `telegram:test`, `pipeline:verify-e2e` |

Запуск: `docker compose exec app php artisan test`

---

## Известные ограничения (Фаза 1)

- **Маршрутизация только MVP:** Telegram-уведомление отправляется только при негативной тональности; нейтральные и позитивные упоминания сохраняются, но не оповещаются.
- **Только точная дедупликация:** Нет нечёткой или content-based дедупликации.
- **Нет admin UI:** Источники и проекты создаются через database seeders или прямую настройку в БД/API.
- **Нет автоматического polling Brand24:** Упоминания поступают через webhook ingest; Brand24 API client предназначен для проверки и ручного E2E-тестирования.
- **Одна модель классификации:** Нет цепочки fallback-моделей или A/B-тестирования.
- **Модерация только с логированием:** Approve/Reject/Skip записывают действия и отправляют события; в Фазе 1 нет downstream-автоматизации workflow.
- **Доступ к Horizon:** Dashboard доступен по `/horizon`, но авторизация для production не настроена (см. Deployment.md).

---


