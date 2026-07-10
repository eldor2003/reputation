# Справочник API

Base URL: `{APP_URL}/api`

Все JSON-ответы используют `Content-Type: application/json`.

---

## Health Check

### GET /up

Встроенный Laravel health-эндпоинт (не под префиксом `/api`).

**Authentication:** None

**Response (200):**

```json
{
  "status": "ok"
}
```

---

## Эндпоинты приёма (Ingest)

Оба ingest-эндпоинта требуют Bearer token authentication через переменную окружения `INGEST_API_TOKEN`.

**Header:**

```
Authorization: Bearer <INGEST_API_TOKEN>
```

Дубликаты webhook (тот же idempotency key или тот же source + external ID) возвращают `200` с `{"success": true}` без повторной обработки.

---

### POST /v1/ingest/youscan

Приём упоминания от YouScan.

**Authentication:** Bearer token (`ingest.token` middleware)

**Request body:**

| Поле | Тип | Обязательно | Описание |
|-------|------|----------|-------------|
| `source_uuid` | UUID | Да | Зарегистрированный UUID источника YouScan |
| `id` | string | Да | External mention ID от YouScan |
| `text` | string | Да | Содержимое упоминания |
| `url` | string | Нет | URL источника |
| `title` | string | Нет | Заголовок упоминания |
| `language` | string | Нет | Код языка |
| `author` | string/object | Нет | Имя автора или объект |
| `published` | datetime | Нет | Время публикации |
| `idempotency_key` | string | Нет | Явный ключ дедупликации |

**Request example:**

```http
POST /api/v1/ingest/youscan HTTP/1.1
Host: reputation.example.com
Authorization: Bearer your-ingest-token
Content-Type: application/json

{
  "source_uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "id": "mention-123",
  "text": "The service was terrible and needs immediate attention.",
  "url": "https://example.com/post/123",
  "title": "Customer complaint",
  "language": "en",
  "author": "John Doe",
  "published": "2026-06-29T10:00:00Z"
}
```

**Response (200):**

```json
{
  "success": true
}
```

**Error responses:**

| Status | Условие | Body |
|--------|-----------|------|
| 401 | Отсутствует или неверный token | `{"message": "Unauthorized."}` |
| 422 | Ошибка валидации | Laravel validation errors |
| 422 | Неизвестный/неактивный source | `{"message": "YouScan source is unavailable: <uuid>"}` |
| 500 | Неожиданный сбой ingest | `{"message": "Failed to ingest youscan mention."}` |

---

### POST /v1/ingest/brand24

Приём упоминания от Brand24.

**Authentication:** Bearer token (`ingest.token` middleware)

**Request body:**

| Поле | Тип | Обязательно | Описание |
|-------|------|----------|-------------|
| `source_uuid` | UUID | Да | Зарегистрированный UUID источника Brand24 |
| `mention_id` | string | Да | External mention ID от Brand24 |
| `content` | string | Да | Содержимое упоминания |
| `url` | string | Нет | URL источника |
| `title` | string | Нет | Заголовок упоминания |
| `language` | string | Нет | Код языка |
| `author_name` | string | Нет | Отображаемое имя автора |
| `author_id` | string | Нет | Идентификатор автора |
| `date` | datetime | Нет | Время публикации |
| `idempotency_key` | string | Нет | Явный ключ дедупликации |

**Request example:**

```http
POST /api/v1/ingest/brand24 HTTP/1.1
Host: reputation.example.com
Authorization: Bearer your-ingest-token
Content-Type: application/json

{
  "source_uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "mention_id": "b24-mention-123",
  "content": "The service was terrible and needs immediate attention.",
  "url": "https://example.com/post/123",
  "title": "Customer complaint",
  "language": "en",
  "author_name": "John Doe",
  "author_id": "johndoe",
  "date": "2026-06-29T10:00:00Z"
}
```

**Response (200):**

```json
{
  "success": true
}
```

**Error responses:**

| Status | Условие | Body |
|--------|-----------|------|
| 401 | Отсутствует или неверный token | `{"message": "Unauthorized."}` |
| 422 | Ошибка валидации | Laravel validation errors |
| 422 | Неизвестный/неактивный source | `{"message": "Brand24 source is unavailable: <uuid>"}` |
| 422 | Неверный тип source | `{"message": "..."}` |
| 500 | Неожиданный сбой ingest | `{"message": "Failed to ingest brand24 mention."}` |

---

### POST /v1/ingest/mentionlytics

Приём упоминания от Mentionlytics (polling bridge или ручной ingest).

