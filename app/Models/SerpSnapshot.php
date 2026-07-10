<?php

namespace App\Models;

use App\Enums\SerpEngine;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'search_engine',
    'query',
    'fetched_at',
    'response_time_ms',
    'serpapi_search_id',
    'screenshot_path',
    'metadata',
])]
class SerpSnapshot extends Model
{
    use HasUuid;

    protected function casts(): array
    {
        return [
            'search_engine' => SerpEngine::class,
            'fetched_at' => 'datetime',
            'response_time_ms' => 'float',
            'metadata' => 'array',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(SerpResult::class);
    }
}
