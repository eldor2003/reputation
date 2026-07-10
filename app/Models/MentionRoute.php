<?php

namespace App\Models;

use App\Enums\RoutingChannel;
use App\Enums\RoutingDeliveryMode;
use App\Enums\RoutingPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'mention_id',
    'routing_rule_id',
    'should_notify',
    'priority',
    'channel',
    'delivery_mode',
    'skip_moderation',
    'reason',
])]
class MentionRoute extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'should_notify' => 'boolean',
            'priority' => RoutingPriority::class,
            'channel' => RoutingChannel::class,
            'delivery_mode' => RoutingDeliveryMode::class,
            'skip_moderation' => 'boolean',
        ];
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }

    public function routingRule(): BelongsTo
    {
        return $this->belongsTo(RoutingRule::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(MentionRoutingTarget::class)->orderBy('sort_order');
    }
}
