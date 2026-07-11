<?php

namespace App\Contracts;

interface MentionlyticsAuthServiceInterface
{
    public function getAccessToken(bool $forceRefresh = false): string;

    public function forceRefresh(): string;

    public function invalidateAccessTokenCache(): void;

    public function resetToEnvironmentCredentials(): void;

    public function wasRefreshUsedOnLastOperation(): bool;
}
