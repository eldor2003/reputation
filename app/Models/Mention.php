<?php

namespace App\Models;

use App\Enums\MentionStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'project_id',
    'source_id',
    'person_id',
    'external_id',
    'language',
    'author',
    'author_id',
    'title',
    'content',
    'url',
    'published_at',
    'received_at',
    'metadata',
    'status',
    'dedup_hash',
    'is_duplicate',
    'original_mention_id',
    'mention_cluster_id',
    'simhash',
    'content_fingerprint',
])]
class Mention extends Model
{
    use HasUuid;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'received_at' => 'datetime',
            'metadata' => 'array',
            'status' => MentionStatus::class,
            'is_duplicate' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(MentionCluster::class, 'mention_cluster_id');
    }

    public function clusterItem(): HasOne
    {
        return $this->hasOne(MentionClusterItem::class);
    }

    public function originalMention(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_mention_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'original_mention_id');
    }

    public function raw(): HasOne
    {
        return $this->hasOne(MentionRaw::class);
    }

    public function aiResults(): HasMany
    {
        return $this->hasMany(AiResult::class);
    }

    public function route(): HasOne
    {
        return $this->hasOne(MentionRoute::class);
    }

    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ModerationLog::class);
    }

    public function telegramNotifications(): HasMany
    {
        return $this->hasMany(TelegramNotification::class);
    }

    public function threatResults(): HasMany
    {
        return $this->hasMany(MentionThreatResult::class);
    }

    public function latestThreatResult(): HasOne
    {
        return $this->hasOne(MentionThreatResult::class)->latestOfMany('assessed_at');
    }

    public function deliveryMessages(): HasMany
    {
        return $this->hasMany(DeliveryMessage::class);
    }

    public function deliveryDigestItems(): HasMany
    {
        return $this->hasMany(DeliveryDigestItem::class);
    }
}
