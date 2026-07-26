<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReadingStatus extends Model
{
    public $timestamps = false;

    /** @return HasMany<UserBook, $this> */
    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    /** Display label for this status, e.g. "in_progress" reads more compactly as "Started". */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->name === 'in_progress'
                ? 'Started'
                : ucfirst(str_replace('_', ' ', $this->name)),
        );
    }
}
