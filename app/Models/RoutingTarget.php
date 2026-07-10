<?php

namespace App\Models;

use App\Enums\RoutingTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'routing_rule_id',
    'target_type',
    'target_config',
    'sort_order',
    'is_active',
])]
class RoutingTarget extends Model
{
    protected function casts(): array
    {
        return [
            'target_type' => RoutingTargetType::class,
            'target_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function routingRule(): BelongsTo
    {
        return $this->belongsTo(RoutingRule::class);
    }
}
