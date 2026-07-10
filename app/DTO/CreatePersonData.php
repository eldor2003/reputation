<?php

namespace App\DTO;

use App\Enums\PersonLanguage;

readonly class CreatePersonData
{
    /**
     * @param  list<string>  $customAliases
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $projectId,
        public string $fullName,
        public PersonLanguage $primaryLanguage,
        public array $customAliases = [],
        public bool $isActive = true,
        public ?string $notes = null,
        public ?array $metadata = null,
    ) {}
}
