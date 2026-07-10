<?php

namespace App\Models;

use App\Enums\PersonLanguage;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'full_name',
    'primary_language',
    'is_active',
    'notes',
    'metadata',
])]
class Person extends Model
{
    use HasUuid;

    protected $table = 'persons';

    protected function casts(): array
    {
        return [
            'primary_language' => PersonLanguage::class,
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(PersonAlias::class);
    }
}
