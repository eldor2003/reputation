<?php

namespace App\Providers\Mentionlytics;

use App\Contracts\MentionlyticsClientInterface;
use App\Contracts\ProviderInterface;
use App\DTO\MentionlyticsConnectionInfoDTO;
use App\DTO\MentionlyticsMentionsPageDTO;
use App\DTO\MentionlyticsMentionsQueryDTO;
use App\DTO\NormalizedMentionDTO;
use App\Enums\SourceType;

class MentionlyticsProvider implements ProviderInterface
{
    public function __construct(
        private readonly MentionlyticsNormalizer $normalizer,
        private readonly MentionlyticsClientInterface $client,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalize(array $payload): NormalizedMentionDTO
    {
        return $this->normalizer->normalize($payload);
    }

    public function supports(SourceType $type): bool
    {
        return $type === SourceType::Mentionlytics;
    }

    public function name(): string
    {
        return SourceType::Mentionlytics->value;
    }

    public function testConnection(): MentionlyticsConnectionInfoDTO
    {
        return $this->client->testConnection();
    }

    public function getMentions(MentionlyticsMentionsQueryDTO $query): MentionlyticsMentionsPageDTO
    {
        return $this->client->getMentions($query);
    }
}
