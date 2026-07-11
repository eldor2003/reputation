<?php

namespace App\Services\Mentionlytics;

use App\Contracts\MentionlyticsAuthServiceInterface;
use App\Contracts\MentionlyticsRefreshServiceInterface;
use App\Contracts\MentionlyticsTokenStorageInterface;
use App\DTO\MentionlyticsTokenPairDTO;
use App\Exceptions\MentionlyticsApiException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class MentionlyticsAuthService implements MentionlyticsAuthServiceInterface
{
    private const REFRESH_LOCK_KEY = 'mentionlytics.token.refresh';

    private ?MentionlyticsTokenPairDTO $memoryCache = null;

    private bool $lastRefreshUsed = false;

    public function __construct(
        private readonly MentionlyticsTokenStorageInterface $tokenStorage,
        private readonly MentionlyticsRefreshServiceInterface $refreshService,
        private readonly MentionlyticsTokenResponseParser $parser,
    ) {}

    public function getAccessToken(bool $forceRefresh = false): string
    {
        $this->lastRefreshUsed = false;

        if ($forceRefresh) {
            return $this->performRefresh($this->resolveRefreshToken(), required: true)->accessToken;
        }

        $tokens = $this->resolveActiveTokens();

        if ($this->shouldRefreshProactively($tokens)) {
            return $this->performRefresh($tokens->refreshToken)->accessToken;
        }

        return $tokens->accessToken;
    }

    public function forceRefresh(): string
    {
        return $this->getAccessToken(forceRefresh: true);
    }

    public function invalidateAccessTokenCache(): void
    {
        $this->memoryCache = null;
    }

    /**
     * Operator-only: re-seed stored credentials from .env after manual credential rotation in Mentionlytics.
     * Normal API traffic must never call this; the database is the source of truth after bootstrap.
     */
    public function resetToEnvironmentCredentials(): void
    {
        $this->memoryCache = null;
        $this->tokenStorage->clear();

        $environmentTokens = $this->parser->buildFromEnvironmentCredentials();

        if ($environmentTokens !== null) {
            $this->tokenStorage->store($environmentTokens);
            $this->memoryCache = $environmentTokens;
        }
    }

    public function wasRefreshUsedOnLastOperation(): bool
    {
        return $this->lastRefreshUsed;
    }

    private function resolveActiveTokens(): MentionlyticsTokenPairDTO
    {
        if ($this->memoryCache !== null) {
            return $this->memoryCache;
        }

        $stored = $this->tokenStorage->load();

        if ($stored !== null) {
            $this->memoryCache = $stored;

            return $stored;
        }

        $environmentTokens = $this->parser->buildFromEnvironmentCredentials();

        if ($environmentTokens === null) {
            throw new MentionlyticsApiException('Mentionlytics credentials are not configured.');
        }

        $this->tokenStorage->store($environmentTokens);
        $this->memoryCache = $environmentTokens;

        return $environmentTokens;
    }

    private function shouldRefreshProactively(MentionlyticsTokenPairDTO $tokens): bool
    {
        if ($tokens->refreshToken === '') {
            return false;
        }

        if ($this->refreshTokenExpired($tokens)) {
            throw new MentionlyticsApiException('Mentionlytics refresh token has expired. Renew credentials in Mentionlytics and update the environment configuration.');
        }

        $bufferSeconds = (int) config('mentionlytics.proactive_refresh_buffer_seconds');

        return $tokens->expiresAt->lte(now()->addSeconds($bufferSeconds));
    }

    private function refreshTokenExpired(MentionlyticsTokenPairDTO $tokens): bool
    {
        if ($tokens->refreshExpiresAt === null) {
            return false;
        }

        return $tokens->refreshExpiresAt->isPast();
    }

    private function resolveRefreshToken(): string
    {
        $tokens = $this->resolveActiveTokens();

        if ($tokens->refreshToken === '') {
            throw new MentionlyticsApiException('Mentionlytics refresh token is not configured.');
        }

        return $tokens->refreshToken;
    }

    private function performRefresh(string $refreshToken, bool $required = false): MentionlyticsTokenPairDTO
    {
        $lock = Cache::lock(self::REFRESH_LOCK_KEY, (int) config('mentionlytics.refresh_lock_seconds'));

        try {
            return $lock->block(
                (int) config('mentionlytics.refresh_lock_wait_seconds'),
                function () use ($refreshToken, $required): MentionlyticsTokenPairDTO {
                    $stored = $this->tokenStorage->load();

                    if ($stored !== null) {
                        if ($stored->refreshToken !== $refreshToken) {
                            $this->memoryCache = $stored;

                            return $stored;
                        }

                        if (! $required && ! $this->shouldRefreshProactively($stored)) {
                            $this->memoryCache = $stored;

                            return $stored;
                        }
                    }

                    $refreshed = $this->refreshService->refresh($refreshToken);

                    $this->tokenStorage->store($refreshed);
                    $this->memoryCache = $refreshed;
                    $this->lastRefreshUsed = true;

                    return $refreshed;
                },
            );
        } catch (LockTimeoutException) {
            throw new MentionlyticsApiException('Timed out waiting for Mentionlytics token refresh lock.');
        }
    }
}
