<?php

namespace App\Models;

use App\Enums\ThreatLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mention_id',
    'ai_result_id',
    'threat_level',
    'threat_score',
    'factor_scores',
    'assessed_at',
])]
class MentionThreatResult extends Model
{
    protected function casts(): array
    {
        return [
            'threat_level' => ThreatLevel::class,
            'threat_score' => 'float',
            'factor_scores' => 'array',
            'assessed_at' => 'datetime',
        ];
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }

    public function aiResult(): BelongsTo
    {
        return $this->belongsTo(AiResult::class);
    }
}
