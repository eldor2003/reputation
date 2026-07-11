<?php

namespace App\Services\Mentionlytics;

use App\Contracts\MentionlyticsTokenStorageInterface;
use App\DTO\MentionlyticsTokenPairDTO;
use App\Models\MentionlyticsOAuthToken;

class DatabaseMentionlyticsTokenStorage implements MentionlyticsTokenStorageInterface
{
    private const CREDENTIAL_KEY = 'default';

    public function load(): ?MentionlyticsTokenPairDTO
    {
        $record = MentionlyticsOAuthToken::query()
            ->where('credential_key', self::CREDENTIAL_KEY)
            ->first();

        if ($record === null) {
            return null;
        }

        return new MentionlyticsTokenPairDTO(
            accessToken: $record->access_token,
            refreshToken: $record->refresh_token,
            expiresAt: $record->expires_at,
            refreshExpiresAt: $record->refresh_expires_at,
        );
    }

    public function store(MentionlyticsTokenPairDTO $tokens): void
    {
        MentionlyticsOAuthToken::query()->updateOrCreate(
            ['credential_key' => self::CREDENTIAL_KEY],
            [
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken,
                'expires_at' => $tokens->expiresAt,
                'refresh_expires_at' => $tokens->refreshExpiresAt,
            ],
        );
    }

    public function clear(): void
    {
        MentionlyticsOAuthToken::query()
            ->where('credential_key', self::CREDENTIAL_KEY)
            ->delete();
    }
}
