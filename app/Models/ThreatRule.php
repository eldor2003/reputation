<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'level',
    'min_score',
    'priority',
    'is_active',
])]
class ThreatRule extends Model
{
    protected function casts(): array
    {
        return [
            'min_score' => 'float',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
