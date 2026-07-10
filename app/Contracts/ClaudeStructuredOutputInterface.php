<?php

namespace App\Contracts;

use App\DTO\StructuredClassificationResultDTO;

interface ClaudeStructuredOutputInterface
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function parse(array $rawResponse): StructuredClassificationResultDTO;
}
