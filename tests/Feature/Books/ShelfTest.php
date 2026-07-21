<?php

use App\Jobs\CacheCoverImage;
use App\Jobs\FetchBookDetails;
use App\Models\Book;
use App\Models\OwnershipStatus;
use App\Models\ReadingStatus;
use App\Models\User;
use App\Models\UserBook;
use Database\Seeders\OwnershipStatusSeeder;
use Database\Seeders\ReadingStatusSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([OwnershipStatusSeeder::class, ReadingStatusSeeder::class]);
    $this->user = User::factory()->create();
});

test('guests are redirected to login', function () {
    $this->get(route('books.shelf'))->assertRedirect(route('login'));
});

test('shelf displays user books', function () {
    $book = Book::factory()->create(['title' => 'Dune']);
    UserBook::factory()->create([
        'user_id' => $this->user->id,
        'book_id' => $book->id,
        'ownership_status_id' => OwnershipStatus::where('name', 'owned')->first()->id,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::books.shelf')
        ->assertSee('Dune');
});

test('shelf does not show other users books', function () {
    $other = User::factory()->create();
    $book = Book::factory()->create(['title' => 'Secret Book']);
    UserBook::factory()->create([
        'user_id' => $other->id,
        'book_id' => $book->id,
        'ownership_status_id' => OwnershipStatus::where('name', 'owned')->first()->id,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::books.shelf')
        ->assertDontSee('Secret Book');
});

test('ownership filter shows only matching books', function () {
    $owned = OwnershipStatus::where('name', 'owned')->first();
    $wishlist = OwnershipStatus::where('name', 'wishlist')->first();

    $ownedBook = Book::factory()->create(['title' => 'Owned Book']);
    $wishlistBook = Book::factory()->create(['title' => 'Wishlist Book']);

    UserBook::factory()->create(['user_id' => $this->user->id, 'book_id' => $ownedBook->id, 'ownership_status_id' => $owned->id]);
    UserBook::factory()->create(['user_id' => $this->user->id, 'book_id' => $wishlistBook->id, 'ownership_status_id' => $wishlist->id]);

    Livewire::actingAs($this->user)
        ->test('pages::books.shelf')
        ->set('ownershipFilter', (string) $owned->id)
        ->assertSee('Owned Book')
        ->assertDontSee('Wishlist Book');
});

test('reading status filter shows only matching books', function () {
    $owned = OwnershipStatus::where('name', 'owned')->first();
    $inProgress = ReadingStatus::where('name', 'in_progress')->first();

    $readingBook = Book::factory()->create(['title' => 'Currently Reading']);
    $unreadBook = Book::factory()->create(['title' => 'Not Started']);

    UserBook::factory()->create(['user_id' => $this->user->id, 'book_id' => $readingBook->id, 'ownership_status_id' => $owned->id, 'reading_status_id' => $inProgress->id]);
    UserBook::factory()->create(['user_id' => $this->user->id, 'book_id' => $unreadBook->id, 'ownership_status_id' => $owned->id]);

    Livewire::actingAs($this->user)
        ->test('pages::books.shelf')
        ->set('readingFilter', (string) $inProgress->id)
        ->assertSee('Currently Reading')
        ->assertDontSee('Not Started');
});

test('clear filters resets all filters', function () {
    $owned = OwnershipStatus::where('name', 'owned')->first();

    Livewire::actingAs($this->user)
        ->test('pages::books.shelf')
        ->set('ownershipFilter', (string) $owned->id)
        ->set('readingFilter', '1')
        ->set('genreFilter', 'Fantasy')
        ->set('authorFilter', 'Tolkien')
        ->set('sortBy', 'title_asc')
        ->call('clearFilters')
        ->assertSet('ownershipFilter', '')
        ->assertSet('readingFilter', '')
        ->assertSet('genreFilter', '')
        ->assertSet('authorFilter', '')
        ->assertSet('sortBy', 'recent');
});

test('adding a new book queues detail and cover jobs instead of fetching synchronously', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response([
            'docs' => [[
                'key' => '/works/OL1W',
                'title' => 'Dune',
                'author_name' => ['Frank Herbert'],
                'cover_i' => 12345,
            ]],
        ]),
    ]);
    Queue::fake();

    $owned = OwnershipStatus::where('name', 'owned')->first();

    Livewire::actingAs($this->user)
        ->test('pages::books.shelf')
        ->set('addQuery', 'dune')
        ->call('selectBookToAdd', '/works/OL1W')
        ->set('addOwnershipStatusId', (string) $owned->id)
        ->call('addToShelf');

    $book = Book::where('open_library_id', '/works/OL1W')->firstOrFail();

    expect(UserBook::where(['user_id' => $this->user->id, 'book_id' => $book->id])->exists())->toBeTrue();

    Queue::assertPushed(FetchBookDetails::class, fn ($job) => $job->book->is($book));
    Queue::assertPushed(CacheCoverImage::class, fn ($job) => $job->book->is($book));

    Http::assertSentCount(1);
});

test('savePanelShelfEntry cannot update another users book', function () {
    $owned = OwnershipStatus::where('name', 'owned')->first();
    $other = User::factory()->create();

    $book = Book::factory()->create();
    $otherBook = UserBook::factory()->create([
        'user_id' => $other->id,
        'book_id' => $book->id,
        'ownership_status_id' => $owned->id,
    ]);

    $originalOwnershipId = $otherBook->ownership_status_id;

    $wishlist = OwnershipStatus::where('name', 'wishlist')->first();

    Livewire::actingAs($this->user)
        ->test('pages::books.shelf')
        ->set('selectedUserBookId', $otherBook->id)
        ->set('panelOwnershipStatusId', (string) $wishlist->id);

    expect($otherBook->fresh()->ownership_status_id)->toBe($originalOwnershipId);
});
