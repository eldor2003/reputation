<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['project_id', 'type', 'external_id', 'name', 'is_active', 'config'])]
class Source extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => SourceType::class,
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }
}
