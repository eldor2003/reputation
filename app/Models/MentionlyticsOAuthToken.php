<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentionlyticsOAuthToken extends Model
{
    protected $table = 'mentionlytics_oauth_tokens';

    protected $fillable = [
        'credential_key',
        'access_token',
        'refresh_token',
        'expires_at',
        'refresh_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
        ];
    }
}
