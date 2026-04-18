<?php

namespace App\Jobs;

use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class CacheCoverImage implements ShouldQueue
{
    use Queueable;

    public function __construct(public Book $book) {}

    public function handle(): void
    {
        if (! $this->book->cover_url || $this->alreadyCached()) {
            return;
        }

        $response = Http::timeout(10)->get($this->book->cover_url);

        if (! $response->successful()) {
            return;
        }

        $path = 'covers/'.$this->book->id.'.jpg';

        Storage::disk('public')->put($path, $response->body());

        $this->book->update(['cover_url' => Storage::disk('public')->url($path)]);
    }

    private function alreadyCached(): bool
    {
        return Storage::disk('public')->exists('covers/'.$this->book->id.'.jpg');
    }
}
