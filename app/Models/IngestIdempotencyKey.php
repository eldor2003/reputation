<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'idempotency_key',
    'mention_id',
    'provider',
    'source_id',
    'external_id',
])]
class IngestIdempotencyKey extends Model
{
    public const UPDATED_AT = null;

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
