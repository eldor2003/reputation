<?php

namespace App\Services;

use App\Contracts\ClaudeStructuredOutputInterface;
use App\DTO\ClassificationResultDTO;
use App\Exceptions\InvalidClassificationResponseException;
use App\Exceptions\SchemaValidationException;

class ClassificationResponseParser
{
    public function __construct(
        private readonly ClaudeStructuredOutputInterface $structuredOutput,
    ) {}

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function parse(array $rawResponse): ClassificationResultDTO
    {
        try {
            return $this->structuredOutput->parse($rawResponse)->classification;
        } catch (SchemaValidationException $exception) {
            throw new InvalidClassificationResponseException($exception->getMessage(), $exception);
        }
    }
}
