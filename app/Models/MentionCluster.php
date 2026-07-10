<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'canonical_mention_id',
    'simhash',
    'content_fingerprint',
    'metadata',
])]
class MentionCluster extends Model
{
    use HasUuid;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function canonicalMention(): BelongsTo
    {
        return $this->belongsTo(Mention::class, 'canonical_mention_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MentionClusterItem::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }
}
