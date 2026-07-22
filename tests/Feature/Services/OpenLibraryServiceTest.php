<?php

use App\Services\OpenLibraryService;
use Illuminate\Support\Facades\Http;

function fakeSearchResponse(): array
{
    return [
        'docs' => [
            [
                'key' => '/works/OL1W',
                'title' => 'Dune',
                'author_name' => ['Frank Herbert'],
                'first_publish_year' => 1965,
                'isbn' => ['9780441013593'],
                'cover_i' => 12345,
                'number_of_pages_median' => 412,
                'publisher' => ['Ace Books'],
                'subject' => ['Science fiction'],
            ],
        ],
    ];
}

test('search only hits the API once for the same query', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(fakeSearchResponse()),
    ]);

    $service = app(OpenLibraryService::class);

    $first = $service->search('dune');
    $second = $service->search('dune');

    expect($first)->toBe($second)
        ->and($first[0]['title'])->toBe('Dune');

    Http::assertSentCount(1);
});

test('search results for different queries are cached separately', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(fakeSearchResponse()),
    ]);

    $service = app(OpenLibraryService::class);

    $service->search('dune');
    $service->search('foundation');

    Http::assertSentCount(2);
});

test('a failed search is not cached', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(status: 500),
    ]);

    $service = app(OpenLibraryService::class);

    $service->search('dune');
    $service->search('dune');

    Http::assertSentCount(2);
});

test('search strips punctuation from the query before calling the API', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(fakeSearchResponse()),
    ]);

    $service = app(OpenLibraryService::class);

    $service->search('Stephen King: Mr. Mercedes!');

    Http::assertSent(fn ($request) => $request['q'] === 'Stephen King Mr Mercedes');
});

test('findByIsbn does not strip the isbn: query prefix', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(fakeSearchResponse()),
    ]);

    $service = app(OpenLibraryService::class);

    $service->findByIsbn('9780441013593');

    Http::assertSent(fn ($request) => $request['q'] === 'isbn:9780441013593');
});

test('fetchDetails only hits the API once for the same id', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(['description' => 'A desert planet epic.']),
    ]);

    $service = app(OpenLibraryService::class);

    $first = $service->fetchDetails('/works/OL1W');
    $second = $service->fetchDetails('/works/OL1W');

    expect($first)->toBe($second)
        ->and($first['description'])->toBe('A desert planet epic.');

    Http::assertSentCount(1);
});
