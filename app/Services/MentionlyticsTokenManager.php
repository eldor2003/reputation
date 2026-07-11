<?php

namespace App\Services;

use App\Contracts\MentionlyticsAuthServiceInterface;

class MentionlyticsTokenManager
{
    public function __construct(
        private readonly MentionlyticsAuthServiceInterface $authService,
    ) {}

    public function wasRefreshUsedOnLastOperation(): bool
    {
        return $this->authService->wasRefreshUsedOnLastOperation();
    }

    public function getBearerToken(bool $forceRefresh = false): string
    {
        return $this->authService->getAccessToken($forceRefresh);
    }

    public function refreshBearerToken(): string
    {
        return $this->authService->forceRefresh();
    }

    public function invalidateCachedBearerToken(): void
    {
        $this->authService->invalidateAccessTokenCache();
    }
}
