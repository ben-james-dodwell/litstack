<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'open_library_id',
    'isbn_10',
    'isbn_13',
    'title',
    'author',
    'description',
    'cover_url',
    'published_year',
    'page_count',
    'publisher',
    'genres',
])]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'genres' => AsArrayObject::class,
            'published_year' => 'integer',
            'page_count' => 'integer',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_books')
            ->using(UserBook::class)
            ->withPivot(['ownership_status_id', 'reading_status_id', 'started_at', 'ended_at'])
            ->withTimestamps();
    }

    /** @return HasMany<UserBook, $this> */
    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    /** @return HasManyThrough<Review, UserBook, $this> */
    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(
            Review::class,
            UserBook::class,
            'book_id',
            'user_book_id',
        );
    }
}
