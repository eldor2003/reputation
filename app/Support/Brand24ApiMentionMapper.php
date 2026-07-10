<?php

namespace App\Support;

use App\DTO\Brand24MentionDTO;

final class Brand24ApiMentionMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toIngestPayload(
        Brand24MentionDTO $mention,
        string $externalId,
        string $sourceUuid,
    ): array {
        $publishedAt = trim($mention->date.' '.$mention->time);

        return array_filter([
            'source_uuid' => $sourceUuid,
            'mention_id' => $externalId,
            'content' => $mention->content ?? $mention->title ?? 'Brand24 mention',
            'url' => $mention->source,
            'title' => $mention->title,
            'language' => 'en',
            'author_name' => $mention->host,
            'date' => $publishedAt !== '' ? $publishedAt : null,
            'category' => $mention->category,
            'sentiment' => $mention->sentiment,
            'tags' => $mention->tags,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
