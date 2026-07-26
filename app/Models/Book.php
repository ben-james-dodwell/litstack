<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /** Falls back to Open Library ISBN cover when no URL is stored. */
    protected function coverUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value
                ?? ($this->isbn_13 ? "https://covers.openlibrary.org/b/isbn/{$this->isbn_13}-M.jpg" : null)
                ?? ($this->isbn_10 ? "https://covers.openlibrary.org/b/isbn/{$this->isbn_10}-M.jpg" : null),
            set: fn (?string $value): ?string => $value,
        );
    }

    /**
     * Colour pairs used to render a stylistic placeholder cover when no image is available.
     *
     * @return array<int, array{bg: string, fg: string}>
     */
    public static function coverPalettes(): array
    {
        return [
            ['bg' => '#1a1a1a', 'fg' => '#f2c14e'],
            ['bg' => '#8b2e1f', 'fg' => '#f5e6c8'],
            ['bg' => '#2d4a2b', 'fg' => '#e8d9a8'],
            ['bg' => '#1f3a5f', 'fg' => '#f0e4c4'],
            ['bg' => '#c76a3a', 'fg' => '#1a1a1a'],
            ['bg' => '#4a2c5a', 'fg' => '#f3d77c'],
            ['bg' => '#3d2817', 'fg' => '#e8c887'],
            ['bg' => '#7a1f2b', 'fg' => '#f5e6c8'],
            ['bg' => '#0f3a3a', 'fg' => '#d4a574'],
            ['bg' => '#d9b382', 'fg' => '#2a1810'],
            ['bg' => '#2b2b44', 'fg' => '#e5c85c'],
            ['bg' => '#5a3a1f', 'fg' => '#f0dba0'],
        ];
    }

    /**
     * Deterministically pick a placeholder palette for the given seed
     * (a book id, or an Open Library id for books not yet persisted).
     *
     * @return array{bg: string, fg: string}
     */
    public static function coverPalette(int|string $seed): array
    {
        $palettes = self::coverPalettes();
        $index = is_int($seed) ? $seed : crc32($seed);

        return $palettes[$index % count($palettes)];
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
