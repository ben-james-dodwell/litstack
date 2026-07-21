<?php

namespace Database\Seeders;

use App\Jobs\CacheCoverImage;
use App\Models\Book;
use App\Models\OwnershipStatus;
use App\Models\ReadingStatus;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Database\Seeder;

class ShelfSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', config('demo.email', 'demo@litstack.app'))->firstOrFail();

        $ownedId = OwnershipStatus::where('name', 'owned')->value('id');
        $wishlistId = OwnershipStatus::where('name', 'wishlist')->value('id');
        $progressId = ReadingStatus::where('name', 'in_progress')->value('id');
        $completedId = ReadingStatus::where('name', 'completed')->value('id');
        $abandonedId = ReadingStatus::where('name', 'abandoned')->value('id');

        // Wishlist
        $this->shelf($user->id, $wishlistId, null, [
            'open_library_id' => 'isbn:9780441013593',
            'title' => 'Dune',
            'author' => 'Frank Herbert',
            'published_year' => 1965,
            'page_count' => 412,
            'isbn_13' => '9780441013593',
            'genres' => ['Science Fiction', 'Fantasy'],
        ]);

        $this->shelf($user->id, $wishlistId, null, [
            'open_library_id' => 'isbn:9780618640157',
            'title' => 'The Lord of the Rings',
            'author' => 'J.R.R. Tolkien',
            'published_year' => 1954,
            'page_count' => 1178,
            'isbn_13' => '9780618640157',
            'genres' => ['Fantasy', 'Adventure'],
        ]);

        // Owned, not yet started
        $this->shelf($user->id, $ownedId, null, [
            'open_library_id' => 'isbn:9780451524935',
            'title' => '1984',
            'author' => 'George Orwell',
            'published_year' => 1949,
            'page_count' => 328,
            'isbn_13' => '9780451524935',
            'genres' => ['Science Fiction', 'Dystopia'],
        ]);

        $this->shelf($user->id, $ownedId, null, [
            'open_library_id' => 'isbn:9780060850524',
            'title' => 'Brave New World',
            'author' => 'Aldous Huxley',
            'published_year' => 1932,
            'page_count' => 311,
            'isbn_13' => '9780060850524',
            'genres' => ['Science Fiction', 'Dystopia'],
        ]);

        // In progress
        $this->shelf($user->id, $ownedId, $progressId, [
            'open_library_id' => 'isbn:9780345391803',
            'title' => "The Hitchhiker's Guide to the Galaxy",
            'author' => 'Douglas Adams',
            'published_year' => 1979,
            'page_count' => 224,
            'isbn_13' => '9780345391803',
            'genres' => ['Science Fiction', 'Comedy'],
        ], started_at: now()->subWeeks(3));

        $this->shelf($user->id, $ownedId, $progressId, [
            'open_library_id' => 'isbn:9780140449136',
            'title' => 'Crime and Punishment',
            'author' => 'Fyodor Dostoevsky',
            'published_year' => 1866,
            'page_count' => 671,
            'isbn_13' => '9780140449136',
            'genres' => ['Classic', 'Literary Fiction'],
        ], started_at: now()->subMonths(2));

        // Completed with reviews
        $userBook = $this->shelf($user->id, $ownedId, $completedId, [
            'open_library_id' => 'isbn:9780061743528',
            'title' => 'To Kill a Mockingbird',
            'author' => 'Harper Lee',
            'published_year' => 1960,
            'page_count' => 281,
            'isbn_13' => '9780061743528',
            'genres' => ['Classic', 'Literary Fiction'],
        ], started_at: now()->subMonths(6), ended_at: now()->subMonths(5));
        Review::updateOrCreate(['user_book_id' => $userBook->id], ['rating' => 5, 'body' => 'A timeless classic. The moral courage shown by Atticus Finch is something every reader carries with them long after the last page.']);

        $userBook = $this->shelf($user->id, $ownedId, $completedId, [
            'open_library_id' => 'isbn:9780141439518',
            'title' => 'Pride and Prejudice',
            'author' => 'Jane Austen',
            'published_year' => 1813,
            'page_count' => 432,
            'isbn_13' => '9780141439518',
            'genres' => ['Classic', 'Romance'],
        ], started_at: now()->subMonths(10), ended_at: now()->subMonths(9));
        Review::updateOrCreate(['user_book_id' => $userBook->id], ['rating' => 4, 'body' => "Austen's wit is razor-sharp and the romance is satisfying, though it takes a while to get going."]);

        $userBook = $this->shelf($user->id, $ownedId, $completedId, [
            'open_library_id' => 'isbn:9780743273565',
            'title' => 'The Great Gatsby',
            'author' => 'F. Scott Fitzgerald',
            'published_year' => 1925,
            'page_count' => 180,
            'isbn_13' => '9780743273565',
            'genres' => ['Classic', 'Literary Fiction'],
        ], started_at: now()->subYear(), ended_at: now()->subMonths(11));
        Review::updateOrCreate(['user_book_id' => $userBook->id], ['rating' => 3, 'body' => null]);

        // Completed without review
        $this->shelf($user->id, $ownedId, $completedId, [
            'open_library_id' => 'isbn:9780140177398',
            'title' => 'Of Mice and Men',
            'author' => 'John Steinbeck',
            'published_year' => 1937,
            'page_count' => 112,
            'isbn_13' => '9780140177398',
            'genres' => ['Classic', 'Literary Fiction'],
        ], started_at: now()->subMonths(4), ended_at: now()->subMonths(3));

        // Abandoned
        $this->shelf($user->id, $ownedId, $abandonedId, [
            'open_library_id' => 'isbn:9780142437247',
            'title' => 'Moby Dick',
            'author' => 'Herman Melville',
            'published_year' => 1851,
            'page_count' => 635,
            'isbn_13' => '9780142437247',
            'genres' => ['Classic', 'Adventure'],
        ], started_at: now()->subMonths(8));
    }

    /**
     * @param  array<string, mixed>  $bookAttributes
     */
    private function shelf(
        int $userId,
        int $ownershipStatusId,
        ?int $readingStatusId,
        array $bookAttributes,
        mixed $started_at = null,
        mixed $ended_at = null,
    ): UserBook {
        $book = Book::firstOrCreate(
            ['open_library_id' => $bookAttributes['open_library_id']],
            $bookAttributes,
        );

        if ($book->cover_url) {
            CacheCoverImage::dispatch($book);
        }

        return UserBook::updateOrCreate(
            ['user_id' => $userId, 'book_id' => $book->id],
            [
                'ownership_status_id' => $ownershipStatusId,
                'reading_status_id' => $readingStatusId,
                'started_at' => $started_at,
                'ended_at' => $ended_at,
            ],
        );
    }
}
