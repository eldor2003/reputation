<?php

namespace App\Contracts;

use App\DTO\MentionlyticsTokenPairDTO;

interface MentionlyticsTokenStorageInterface
{
    public function load(): ?MentionlyticsTokenPairDTO;

    public function store(MentionlyticsTokenPairDTO $tokens): void;

    public function clear(): void;
}
