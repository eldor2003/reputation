<?php

namespace App\Providers\YouScan;

use App\DTO\NormalizedMentionDTO;
use App\Exceptions\MentionNormalizationException;
use Carbon\Carbon;

class YouScanNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): NormalizedMentionDTO
    {
        $projectId = $this->requireInt($payload, 'project_id');
        $sourceId = $this->requireInt($payload, 'source_id');
        $externalId = $this->requireString($payload, 'id');
        $text = $this->extractText($payload);

        return new NormalizedMentionDTO(
            projectId: $projectId,
            sourceId: $sourceId,
            externalId: $externalId,
            author: $this->extractAuthorName($payload),
            authorId: $this->extractAuthorId($payload),
            language: $this->extractLanguage($payload),
            text: $text,
            title: $this->optionalString($payload, 'title'),
            url: $this->optionalString($payload, 'url'),
            publishedAt: $this->extractPublishedAt($payload),
            receivedAt: $this->extractReceivedAt($payload),
            metadata: $this->extractMetadata($payload),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        foreach (['text', 'fullText', 'full_text', 'content'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        throw new MentionNormalizationException('YouScan payload is missing mention text.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractAuthorName(array $payload): ?string
    {
        $author = $payload['author'] ?? null;

        if (is_string($author)) {
            return $author;
        }

        if (is_array($author)) {
            foreach (['name', 'nickname', 'username'] as $key) {
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
        $author = $payload['author'] ?? null;

        if (is_array($author)) {
            foreach (['id', 'profileId', 'profile_id', 'userId', 'user_id'] as $key) {
                if (isset($author[$key]) && (is_string($author[$key]) || is_int($author[$key]))) {
                    return (string) $author[$key];
                }
            }
        }

        foreach (['author_id', 'authorId'] as $key) {
            if (isset($payload[$key]) && (is_string($payload[$key]) || is_int($payload[$key]))) {
                return (string) $payload[$key];
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
        foreach (['published', 'published_at', 'publishedAt', 'date'] as $key) {
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
            'id',
            'text',
            'fullText',
            'full_text',
            'content',
            'url',
            'title',
            'language',
            'lang',
            'author',
            'author_id',
            'authorId',
            'published',
            'published_at',
            'publishedAt',
            'date',
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
            throw new MentionNormalizationException("YouScan payload is missing required field: {$key}.");
        }

        return (int) $payload[$key];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new MentionNormalizationException("YouScan payload is missing required field: {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
