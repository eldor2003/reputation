<?php

namespace App\DTO;

readonly class ToolResultDTO
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $toolName,
        public bool $success,
        public array $payload,
        public ?string $error = null,
    ) {}
}
