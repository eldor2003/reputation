<?php

namespace App\Factories;

use App\Contracts\ProviderFactoryInterface;
use App\Contracts\ProviderInterface;
use App\Enums\SourceType;
use App\Exceptions\UnsupportedProviderException;
use Illuminate\Contracts\Container\Container;

class ProviderFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function resolve(SourceType $type): ProviderInterface
    {
        $providers = config('providers.providers', []);
        $providerClass = $providers[$type->value] ?? null;

        if (! is_string($providerClass) || $providerClass === '') {
            throw new UnsupportedProviderException($type->value);
        }

        $provider = $this->container->make($providerClass);

        if (! $provider instanceof ProviderInterface) {
            throw new UnsupportedProviderException($type->value);
        }

        if (! $provider->supports($type)) {
            throw new UnsupportedProviderException($type->value);
        }

        return $provider;
    }
}
