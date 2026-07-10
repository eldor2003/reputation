<?php

namespace Tests\Unit\Enums;

use App\Enums\SerpEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SerpEngineTest extends TestCase
{
    #[Test]
    public function it_maps_to_serpapi_engine_values(): void
    {
        $this->assertSame('google', SerpEngine::Google->serpApiEngine());
        $this->assertSame('yandex', SerpEngine::Yandex->serpApiEngine());
        $this->assertSame('bing', SerpEngine::Bing->serpApiEngine());
        $this->assertSame('baidu', SerpEngine::Baidu->serpApiEngine());
    }
}
