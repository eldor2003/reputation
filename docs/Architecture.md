# Архитектура

## Обзор

Приложение следует подходу **Clean Architecture** с чётким разделением HTTP-слоя, Actions (use cases), Services, Contracts (интерфейсы), DTO и доменных Events. Бизнес-логика находится в Actions и Services; контроллеры — тонкие invokable-классы.

Внешние интеграции (Claude, Telegram, Brand24 API) доступны через интерфейсы, привязанные в `AppServiceProvider`, что позволяет использовать test doubles без изменения кода pipeline.

```
┌─────────────────────────────────────────────────────────────┐
│  HTTP Layer (Controllers, Middleware, Form Requests)        │
├─────────────────────────────────────────────────────────────┤
│  Actions (Ingest, Process, Classify, Route, Moderate)       │
├─────────────────────────────────────────────────────────────┤
│  Services (Normalizers, Router, Deduplication, Storage)     │
├─────────────────────────────────────────────────────────────┤
│  Contracts / Interfaces                                     │
├─────────────────────────────────────────────────────────────┤
│  Models (Eloquent) + Events + Jobs                            │
└─────────────────────────────────────────────────────────────┘
```

---

## Структура папок

```
app/
├── Actions/              # Use-case orchestrators
├── Console/Commands/     # Integration test & verification commands
├── Contracts/            # Service interfaces
├── DTO/                  # Data transfer objects
├── Enums/                # Domain enumerations
├── Events/               # Domain events
├── Exceptions/           # Domain-specific exceptions
├── Factories/            # ProviderFactory
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Middleware/
│   └── Requests/
├── Interfaces/           # Additional interfaces (legacy naming)
├── Jobs/                 # Queue jobs
├── Listeners/            # Event listeners
├── Models/               # Eloquent models
├── Prompt/               # Claude prompt templates
├── Providers/
│   ├── Brand24/          # Brand24Provider + Brand24Normalizer
│   └── YouScan/          # YouScanProvider + YouScanNormalizer
├── Services/             # Infrastructure & domain services
└── Support/              # Helpers (mappers, resolvers, sanitizers)

config/                   # Application configuration
database/migrations/      # PostgreSQL schema
docker/                   # Dockerfiles, nginx, PHP config, entrypoints
docs/                     # Project documentation
routes/
├── api.php               # Ingest + Telegram webhook routes
└── web.php               # Welcome page
tests/
├── Unit/                 # Isolated component tests
├── Feature/              # HTTP & integration tests
└── Concerns/             # Shared test traits
```

---

## Pipeline обработки

### 1. Ingest

`IngestMentionAction` (общий для YouScan и Brand24):

1. Проверка idempotency key в базе данных
2. Получение блокировки Redis
3. Разрешение активного source по UUID
4. Сохранение упоминания (status: `pending`) + raw payload
5. Запись idempotency key
6. Отправка события `MentionReceived`
7. Постановка `ProcessMentionJob` в очередь

### 2. Process

`ProcessMentionAction` (через `ProcessMentionJob`):

1. Установка status → `processing`
2. Разрешение провайдера через `ProviderFactory`
3. **Normalize** → отправка `MentionNormalized`
4. **Deduplicate** → отправка `MentionDeduplicated`
5. Если дубликат → пометка completed, остановка
6. Сохранение нормализованных полей
7. **Classify** через `ClassifyMentionAction`
8. **Route** через `RouteMentionAction`
9. Установка status → `completed`
10. Отправка `MentionProcessingCompleted`

При сбое → status `failed`, отправка `MentionProcessingFailed`.

### 3. Classify

`ClassifyMentionAction`:

1. Формирование prompt через `MentionPromptBuilder`
2. Вызов Claude API
3. Разбор JSON-ответа через `ClassificationResponseParser`
4. Один повтор при невалидном ответе
5. Сохранение результата в `ai_results`
6. Отправка `MentionClassified`

### 4. Route

`RouteMentionAction`:

1. Загрузка последнего `AiResult`
2. Оценка через `MvpMentionRouter`
3. Сохранение решения в `mention_routes`
4. Отправка `MentionRouted`

### 5. Notify

`SendTelegramNotificationListener` (по событию `MentionRouted`):

