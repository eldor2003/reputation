<?php

namespace App\Models;

use App\Enums\TelegramNotificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['mention_id', 'status', 'message_id', 'chat_id', 'sent_at'])]
class TelegramNotification extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TelegramNotificationStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }
}
