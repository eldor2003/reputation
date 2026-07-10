<?php

namespace App\Models;

use App\Enums\RoutingDeliveryMode;
use App\Enums\RoutingPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'person_id',
    'name',
    'rule_priority',
    'routing_priority',
    'delivery_mode',
    'auto_skip',
    'skip_moderation',
    'reason_template',
    'is_active',
    'is_fallback',
])]
class RoutingRule extends Model
{
    protected function casts(): array
    {
        return [
            'routing_priority' => RoutingPriority::class,
            'delivery_mode' => RoutingDeliveryMode::class,
            'auto_skip' => 'boolean',
            'skip_moderation' => 'boolean',
            'is_active' => 'boolean',
            'is_fallback' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(RoutingCondition::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(RoutingTarget::class);
    }
}