1. Пропуск, если `should_notify = false`
2. Формирование сообщения и клавиатуры модерации
3. Отправка во все настроенные chat ID
4. Запись доставки в `telegram_notifications`
5. Один повтор для каждого чата при сбое

---

## Событийно-ориентированная архитектура

Все этапы pipeline отправляют доменные события, расширяющие `MentionDomainEvent`:

| Событие | Когда |
|-------|------|
| `MentionReceived` | После успешного ingest |
| `MentionNormalized` | После нормализации провайдером |
| `MentionDeduplicated` | После проверки dedup |
| `MentionClassified` | После сохранения классификации Claude |
| `MentionRouted` | После сохранения решения маршрутизации |
| `MentionProcessingCompleted` | Pipeline завершён (успех или дубликат) |
| `MentionProcessingFailed` | Pipeline завершился с ошибкой |
| `MentionApproved` | Telegram approve callback |
| `MentionRejected` | Telegram reject callback |
| `MentionSkipped` | Telegram skip callback |

**Зарегистрированный listener:**

```
MentionRouted → SendTelegramNotificationListener
```

Остальные события доступны для будущих listeners, логирования или аналитики без изменения core pipeline.

---

## Provider Pattern

Каждый провайдер мониторинга реализует `ProviderInterface`:

```
ProviderInterface
├── getType(): SourceType
├── normalize(array $payload): NormalizedMentionDTO
```

Регистрация в `ProviderFactory`:

| SourceType | Provider class | Normalizer |
|------------|---------------|------------|
| `youscan` | `YouScanProvider` | `YouScanNormalizer` |
| `brand24` | `Brand24Provider` | `Brand24Normalizer` |

Для добавления нового провайдера требуются: enum case, классы provider + normalizer, регистрация в factory, ingest controller + request и тесты. Downstream pipeline остаётся без изменений.

---

## Поток очереди

```
IngestMentionAction
        │
        ▼
ProcessMentionJob::dispatch($mentionId)
        │
        ▼ (Redis queue, processed by Horizon)
ProcessMentionAction::execute($mentionId)
        │
        ├── Normalize
        ├── Deduplicate
        ├── ClassifyMentionAction
        └── RouteMentionAction
                │
                ▼
        MentionRouted (sync event)
                │
                ▼
        SendTelegramNotificationListener (sync)
```

Jobs реализуют `ShouldQueue`. Horizon работает в отдельном контейнере с `SERVICE_MODE=horizon`.

---

## Диаграмма базы данных

```
projects
├── id, uuid, name, slug, is_active, timestamps, soft_deletes
│
└──< sources
     ├── id, uuid, project_id (FK), type, external_id, name
     ├── is_active, config (JSON), timestamps, soft_deletes
     │
     └──< mentions
          ├── id, uuid, project_id (FK), source_id (FK)
          ├── external_id, language, author, author_id, title
          ├── content, url, published_at, received_at
          ├── metadata (JSON), status
          ├── dedup_hash, is_duplicate, original_mention_id (FK → mentions)
          ├── timestamps
          │
          ├──< mention_raws
          │    └── mention_id (FK, unique), provider, payload (JSON)
          │
          ├──< ai_results
          │    └── mention_id (FK), provider, model, sentiment, severity
          │         category, language, person, confidence, reasoning
          │         summary, raw_response (JSON), processed_at
          │
          ├──< mention_routes
          │    └── mention_id (FK, unique), should_notify, priority
          │         channel, reason, created_at
          │
          ├──< telegram_notifications
          │    └── mention_id (FK), status, message_id, chat_id, sent_at
          │
          ├──< moderation_logs
          │    └── mention_id (FK, unique), action, moderator_id
          │         moderator_username, telegram_chat_id, telegram_message_id
          │         callback_query_id, created_at
          │
          └──< ingest_idempotency_keys
               └── idempotency_key (unique), mention_id (FK), provider
                    source_id (FK), external_id, created_at
```

**Ключевые ограничения:**

- `mentions`: unique `(source_id, external_id)`
- `sources`: unique `(project_id, type, external_id)`
- `mention_routes`: один route на упоминание
- `moderation_logs`: одно действие модерации на упоминание

**Статусы упоминаний:** `pending` → `processing` → `completed` | `failed`
