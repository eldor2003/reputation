<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredSecret = config('telegram.webhook_secret');

        if (! is_string($configuredSecret) || $configuredSecret === '') {
            return response()->json(['message' => 'Секрет вебхука Telegram не настроен.'], 503);
        }

        $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! is_string($providedSecret) || ! hash_equals($configuredSecret, $providedSecret)) {
            return response()->json(['message' => 'Не авторизован.'], 401);
        }

        return $next($request);
    }
}
