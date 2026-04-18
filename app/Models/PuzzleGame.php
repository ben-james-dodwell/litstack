<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'puzzle_book_id', 'puzzle_date', 'guesses', 'current_round', 'won', 'completed', 'completed_at'])]
class PuzzleGame extends Model
{
    protected function casts(): array
    {
        return [
            'puzzle_date' => 'date',
            'guesses' => 'array',
            'won' => 'boolean',
            'completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PuzzleBook, $this> */
    public function puzzleBook(): BelongsTo
    {
        return $this->belongsTo(PuzzleBook::class);
    }
}
