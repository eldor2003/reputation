<?php

namespace App\Services;

use App\DTO\MentionIngestData;
use App\Interfaces\MentionIngestStorageInterface;
use App\Models\Mention;
use App\Models\MentionRaw;
use Illuminate\Support\Facades\DB;

class MentionIngestStorage implements MentionIngestStorageInterface
{
    public function store(MentionIngestData $data): Mention
    {
        return DB::transaction(function () use ($data): Mention {
            $mention = Mention::query()->create([
                'project_id' => $data->projectId,
                'source_id' => $data->sourceId,
                'external_id' => $data->externalId,
                'content' => '',
                'received_at' => $data->receivedAt,
                'status' => $data->status,
            ]);

            MentionRaw::query()->create([
                'mention_id' => $mention->id,
                'provider' => $data->provider,
                'payload' => $this->buildStoredPayload($data),
            ]);

            return $mention;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStoredPayload(MentionIngestData $data): array
    {
        return array_merge($data->rawPayload, [
            'project_id' => $data->projectId,
            'source_id' => $data->sourceId,
            'received_at' => $data->receivedAt->toIso8601String(),
        ]);
    }
}
