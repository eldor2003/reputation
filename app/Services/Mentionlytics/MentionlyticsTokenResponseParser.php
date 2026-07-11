<?php

namespace App\Services\Mentionlytics;

use App\DTO\MentionlyticsTokenPairDTO;
use App\Exceptions\MentionlyticsApiException;
use Carbon\CarbonImmutable;

class MentionlyticsTokenResponseParser
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseRefreshResponse(array $payload): MentionlyticsTokenPairDTO
    {
        $accessToken = $this->extractToken($payload, ['access_token', 'bearer', 'bearer_token', 'token']);

        if ($accessToken === null) {
            throw new MentionlyticsApiException('Mentionlytics token refresh response is missing access token.');
        }

        $refreshToken = $this->extractToken($payload, ['refresh_token']);

        if ($refreshToken === null) {
            throw new MentionlyticsApiException(
                'Mentionlytics token refresh response is missing refresh token. Refresh token rotation requires storing the new refresh token.',
            );
        }

        $expiresAt = $this->resolveAccessExpiresAt($payload, $accessToken);
        $refreshExpiresAt = $this->resolveRefreshExpiresAt($payload);

        return new MentionlyticsTokenPairDTO(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: $expiresAt,
            refreshExpiresAt: $refreshExpiresAt,
        );
    }

    public function buildFromEnvironmentCredentials(): ?MentionlyticsTokenPairDTO
    {
        $accessToken = config('mentionlytics.bearer_token');
        $refreshToken = config('mentionlytics.refresh_token');

        if (! is_string($accessToken) || $accessToken === '') {
            return null;
        }

        $refreshTokenValue = is_string($refreshToken) ? $refreshToken : '';

        return new MentionlyticsTokenPairDTO(
            accessToken: $accessToken,
            refreshToken: $refreshTokenValue,
            expiresAt: $this->resolveJwtExpiry($accessToken)
                ?? CarbonImmutable::now()->addSeconds((int) config('mentionlytics.access_token_ttl_seconds')),
            refreshExpiresAt: CarbonImmutable::now()->addSeconds((int) config('mentionlytics.refresh_token_ttl_seconds')),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function extractToken(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        /** @var array<string, mixed>|null $data */
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : null;

        if ($data === null) {
            return null;
        }

        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveAccessExpiresAt(array $payload, string $accessToken): CarbonImmutable
    {
        $expiresIn = $payload['expires_in'] ?? ($payload['data']['expires_in'] ?? null);

        if (is_numeric($expiresIn)) {
            return CarbonImmutable::now()->addSeconds((int) $expiresIn);
        }

        return $this->resolveJwtExpiry($accessToken)
            ?? CarbonImmutable::now()->addSeconds((int) config('mentionlytics.access_token_ttl_seconds'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveRefreshExpiresAt(array $payload): CarbonImmutable
    {
        $refreshExpiresIn = $payload['refresh_expires_in'] ?? ($payload['data']['refresh_expires_in'] ?? null);

        if (is_numeric($refreshExpiresIn)) {
            return CarbonImmutable::now()->addSeconds((int) $refreshExpiresIn);
        }

        return CarbonImmutable::now()->addSeconds((int) config('mentionlytics.refresh_token_ttl_seconds'));
    }

    private function resolveJwtExpiry(string $jwt): ?CarbonImmutable
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true), true);

        if (! is_array($payload) || ! isset($payload['exp']) || ! is_numeric($payload['exp'])) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $payload['exp']);
    }
}
