<?php

namespace App\Contracts;

use App\Enums\SourceType;

interface ProviderFactoryInterface
{
    public function resolve(SourceType $type): ProviderInterface;
}
