<?php

namespace Tests\Unit\Factories;

use App\Contracts\ProviderInterface;
use App\Enums\SourceType;
use App\Exceptions\UnsupportedProviderException;
use App\Factories\ProviderFactory;
use App\Providers\YouScan\YouScanProvider;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderFactoryTest extends TestCase
{
    #[Test]
    public function it_resolves_registered_provider(): void
    {
        config([
            'providers.providers' => [
                'youscan' => YouScanProvider::class,
            ],
        ]);

        $factory = new ProviderFactory(new Container);

        $provider = $factory->resolve(SourceType::YouScan);

        $this->assertInstanceOf(YouScanProvider::class, $provider);
        $this->assertInstanceOf(ProviderInterface::class, $provider);
        $this->assertSame('youscan', $provider->name());
        $this->assertTrue($provider->supports(SourceType::YouScan));
    }

    #[Test]
    public function it_throws_for_unregistered_provider(): void
    {
        config(['providers.providers' => []]);

        $factory = new ProviderFactory(new Container);

        $this->expectException(UnsupportedProviderException::class);

        $factory->resolve(SourceType::Brand24);
    }
}
