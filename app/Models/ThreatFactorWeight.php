<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'factor_key',
    'weight',
    'scoring_config',
    'is_active',
])]
class ThreatFactorWeight extends Model
{
    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'scoring_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
