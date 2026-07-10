<?php

namespace App\Models;

use App\Enums\DeliveryDigestStatus;
use App\Enums\DigestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'digest_type',
    'status',
    'item_count',
    'message_text',
    'chat_id',
    'telegram_message_id',
    'scheduled_for',
    'generated_at',
    'sent_at',
    'error_message',
])]
class DeliveryDigest extends Model
{
    protected function casts(): array
    {
        return [
            'digest_type' => DigestType::class,
            'status' => DeliveryDigestStatus::class,
            'scheduled_for' => 'datetime',
            'generated_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryDigestItem::class)->orderBy('sort_order');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DeliveryMessage::class);
    }
}
