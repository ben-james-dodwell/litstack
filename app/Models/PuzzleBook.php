<?php

namespace App\Models;

use Database\Factories\PuzzleBookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'open_library_id',
    'title',
    'author',
    'description',
    'cover_url',
    'published_year',
    'page_count',
    'publisher',
    'genres',
])]
class PuzzleBook extends Model
{
    /** @use HasFactory<PuzzleBookFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'genres' => AsArrayObject::class,
            'published_year' => 'integer',
            'page_count' => 'integer',
        ];
    }

    /** @return HasMany<PuzzleGame, $this> */
    public function puzzleGames(): HasMany
    {
        return $this->hasMany(PuzzleGame::class);
    }
}
