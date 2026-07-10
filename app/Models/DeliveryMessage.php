<?php

namespace App\Models;

use App\Enums\DeliveryChannel;
use App\Enums\DeliveryMessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mention_id',
    'delivery_digest_id',
    'project_id',
    'moderation_log_id',
    'channel',
    'status',
    'card_payload',
    'message_text',
    'chat_id',
    'telegram_message_id',
    'error_message',
    'sent_at',
])]
class DeliveryMessage extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'status' => DeliveryMessageStatus::class,
            'card_payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }

    public function deliveryDigest(): BelongsTo
    {
        return $this->belongsTo(DeliveryDigest::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function moderationLog(): BelongsTo
    {
        return $this->belongsTo(ModerationLog::class);
    }
}
