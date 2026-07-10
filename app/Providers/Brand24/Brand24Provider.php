<?php

namespace App\Providers\Brand24;

use App\Contracts\Brand24ClientInterface;
use App\Contracts\ProviderInterface;
use App\DTO\Brand24AccountInfoDTO;
use App\DTO\Brand24MentionsPageDTO;
use App\DTO\Brand24MentionsQueryDTO;
use App\DTO\Brand24ProjectsListDTO;
use App\DTO\NormalizedMentionDTO;
use App\Enums\SourceType;

class Brand24Provider implements ProviderInterface
{
    public function __construct(
        private readonly Brand24Normalizer $normalizer,
        private readonly Brand24ClientInterface $client,
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
        return $type === SourceType::Brand24;
    }

    public function name(): string
    {
        return SourceType::Brand24->value;
    }

    public function testConnection(): Brand24AccountInfoDTO
    {
        return $this->client->testConnection();
    }

    public function getProjects(int $accountId): Brand24ProjectsListDTO
    {
        return $this->client->getProjects($accountId);
    }

    public function getMentions(Brand24MentionsQueryDTO $query): Brand24MentionsPageDTO
    {
        return $this->client->getMentions($query);
    }
}
