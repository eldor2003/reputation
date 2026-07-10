<?php

namespace Tests\Unit\Services\Classification;

use App\Services\Classification\ClaudeStructuredOutputService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClaudeStructuredOutputServiceTest extends TestCase
{
    #[Test]
    public function it_parses_json_wrapped_in_markdown_fences(): void
    {
        $service = new ClaudeStructuredOutputService;

        $result = $service->parse([
            'content' => [[
                'type' => 'text',
                'text' => <<<'JSON'
```json
{
  "summary": "Summary.",
  "sentiment": "negative",
  "severity": 4,
  "language": "en",
  "category": "customer_service",
  "person": "unknown",
  "confidence": 88,
  "reasoning": "Reasoning."
}
```
JSON,
            ]],
        ]);

        $this->assertSame('Summary.', $result->classification->summary);
        $this->assertSame('negative', $result->classification->sentiment);
    }
}
