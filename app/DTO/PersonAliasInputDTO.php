<?php

namespace App\DTO;

use App\Enums\PersonLanguage;

readonly class PersonAliasInputDTO
{
    public function __construct(
        public string $alias,
        public PersonLanguage $language = PersonLanguage::Custom,
    ) {}
}
