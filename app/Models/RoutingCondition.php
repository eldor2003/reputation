<?php

namespace App\Models;

use App\Enums\RoutingConditionOperator;
use App\Enums\RoutingConditionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'routing_rule_id',
    'condition_type',
    'operator',
    'value',
])]
class RoutingCondition extends Model
{
    protected function casts(): array
    {
        return [
            'condition_type' => RoutingConditionType::class,
            'operator' => RoutingConditionOperator::class,
            'value' => 'array',
        ];
    }

    public function routingRule(): BelongsTo
    {
        return $this->belongsTo(RoutingRule::class);
    }
}
