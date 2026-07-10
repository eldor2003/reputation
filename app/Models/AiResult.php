<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mention_id',
    'provider',
    'model',
    'cascade_tier',
    'processing_time_ms',
    'input_tokens',
    'output_tokens',
    'estimated_cost',
    'escalation_reason',
    'validation_status',
    'validation_retry_count',
    'injection_detected',
    'guard_reason',
    'summary',
    'sentiment',
    'severity',
    'language',
    'category',
    'person',
    'confidence',
    'reasoning',
    'raw_response',
    'processed_at',
])]
class AiResult extends Model
{
    protected function casts(): array
    {
        return [
            'severity' => 'integer',
            'confidence' => 'integer',
            'processing_time_ms' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost' => 'float',
            'validation_retry_count' => 'integer',
            'injection_detected' => 'boolean',
            'raw_response' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }
}
