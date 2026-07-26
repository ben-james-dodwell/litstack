<?php

use App\Jobs\CacheCoverImage;
use App\Models\Book;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('it caches a real cover image locally', function () {
    Storage::fake('public');

    $book = Book::factory()->create(['cover_url' => 'https://covers.openlibrary.org/b/id/12345-M.jpg']);

    Http::fake([
        'covers.openlibrary.org/*' => Http::response(str_repeat('a', 5000), 200),
    ]);

    (new CacheCoverImage($book))->handle();

    Storage::disk('public')->assertExists('covers/'.$book->id.'.jpg');
    expect($book->fresh()->cover_url)->toContain('covers/'.$book->id.'.jpg');
});

test('a tiny placeholder image from the API is treated as no cover instead of being cached', function () {
    Storage::fake('public');

    // Open Library returns an HTTP 200 with a 1x1 pixel (43 byte) gif when no
    // real cover exists for an ISBN-guessed URL, instead of a 404.
    $book = Book::factory()->create(['cover_url' => 'https://covers.openlibrary.org/b/isbn/9781784296667-M.jpg']);

    Http::fake([
        'covers.openlibrary.org/*' => Http::response(str_repeat('a', 43), 200),
    ]);

    (new CacheCoverImage($book))->handle();

    Storage::disk('public')->assertMissing('covers/'.$book->id.'.jpg');
    expect($book->fresh()->cover_url)->toBe('');
});

test('it does nothing when the book has no cover url', function () {
    Storage::fake('public');

    $book = Book::factory()->create(['cover_url' => null]);

    Http::fake();

    (new CacheCoverImage($book))->handle();

    Http::assertNothingSent();
});
