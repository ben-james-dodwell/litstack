<?php

namespace App\Console\Commands;

use App\Models\PuzzleGame;
use App\Services\PuzzleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('puzzle:reset {--next : Also advance to the next book}')]
#[Description('Reset today\'s puzzle games so the puzzle can be replayed (development use)')]
class ResetPuzzle extends Command
{
    public function handle(PuzzleService $service): int
    {
        $deleted = PuzzleGame::where('puzzle_date', today())->delete();

        $this->line("  Deleted {$deleted} puzzle game(s) for today.");

        if ($this->option('next')) {
            $service->advanceDayOffset();
            $this->line('  Advanced to the next book.');
        }

        $book = $service->todaysBook();

        if ($book) {
            $this->info("Today's puzzle: <comment>{$book->title}</comment>".($book->author ? " by {$book->author}" : ''));
        }

        return Command::SUCCESS;
    }
}
