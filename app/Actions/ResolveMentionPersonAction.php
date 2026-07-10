<?php

namespace App\Actions;

use App\Contracts\PersonResolverInterface;
use App\DTO\NormalizedMentionDTO;
use App\DTO\PersonMatchResultDTO;
use App\Models\Mention;

class ResolveMentionPersonAction
{
    public function __construct(
        private readonly PersonResolverInterface $personResolver,
    ) {}

    public function execute(int $mentionId, NormalizedMentionDTO $mention): PersonMatchResultDTO
    {
        $result = $this->personResolver->resolve($mention);

        if ($result->resolvedPerson !== null) {
            Mention::query()
                ->whereKey($mentionId)
                ->update(['person_id' => $result->resolvedPerson->personId]);
        }

        return $result;
    }
}
