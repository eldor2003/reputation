<?php

namespace App\Models;

use App\Enums\ModerationAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mention_id',
    'action',
    'moderator_id',
    'moderator_username',
    'telegram_chat_id',
    'telegram_message_id',
    'callback_query_id',
])]
class ModerationLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ModerationAction::class,
        ];
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }
}
