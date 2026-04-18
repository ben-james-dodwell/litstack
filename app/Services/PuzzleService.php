<?php

namespace App\Services;

use App\Models\PuzzleBook;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PuzzleService
{
    private const EPOCH = '2026-01-01';

    private const MAX_ROUNDS = 7;

    private const OFFSET_CACHE_KEY = 'puzzle_day_offset';

    public function todaysBook(): ?PuzzleBook
    {
        $total = PuzzleBook::count();

        if ($total === 0) {
            return null;
        }

        $base = (int) Carbon::parse(self::EPOCH)->diffInDays(today());
        $offset = (int) Cache::get(self::OFFSET_CACHE_KEY, 0);

        $dayIndex = ($base + $offset) % $total;

        return PuzzleBook::orderBy('id')->skip($dayIndex)->first();
    }

    public function advanceDayOffset(): void
    {
        $current = (int) Cache::get(self::OFFSET_CACHE_KEY, 0);
        Cache::forever(self::OFFSET_CACHE_KEY, $current + 1);
    }

    public function maxRounds(): int
    {
        return self::MAX_ROUNDS;
    }

    /**
     * Returns all clue definitions for a book, keyed by round number.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cluesForBook(PuzzleBook $book): array
    {
        return [
            1 => [
                'type' => 'page_count',
                'label' => 'Pages',
                'value' => $book->page_count,
            ],
            2 => [
                'type' => 'published_year',
                'label' => 'First published',
                'value' => $book->published_year,
            ],
            3 => [
                'type' => 'genres',
                'label' => 'Genres',
                'value' => $book->genres ? (array) $book->genres : [],
            ],
            4 => [
                'type' => 'publisher',
                'label' => 'Publisher',
                'value' => $book->publisher,
            ],
            5 => [
                'type' => 'description_excerpt',
                'label' => 'Description',
                'value' => $book->description ? Str::limit($book->description, 200) : null,
            ],
            6 => [
                'type' => 'author',
                'label' => 'Author',
                'value' => $book->author,
            ],
            7 => [
                'type' => 'cover',
                'label' => 'Cover',
                'value' => $book->cover_url,
                'blur' => 'sm',
            ],
        ];
    }
}
