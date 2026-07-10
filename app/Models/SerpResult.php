<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'serp_snapshot_id',
    'position',
    'title',
    'url',
    'snippet',
    'fetched_at',
])]
class SerpResult extends Model
{
    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
        ];
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(SerpSnapshot::class, 'serp_snapshot_id');
    }
}
