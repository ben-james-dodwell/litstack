<?php

namespace Database\Seeders;

use App\Jobs\CacheCoverImage;
use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

class ShelfSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'demo@litstack.app')->firstOrFail();

        // Wishlist
        $this->shelfEntry(['title' => 'Dune', 'author' => 'Frank Herbert', 'published_year' => 1965, 'page_count' => 412, 'isbn_13' => '9780441013593', 'genres' => ['Science Fiction', 'Fantasy']])
            ->onWishlist()->create(['user_id' => $user->id]);

        $this->shelfEntry(['title' => 'The Lord of the Rings', 'author' => 'J.R.R. Tolkien', 'published_year' => 1954, 'page_count' => 1178, 'isbn_13' => '9780618640157', 'genres' => ['Fantasy', 'Adventure']])
            ->onWishlist()->create(['user_id' => $user->id]);

        // Owned, not yet started
        $this->shelfEntry(['title' => '1984', 'author' => 'George Orwell', 'published_year' => 1949, 'page_count' => 328, 'isbn_13' => '9780451524935', 'genres' => ['Science Fiction', 'Dystopia']])
            ->create(['user_id' => $user->id]);

        $this->shelfEntry(['title' => 'Brave New World', 'author' => 'Aldous Huxley', 'published_year' => 1932, 'page_count' => 311, 'isbn_13' => '9780060850524', 'genres' => ['Science Fiction', 'Dystopia']])
            ->create(['user_id' => $user->id]);

        // In progress
        $this->shelfEntry(['title' => "The Hitchhiker's Guide to the Galaxy", 'author' => 'Douglas Adams', 'published_year' => 1979, 'page_count' => 224, 'isbn_13' => '9780345391803', 'genres' => ['Science Fiction', 'Comedy']])
            ->inProgress()->create(['user_id' => $user->id]);

        $this->shelfEntry(['title' => 'Crime and Punishment', 'author' => 'Fyodor Dostoevsky', 'published_year' => 1866, 'page_count' => 671, 'isbn_13' => '9780140449136', 'genres' => ['Classic', 'Literary Fiction']])
            ->inProgress()->create(['user_id' => $user->id]);

        // Completed with reviews
        $userBook = $this->shelfEntry(['title' => 'To Kill a Mockingbird', 'author' => 'Harper Lee', 'published_year' => 1960, 'page_count' => 281, 'isbn_13' => '9780061743528', 'genres' => ['Classic', 'Literary Fiction']])
            ->completed()->create(['user_id' => $user->id]);
        Review::factory()->create(['user_book_id' => $userBook->id, 'rating' => 5, 'body' => 'A timeless classic. The moral courage shown by Atticus Finch is something every reader carries with them long after the last page.']);

        $userBook = $this->shelfEntry(['title' => 'Pride and Prejudice', 'author' => 'Jane Austen', 'published_year' => 1813, 'page_count' => 432, 'isbn_13' => '9780141439518', 'genres' => ['Classic', 'Romance']])
            ->completed()->create(['user_id' => $user->id]);
        Review::factory()->create(['user_book_id' => $userBook->id, 'rating' => 4, 'body' => "Austen's wit is razor-sharp and the romance is satisfying, though it takes a while to get going."]);

        $userBook = $this->shelfEntry(['title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald', 'published_year' => 1925, 'page_count' => 180, 'isbn_13' => '9780743273565', 'genres' => ['Classic', 'Literary Fiction']])
            ->completed()->create(['user_id' => $user->id]);
        Review::factory()->create(['user_book_id' => $userBook->id, 'rating' => 3, 'body' => null]);

        // Completed without review
        $this->shelfEntry(['title' => 'Of Mice and Men', 'author' => 'John Steinbeck', 'published_year' => 1937, 'page_count' => 112, 'isbn_13' => '9780140177398', 'genres' => ['Classic', 'Literary Fiction']])
            ->completed()->create(['user_id' => $user->id]);

        // Abandoned
        $this->shelfEntry(['title' => 'Moby Dick', 'author' => 'Herman Melville', 'published_year' => 1851, 'page_count' => 635, 'isbn_13' => '9780142437247', 'genres' => ['Classic', 'Adventure']])
            ->abandoned()->create(['user_id' => $user->id]);
    }

    /**
     * Create a book, queue cover caching, and return a UserBook factory scoped to it.
     *
     * @param  array<string, mixed>  $bookAttributes
     */
    private function shelfEntry(array $bookAttributes): Factory
    {
        $book = Book::factory()->create($bookAttributes);

        if ($book->cover_url) {
            CacheCoverImage::dispatch($book);
        }

        return UserBook::factory()->for($book);
    }
}
