<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\User;
use Database\Seeders\ShelfSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ResetDemoData extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Delete all non-demo users and reset the demo account\'s shelf to its initial state';

    public function handle(): int
    {
        if (! config('demo.enabled') || ! config('demo.email')) {
            $this->error('Demo mode is not enabled (DEMO_ENABLED=false).');

            return self::FAILURE;
        }

        $demo = User::where('email', config('demo.email'))->first();

        if (! $demo) {
            $this->error('Demo user not found — run db:seed first.');

            return self::FAILURE;
        }

        // Remove all registered users except the demo account.
        // Iterating ensures the UserBookObserver fires per user-book, which
        // handles cover-file and orphan-book cleanup automatically.
        $this->info('Removing non-demo users…');
        User::where('email', '!=', config('demo.email'))->lazy()->each(function (User $user): void {
            $user->userBooks()->lazy()->each->delete();
            $user->delete();
        });

        // Catch any books not cleaned up by the observer.
        $orphaned = Book::doesntHave('userBooks')->get();
        foreach ($orphaned as $book) {
            if ($book->getRawOriginal('cover_url') && str_contains((string) $book->getRawOriginal('cover_url'), '/covers/')) {
                Storage::disk('public')->delete('covers/'.basename((string) $book->getRawOriginal('cover_url')));
            }
            $book->delete();
        }

        // Reset the demo user's shelf.
        $this->info('Resetting demo shelf…');
        $demo->userBooks()->lazy()->each->delete();

        (new ShelfSeeder)->run();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
