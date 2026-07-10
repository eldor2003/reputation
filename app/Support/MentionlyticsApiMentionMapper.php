<?php

namespace App\Support;

use App\DTO\MentionlyticsMentionDTO;

final class MentionlyticsApiMentionMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toIngestPayload(
        MentionlyticsMentionDTO $mention,
        string $sourceUuid,
    ): array {
        return array_filter([
            'source_uuid' => $sourceUuid,
            'mention_id' => $mention->uuid ?? $mention->id,
            'content' => $mention->text,
            'url' => $mention->url,
            'title' => $mention->title,
            'language' => $mention->language,
            'author_name' => $mention->authorName,
            'author_id' => $mention->authorId,
            'date' => $mention->publishedAt,
            'sentiment_text' => $mention->sentiment,
            'mchannel' => $mention->channel,
            'mchannel_id' => $mention->channelId,
            'mEngagement' => $mention->engagement,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
