<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Reputation') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f5f5f4;
            color: #1c1917;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #fff;
            border: 1px solid #e7e5e4;
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        h1 { font-size: 1.5rem; margin-bottom: .5rem; }
        p { color: #57534e; line-height: 1.6; margin-bottom: 1rem; }
        .status { color: #16a34a; font-weight: 600; margin-bottom: 1.5rem; }
        code {
            background: #f5f5f4;
            padding: .15rem .4rem;
            border-radius: 4px;
            font-size: .875rem;
        }
        ul { margin-left: 1.25rem; color: #57534e; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ config('app.name', 'Reputation') }}</h1>
        <p class="status">● Сервис работает</p>
        <p>Система мониторинга репутации — Фаза 1 (MVP).</p>
        <p>Точки входа API:</p>
        <ul>
            <li><code>POST /api/v1/ingest/youscan</code></li>
            <li><code>POST /api/v1/ingest/brand24</code></li>
            <li><code>POST /api/v1/telegram/webhook</code></li>
        </ul>
    </div>
</body>
</html>
