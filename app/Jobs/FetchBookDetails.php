<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\OpenLibraryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchBookDetails implements ShouldQueue
{
    use Queueable;

    public function __construct(public Book $book) {}

    public function handle(OpenLibraryService $service): void
    {
        if (! $this->book->open_library_id || filled($this->book->description)) {
            return;
        }

        $details = $service->fetchDetails($this->book->open_library_id);

        if ($details) {
            $this->book->update($details);
        }
    }
}
