<?php

use App\Jobs\FetchBookDetails;
use App\Models\Book;
use App\Services\OpenLibraryService;
use Illuminate\Support\Facades\Http;

test('it fetches and saves the description for a book missing one', function () {
    $book = Book::factory()->create([
        'open_library_id' => '/works/OL1W',
        'description' => null,
    ]);

    Http::fake([
        'openlibrary.org/*' => Http::response(['description' => 'A desert planet epic.']),
    ]);

    (new FetchBookDetails($book))->handle(app(OpenLibraryService::class));

    expect($book->fresh()->description)->toBe('A desert planet epic.');
});

test('it does nothing when the book already has a description', function () {
    $book = Book::factory()->create([
        'open_library_id' => '/works/OL1W',
        'description' => 'Existing description.',
    ]);

    Http::fake();

    (new FetchBookDetails($book))->handle(app(OpenLibraryService::class));

    Http::assertNothingSent();
    expect($book->fresh()->description)->toBe('Existing description.');
});
