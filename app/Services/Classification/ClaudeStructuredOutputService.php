<?php

namespace App\Services\Classification;

use App\Contracts\ClaudeStructuredOutputInterface;
use App\DTO\ClassificationResultDTO;
use App\DTO\StructuredClassificationResultDTO;
use App\Enums\ClassificationValidationStatus;
use App\Exceptions\SchemaValidationException;
use App\Schemas\ClassificationSchema;

class ClaudeStructuredOutputService implements ClaudeStructuredOutputInterface
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function parse(array $rawResponse): StructuredClassificationResultDTO
    {
        $payload = $this->extractPayload($rawResponse);
        ClassificationSchema::validate($payload);

        return new StructuredClassificationResultDTO(
            classification: new ClassificationResultDTO(
                summary: $payload['summary'],
                sentiment: $payload['sentiment'],
                severity: $payload['severity'],
                language: $payload['language'],
                category: $payload['category'],
                person: $payload['person'],
                confidence: $payload['confidence'],
                reasoning: $payload['reasoning'],
                rawResponse: $rawResponse,
            ),
            validationStatus: ClassificationValidationStatus::Valid,
            validationRetryCount: 0,
            injectionDetected: false,
            guardReason: null,
        );
    }

    /**
     * @param  array<string, mixed>  $rawResponse
     * @return array<string, mixed>
     */
    private function extractPayload(array $rawResponse): array
    {
        $content = $rawResponse['content'] ?? null;

        if (! is_array($content)) {
            throw new SchemaValidationException('Structured output is missing content.');
        }

        foreach ($content as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'text') {
                continue;
            }

            $text = trim((string) ($block['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $decoded = $this->decodeJsonText($text);

            if ($decoded === null) {
                throw new SchemaValidationException('Structured output is not valid JSON.');
            }

            return $decoded;
        }

        throw new SchemaValidationException('Structured output does not contain text content.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonText(string $text): ?array
    {
        $candidates = [$text];

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches) === 1) {
            $candidates[] = trim($matches[1]);
        }

        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
