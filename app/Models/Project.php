<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'is_active'])]
class Project extends Model
{
    use HasUuid;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }

    public function persons(): HasMany
    {
        return $this->hasMany(Person::class);
    }
}
