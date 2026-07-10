<?php

namespace App\DTO;

readonly class Brand24ProjectDTO
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
