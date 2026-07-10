<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\IngestBrand24MentionAction;
use App\DTO\Brand24IngestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brand24IngestRequest;
use Illuminate\Http\JsonResponse;

class Brand24IngestController extends Controller
{
    public function __invoke(
        Brand24IngestRequest $request,
        IngestBrand24MentionAction $action,
    ): JsonResponse {
        $action->execute(Brand24IngestData::fromRequest($request));

        return response()->json(['success' => true]);
    }
}
