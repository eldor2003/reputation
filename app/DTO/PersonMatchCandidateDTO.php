<?php

namespace App\DTO;

use App\Enums\PersonAliasType;

readonly class PersonMatchCandidateDTO
{
    public function __construct(
        public int $personId,
        public string $personUuid,
        public string $fullName,
        public string $matchedAlias,
        public PersonAliasType $matchType,
        public float $confidence,
        public string $matchedIn,
    ) {}
}
