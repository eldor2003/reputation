<?php

namespace App\Providers\Mentionlytics;

use App\DTO\NormalizedMentionDTO;
use App\Exceptions\MentionNormalizationException;
use Carbon\Carbon;

class MentionlyticsNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): NormalizedMentionDTO
    {
        $projectId = $this->requireInt($payload, 'project_id');
        $sourceId = $this->requireInt($payload, 'source_id');
        $externalId = $this->requireExternalId($payload);
        $text = $this->extractText($payload);

        return new NormalizedMentionDTO(
            projectId: $projectId,
            sourceId: $sourceId,
            externalId: $externalId,
            author: $this->extractAuthorName($payload),
            authorId: $this->extractAuthorId($payload),
            language: $this->extractLanguage($payload),
            text: $text,
            title: $this->optionalString($payload, 'title', 'campaign', 'commtrack'),
            url: $this->optionalString($payload, 'url', 'link', 'murl'),
            publishedAt: $this->extractPublishedAt($payload),
            receivedAt: $this->extractReceivedAt($payload),
            metadata: $this->extractMetadata($payload),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireExternalId(array $payload): string
    {
        foreach (['mention_id', 'id', 'uu_id'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_int($value)) {
                return (string) $value;
            }
        }

        throw new MentionNormalizationException('Mentionlytics payload is missing required field: mention_id.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        foreach (['content', 'ftext', 'text', 'body'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        throw new MentionNormalizationException('Mentionlytics payload is missing mention content.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractAuthorName(array $payload): ?string
    {
        foreach (['author_name', 'profile_name', 'screen_name', 'profile_username', 'name', 'username'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $author = $payload['author'] ?? null;

        if (is_array($author)) {
            foreach (['name', 'username', 'nickname', 'profile_name'] as $key) {
                if (isset($author[$key]) && is_string($author[$key]) && $author[$key] !== '') {
                    return $author[$key];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractAuthorId(array $payload): ?string
    {
        foreach (['author_id', 'uid', 'profile_uu_id', 'profile_id', 'username'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_int($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractLanguage(array $payload): ?string
    {
        foreach (['language', 'lang'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPublishedAt(array $payload): ?Carbon
    {
        foreach (['date', 'pub_datetime', 'pub_date', 'published_at', 'publishedAt'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return Carbon::parse($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractReceivedAt(array $payload): Carbon
    {
        foreach (['received_at', 'receivedAt'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return Carbon::parse($value);
            }
        }

        return now();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function extractMetadata(array $payload): ?array
    {
        $reservedKeys = [
            'project_id',
            'source_id',
            'received_at',
            'receivedAt',
            'source_uuid',
            'mention_id',
            'id',
            'uu_id',
            'content',
            'ftext',
            'text',
            'body',
            'url',
            'link',
            'murl',
            'title',
            'campaign',
            'commtrack',
            'language',
            'lang',
            'author',
            'author_name',
            'author_id',
            'profile_name',
            'screen_name',
            'profile_username',
            'name',
            'username',
            'uid',
            'profile_uu_id',
            'profile_id',
            'date',
            'pub_datetime',
            'pub_date',
            'published_at',
            'publishedAt',
        ];

        $metadata = array_diff_key($payload, array_flip($reservedKeys));

        return $metadata === [] ? null : $metadata;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireInt(array $payload, string $key): int
    {
        if (! isset($payload[$key]) || ! is_numeric($payload[$key])) {
            throw new MentionNormalizationException("Mentionlytics payload is missing required field: {$key}.");
        }

        return (int) $payload[$key];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function optionalString(array $payload, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
