<?php

namespace App\Contracts;

interface LLMModelInterface
{
    public function tier(): string;

    public function modelName(): string;

    /**
     * @return array<string, mixed>
     */
    public function send(string $prompt): array;
}
