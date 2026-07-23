<?php

use App\Models\Book;
use App\Models\OwnershipStatus;
use App\Models\ReadingStatus;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBook;
use Database\Seeders\OwnershipStatusSeeder;
use Database\Seeders\ReadingStatusSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([OwnershipStatusSeeder::class, ReadingStatusSeeder::class]);

    $this->user = User::factory()->create();
    $this->book = Book::factory()->create();
    $this->userBook = UserBook::factory()->create([
        'user_id' => $this->user->id,
        'book_id' => $this->book->id,
        'ownership_status_id' => OwnershipStatus::where('name', 'owned')->first()->id,
    ]);
});

test('guests are redirected to login', function () {
    $this->get(route('books.show', $this->userBook))->assertRedirect(route('login'));
});

test('cannot view another user\'s book', function () {
    $other = User::factory()->create();

    Livewire::actingAs($other)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->assertForbidden();
});

test('book metadata is displayed', function () {
    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->assertSee($this->book->title)
        ->assertSee($this->book->author);
});

test('ownership status change is saved', function () {
    $wishlist = OwnershipStatus::where('name', 'wishlist')->first();

    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->set('ownershipStatusId', (string) $wishlist->id)
        ->call('saveShelfEntry');

    expect($this->userBook->fresh()->ownership_status_id)->toBe($wishlist->id);
});

test('reading status in_progress auto-fills started_at', function () {
    $inProgress = ReadingStatus::where('name', 'in_progress')->first();

    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->set('readingStatusId', (string) $inProgress->id);

    $fresh = $this->userBook->fresh();
    expect($fresh->reading_status_id)->toBe($inProgress->id)
        ->and($fresh->started_at->toDateString())->toBe(now()->toDateString());
});

test('reading status completed auto-fills both dates', function () {
    $completed = ReadingStatus::where('name', 'completed')->first();

    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->set('readingStatusId', (string) $completed->id);

    $fresh = $this->userBook->fresh();
    expect($fresh->started_at->toDateString())->toBe(now()->toDateString())
        ->and($fresh->ended_at->toDateString())->toBe(now()->toDateString());
});

test('manually changing started_at saves to database', function () {
    $date = '2024-01-15';

    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->set('startedAt', $date);

    expect($this->userBook->fresh()->started_at->toDateString())->toBe($date);
});

test('manually changing ended_at saves to database', function () {
    $date = '2024-03-20';

    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->set('endedAt', $date);

    expect($this->userBook->fresh()->ended_at->toDateString())->toBe($date);
});

test('rating can be set', function () {
    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->call('setRating', 4);

    expect(Review::where('user_book_id', $this->userBook->id)->first()->rating)->toBe(4);
});

test('clicking the same star clears the rating', function () {
    Review::factory()->create(['user_book_id' => $this->userBook->id, 'rating' => 3]);

    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->set('rating', 3)
        ->call('setRating', 3);

    expect(Review::where('user_book_id', $this->userBook->id)->first()->rating)->toBeNull();
});

test('review body can be saved', function () {
    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->set('reviewBody', 'A wonderful read.')
        ->call('saveReview');

    expect(Review::where('user_book_id', $this->userBook->id)->first()->body)->toBe('A wonderful read.');
});

test('removing from shelf deletes the user book', function () {
    Livewire::actingAs($this->user)
        ->test('pages::books.show', ['userBook' => $this->userBook])
        ->call('removeFromShelf');

    expect(UserBook::find($this->userBook->id))->toBeNull();
});
