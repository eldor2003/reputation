<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\IngestMentionlyticsMentionAction;
use App\DTO\MentionlyticsIngestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\MentionlyticsIngestRequest;
use Illuminate\Http\JsonResponse;

class MentionlyticsIngestController extends Controller
{
    public function __invoke(
        MentionlyticsIngestRequest $request,
        IngestMentionlyticsMentionAction $action,
    ): JsonResponse {
        $action->execute(MentionlyticsIngestData::fromRequest($request));

        return response()->json(['success' => true]);
    }
}
