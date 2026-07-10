<?php

namespace App\Models;

use App\Enums\PersonAliasType;
use App\Enums\PersonLanguage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'person_id',
    'alias',
    'normalized_alias',
    'type',
    'language',
    'source_alias_id',
    'is_auto_generated',
])]
class PersonAlias extends Model
{
    protected function casts(): array
    {
        return [
            'type' => PersonAliasType::class,
            'language' => PersonLanguage::class,
            'is_auto_generated' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function sourceAlias(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_alias_id');
    }
}
