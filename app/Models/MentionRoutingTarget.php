<?php

namespace App\Models;

use App\Enums\RoutingTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mention_route_id',
    'target_type',
    'target_config',
    'sort_order',
])]
class MentionRoutingTarget extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'target_type' => RoutingTargetType::class,
            'target_config' => 'array',
        ];
    }

    public function mentionRoute(): BelongsTo
    {
        return $this->belongsTo(MentionRoute::class);
    }
}
