<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessTelegramCallbackAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        ProcessTelegramCallbackAction $action,
    ): JsonResponse {
        /** @var array<string, mixed> $update */
        $update = $request->all();

        $action->execute($update);

        return response()->json(['success' => true]);
    }
}
