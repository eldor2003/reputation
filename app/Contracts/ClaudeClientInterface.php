<?php

namespace App\Contracts;

interface ClaudeClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function send(string $prompt, ?string $model = null): array;
}
