<?php

namespace App\Schemas;

use App\Exceptions\SchemaValidationException;

final class ClassificationSchema
{
    /**
     * @return list<string>
     */
    public static function requiredFields(): array
    {
        return [
            'summary',
            'sentiment',
            'severity',
            'language',
            'category',
            'person',
            'confidence',
            'reasoning',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedSentiments(): array
    {
        return ['positive', 'neutral', 'negative'];
    }

    public static function severityMin(): int
    {
        return (int) config('classification.schema.severity.min', 1);
    }

    public static function severityMax(): int
    {
        return (int) config('classification.schema.severity.max', 5);
    }

    public static function confidenceMin(): int
    {
        return (int) config('classification.schema.confidence.min', 0);
    }

    public static function confidenceMax(): int
    {
        return (int) config('classification.schema.confidence.max', 100);
    }

    public static function languagePattern(): string
    {
        return (string) config('classification.schema.language.pattern', '/^[a-z]{2}$/');
    }

    public static function promptSchemaDescription(): string
    {
        return <<<'PROMPT'
Required JSON schema:
{
  "summary": "string (non-empty)",
  "sentiment": "positive|neutral|negative",
  "severity": integer 1-5,
  "language": "ISO 639-1 code",
  "category": "string (non-empty)",
  "person": "string (non-empty)",
  "confidence": integer 0-100,
  "reasoning": "string (non-empty)"
}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function validate(array $payload): void
    {
        foreach (self::requiredFields() as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new SchemaValidationException("Schema validation failed: missing required field [{$field}].");
            }
        }

        $allowedFields = self::requiredFields();

        foreach (array_keys($payload) as $field) {
            if (! in_array($field, $allowedFields, true)) {
                throw new SchemaValidationException("Schema validation failed: unexpected field [{$field}].");
            }
        }

        foreach (['summary', 'sentiment', 'language', 'category', 'person', 'reasoning'] as $stringField) {
            if (! is_string($payload[$stringField]) || trim($payload[$stringField]) === '') {
                throw new SchemaValidationException("Schema validation failed: [{$stringField}] must be a non-empty string.");
            }
        }

        if (! in_array($payload['sentiment'], self::allowedSentiments(), true)) {
            throw new SchemaValidationException('Schema validation failed: invalid sentiment value.');
        }

        self::validateIntegerRange($payload, 'severity', self::severityMin(), self::severityMax());
        self::validateIntegerRange($payload, 'confidence', self::confidenceMin(), self::confidenceMax());

        if (! preg_match(self::languagePattern(), (string) $payload['language'])) {
            throw new SchemaValidationException('Schema validation failed: invalid language code.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function validateIntegerRange(array $payload, string $field, int $min, int $max): void
    {
        if (! is_int($payload[$field])) {
            throw new SchemaValidationException("Schema validation failed: [{$field}] must be an integer.");
        }

        if ($payload[$field] < $min || $payload[$field] > $max) {
            throw new SchemaValidationException("Schema validation failed: [{$field}] must be between {$min} and {$max}.");
        }
    }
}
