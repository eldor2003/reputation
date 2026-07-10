<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'mention_cluster_id',
    'mention_id',
    'is_canonical',
    'similarity_score',
    'joined_at',
])]
class MentionClusterItem extends Model
{
    protected function casts(): array
    {
        return [
            'is_canonical' => 'boolean',
            'similarity_score' => 'float',
            'joined_at' => 'datetime',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(MentionCluster::class, 'mention_cluster_id');
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(Mention::class);
    }
}
