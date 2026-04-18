<?php

namespace App\Console\Commands;

use App\Models\PuzzleBook;
use App\Models\PuzzleGame;
use App\Services\OpenLibraryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('puzzle:seed {--limit=50 : Books per subject} {--fresh : Truncate puzzle_books before seeding}')]
#[Description('Seed puzzle_books from popular Open Library subjects')]
class SeedPuzzleBooks extends Command
{
    private const SUBJECTS = [
        'fiction',
        'mystery',
        'science_fiction',
        'fantasy',
        'biography',
        'history',
        'thriller',
        'romance',
        'classics',
        'adventure',
    ];

    public function handle(OpenLibraryService $service): int
    {
        $limit = (int) $this->option('limit');

        if ($this->option('fresh')) {
            PuzzleGame::query()->delete();
            PuzzleBook::truncate();
            $this->line('  Cleared existing puzzle games and books.');
        }

        $created = 0;
        $skipped = 0;

        foreach (self::SUBJECTS as $subject) {
            $this->line("  Fetching subject: <comment>{$subject}</comment>");

            $books = $service->fetchSubject($subject, $limit);

            foreach ($books as $data) {
                if (blank($data['open_library_id']) || blank($data['title'])) {
                    continue;
                }

                if (! mb_detect_encoding($data['title'], 'ASCII', strict: true)) {
                    continue;
                }

                $existed = PuzzleBook::where('open_library_id', $data['open_library_id'])->exists();

                if ($existed) {
                    $skipped++;

                    continue;
                }

                if (blank($data['description'])) {
                    $details = $service->fetchDetails($data['open_library_id']);
                    if (! empty($details['description'])) {
                        $data['description'] = $details['description'];
                    }
                }

                PuzzleBook::create($data);
                $created++;
            }

            $this->line("    → {$created} added, {$skipped} already exist");
        }

        $total = PuzzleBook::count();
        $this->info("Done. Puzzle library contains {$total} books.");

        return Command::SUCCESS;
    }
}
