<?php

namespace App\DTO;

use App\Enums\PersonLanguage;

readonly class PersonDTO
{
    /**
     * @param  list<PersonAliasDTO>  $aliases
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $uuid,
        public int $projectId,
        public string $fullName,
        public PersonLanguage $primaryLanguage,
        public bool $isActive,
        public array $aliases,
        public ?string $notes = null,
        public ?array $metadata = null,
    ) {}
}
