<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenLibraryService
{
    private const BASE_URL = 'https://openlibrary.org';

    private const SEARCH_FIELDS = [
        'key',
        'title',
        'author_name',
        'first_publish_year',
        'isbn',
        'cover_i',
        'number_of_pages_median',
        'publisher',
        'subject',
    ];

    /**
     * Search for books by query string.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 20): array
    {
        try {
            $response = Http::timeout(10)
                ->get(self::BASE_URL.'/search.json', [
                    'q' => $query,
                    'limit' => $limit,
                    'fields' => implode(',', self::SEARCH_FIELDS),
                ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('docs', []))
                ->map(fn (array $doc) => $this->normalise($doc))
                ->filter(fn (array $book) => filled($book['title']))
                ->values()
                ->all();

        } catch (ConnectionException $e) {
            Log::warning('Open Library search failed', ['query' => $query, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Fetch books for a subject (used for seeding puzzle candidates).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchSubject(string $subject, int $limit = 50): array
    {
        try {
            $response = Http::timeout(15)
                ->get(self::BASE_URL.'/subjects/'.urlencode($subject).'.json', [
                    'limit' => $limit,
                ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('works', []))
                ->map(fn (array $work) => $this->normaliseSubjectWork($work))
                ->filter(fn (array $book) => filled($book['title']) && filled($book['open_library_id']))
                ->values()
                ->all();

        } catch (ConnectionException $e) {
            Log::warning('Open Library fetchSubject failed', ['subject' => $subject, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Fetch additional details (description, subjects) from the Works endpoint.
     *
     * @return array<string, mixed>
     */
    public function fetchDetails(string $openLibraryId): array
    {
        try {
            $response = Http::timeout(10)
                ->get(self::BASE_URL.$openLibraryId.'.json');

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            $description = match (true) {
                is_string($data['description'] ?? null) => $data['description'],
                is_array($data['description'] ?? null) => $data['description']['value'] ?? null,
                default => null,
            };

            return array_filter([
                'description' => $description,
            ]);

        } catch (ConnectionException $e) {
            Log::warning('Open Library fetchDetails failed', ['id' => $openLibraryId, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Search for a book by ISBN.
     *
     * @return array<string, mixed>|null
     */
    public function findByIsbn(string $isbn): ?array
    {
        $results = $this->search("isbn:{$isbn}", 1);

        return $results[0] ?? null;
    }

    /**
     * Normalise a raw Open Library doc into a consistent shape.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>
     */
    private function normalise(array $doc): array
    {
        $isbns = collect($doc['isbn'] ?? []);

        return [
            'open_library_id' => $doc['key'] ?? null,
            'title' => $doc['title'] ?? '',
            'author' => collect($doc['author_name'] ?? [])->first(),
            'published_year' => $doc['first_publish_year'] ?? null,
            'isbn_10' => $isbns->first(fn (string $isbn) => strlen($isbn) === 10),
            'isbn_13' => $isbns->first(fn (string $isbn) => strlen($isbn) === 13),
            'cover_url' => isset($doc['cover_i'])
                ? "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-M.jpg"
                : null,
            'page_count' => $doc['number_of_pages_median'] ?? null,
            'publisher' => collect($doc['publisher'] ?? [])->first(),
            'genres' => collect($doc['subject'] ?? [])->take(5)->values()->all(),
        ];
    }

    /**
     * Normalise a work from the subjects API into a consistent shape.
     *
     * @param  array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function normaliseSubjectWork(array $work): array
    {
        return [
            'open_library_id' => $work['key'] ?? null,
            'title' => $work['title'] ?? '',
            'author' => collect($work['authors'] ?? [])->pluck('name')->first(),
            'published_year' => $work['first_publish_year'] ?? null,
            'isbn_10' => null,
            'isbn_13' => null,
            'cover_url' => isset($work['cover_id'])
                ? "https://covers.openlibrary.org/b/id/{$work['cover_id']}-M.jpg"
                : null,
            'page_count' => null,
            'publisher' => null,
            'genres' => collect($work['subject'] ?? [])->take(5)->values()->all(),
        ];
    }
}
