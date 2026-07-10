<?php

namespace Tests\Unit\DTO;

use App\DTO\SerpSearchRequestDTO;
use App\Enums\SerpEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SerpSearchRequestDTOTest extends TestCase
{
    #[Test]
    public function it_builds_serpapi_query_parameters(): void
    {
        $request = new SerpSearchRequestDTO(
            query: 'reputation monitoring',
            engine: SerpEngine::Google,
            location: 'United States',
            language: 'en',
            num: 10,
        );

        $this->assertSame([
            'engine' => 'google',
            'q' => 'reputation monitoring',
            'location' => 'United States',
            'hl' => 'en',
            'num' => 10,
        ], $request->toQueryParameters());
    }
}
