<?php

namespace App\Models;

use App\Enums\DeliveryDigestItemStatus;
use App\Enums\DigestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'delivery_digest_id',
    'delivery_message_id',
    'mention_id',
    'project_id',
    'digest_type',
    'status',
    'card_payload',
    'sort_order',
    'queued_at',
])]
class DeliveryDigestItem extends Model
{
    protected function casts(): array
    {
        return [
            'digest_type' => DigestType::class,
            'status' => DeliveryDigestItemStatus::class,
            'card_payload' => 'array',
            'queued_at' => 'datetime',
        ];
    }

    public function deliveryDigest(): BelongsTo
    {
        return $this->belongsTo(DeliveryDigest::class);
    }

    public function deliveryMessage(): BelongsTo
    {
        return $this->belongsTo(DeliveryMessage::class);
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
