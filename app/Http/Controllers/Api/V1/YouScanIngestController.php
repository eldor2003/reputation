<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\IngestYouScanMentionAction;
use App\DTO\YouScanIngestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\YouScanIngestRequest;
use Illuminate\Http\JsonResponse;

class YouScanIngestController extends Controller
{
    public function __invoke(
        YouScanIngestRequest $request,
        IngestYouScanMentionAction $action,
    ): JsonResponse {
        $action->execute(YouScanIngestData::fromRequest($request));

        return response()->json(['success' => true]);
    }
}