**Примечание:** Mentionlytics не предоставляет webhooks. В production упоминания поступают через polling-инфраструктуру (`PollMentionlyticsMentionsAction`), которая вызывает этот endpoint внутренне.

**Authentication:** Bearer token (`ingest.token` middleware)

**Request body:**

| Поле | Тип | Обязательно | Описание |
|-------|------|----------|-------------|
| `source_uuid` | UUID | Да | Зарегистрированный UUID источника Mentionlytics |
| `mention_id` | string | Да | External mention ID (`id` / `uu_id`) |
| `content` | string | Да | Содержимое упоминания (`ftext`) |
| `url` | string | Нет | URL упоминания |
| `title` | string | Нет | Заголовок / tracker |
| `language` | string | Нет | Код языка |
| `author_name` | string | Нет | Имя профиля |
| `author_id` | string | Нет | ID профиля |
| `date` | datetime | Нет | Время публикации |
| `idempotency_key` | string | Нет | Явный ключ дедупликации |

**Request example:**

```http
POST /api/v1/ingest/mentionlytics HTTP/1.1
Host: reputation.example.com
Authorization: Bearer your-ingest-token
Content-Type: application/json

{
  "source_uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "mention_id": "ml-mention-123",
  "content": "The service was terrible and needs immediate attention.",
  "url": "https://example.com/post/123",
  "title": "Brand tracker",
  "language": "en",
  "author_name": "John Doe",
  "author_id": "johndoe",
  "date": "2026-07-09T10:00:00Z"
}
```

**Response (200):**

```json
{"success": true}
```

**Errors:**

| HTTP | Условие | Пример |
|------|---------|--------|
| 401 | Неверный ingest token | `{"message": "Не авторизован."}` |
| 422 | Ошибка валидации | `{"message": "...", "errors": {...}}` |
| 422 | Неизвестный/неактивный source | `{"message": "Источник mentionlytics недоступен: <uuid>"}` |
| 500 | Неожиданный сбой ingest | `{"message": "Failed to ingest mentionlytics mention."}` |

---

## Telegram Webhook

### POST /v1/telegram/webhook

Принимает обновления Telegram Bot API (callback query от inline-кнопок модерации).

**Authentication:** Заголовок `X-Telegram-Bot-Api-Secret-Token` должен совпадать с `TELEGRAM_WEBHOOK_SECRET`.

**Header:**

```
X-Telegram-Bot-Api-Secret-Token: <TELEGRAM_WEBHOOK_SECRET>
```

**Request body:** Стандартный объект Telegram Update. Приложение обрабатывает записи `callback_query` с callback data модерации.

**Callback data format:**

```
moderation:<action>:<mention_id>
```

Где `<action>` — одно из: `approve`, `reject`, `skip`.

**Request example (approve):**

```http
POST /api/v1/telegram/webhook HTTP/1.1
Host: reputation.example.com
X-Telegram-Bot-Api-Secret-Token: your-webhook-secret
Content-Type: application/json

{
  "callback_query": {
    "id": "callback-query-1",
    "from": {
      "id": 987654321,
      "username": "moderator_user"
    },
    "message": {
      "message_id": 42,
      "chat": {
        "id": -1001234567890
      }
    },
    "data": "moderation:approve:15"
  }
}
```

**Response (200):**

```json
{
  "success": true
}
```

**Error responses:**

| Status | Условие | Body |
|--------|-----------|------|
| 401 | Отсутствует или неверный secret | `{"message": "Unauthorized."}` |
| 503 | Secret не настроен | `{"message": "Telegram webhook secret is not configured."}` |

**Примечания по поведению:**

- Обновления без callback silently игнорируются (возвращается 200)
- Повторная модерация того же упоминания игнорируется с callback answer
- Валидные действия отправляют доменные события: `MentionApproved`, `MentionRejected`, `MentionSkipped`
- Модерация записывается в `moderation_logs`

---

## Horizon Dashboard

### GET /horizon

Dashboard мониторинга очередей Laravel Horizon.

**Authentication:** В Фазе 1 не настроена — ограничьте доступ в production (см. [Deployment.md](Deployment.md)).

---

## Обработка после ingest

Ingest-эндпоинты возвращают ответ сразу после постановки в очередь. Обработка выполняется асинхронно:

1. Упоминание нормализуется и дедуплицируется
2. Claude классифицирует содержимое (если не дубликат)
3. Router принимает решение об уведомлении
4. Telegram-сообщение отправляется (при негативной тональности)

В Фазе 1 нет polling-эндпоинта для проверки статуса обработки. Мониторинг через базу данных (`mentions.status`) или Horizon dashboard.
