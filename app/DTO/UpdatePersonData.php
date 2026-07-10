<?php

namespace App\DTO;

use App\Enums\PersonLanguage;

readonly class UpdatePersonData
{
    /**
     * @param  list<string>|null  $customAliases
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ?string $fullName = null,
        public ?PersonLanguage $primaryLanguage = null,
        public ?array $customAliases = null,
        public ?bool $isActive = null,
        public ?string $notes = null,
        public ?array $metadata = null,
        public bool $regenerateAliases = false,
    ) {}
}
