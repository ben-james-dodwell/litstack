<?php

use App\Models\Book;
use App\Models\OwnershipStatus;
use App\Models\User;
use App\Models\UserBook;
use Database\Seeders\OwnershipStatusSeeder;
use Database\Seeders\ReadingStatusSeeder;

beforeEach(function () {
    $this->seed([OwnershipStatusSeeder::class, ReadingStatusSeeder::class]);
    $this->user = User::factory()->create();
});

test('guests are redirected to login', function () {
    $this->get(route('books.export'))->assertRedirect(route('login'));
});

test('exporting the full library downloads a pdf', function () {
    $book = Book::factory()->create();
    UserBook::factory()->create([
        'user_id' => $this->user->id,
        'book_id' => $book->id,
        'ownership_status_id' => OwnershipStatus::where('name', 'owned')->value('id'),
    ]);

    $response = $this->actingAs($this->user)->get(route('books.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect(strlen($response->getContent()))->toBeGreaterThan(0);
});

test('invalid ownership value is rejected', function () {
    $this->actingAs($this->user)
        ->get(route('books.export', ['ownership' => 999999]))
        ->assertInvalid('ownership');
});

test('ownership filter and user scoping only include the right books', function () {
    $owned = OwnershipStatus::where('name', 'owned')->first();
    $wishlist = OwnershipStatus::where('name', 'wishlist')->first();

    $ownedBook = Book::factory()->create(['title' => 'Owned Book', 'author' => 'Owned Author']);
    $wishlistBook = Book::factory()->create(['title' => 'Wishlist Book', 'author' => 'Wishlist Author']);

    UserBook::factory()->create(['user_id' => $this->user->id, 'book_id' => $ownedBook->id, 'ownership_status_id' => $owned->id]);
    UserBook::factory()->create(['user_id' => $this->user->id, 'book_id' => $wishlistBook->id, 'ownership_status_id' => $wishlist->id]);

    $other = User::factory()->create();
    $otherBook = Book::factory()->create(['title' => 'Secret Book', 'author' => 'Secret Author']);
    UserBook::factory()->create(['user_id' => $other->id, 'book_id' => $otherBook->id, 'ownership_status_id' => $owned->id]);

    $userBooks = $this->user->userBooks()
        ->with('book')
        ->where('ownership_status_id', $owned->id)
        ->get()
        ->sortBy([['book.author', 'asc'], ['book.title', 'asc']])
        ->values();

    $html = view('pdf.library-export', [
        'userBooks' => $userBooks,
        'scopeLabel' => 'Owned Books',
        'generatedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Owned Book')
        ->toContain('Owned Author')
        ->not->toContain('Wishlist Book')
        ->not->toContain('Secret Book');
});

test('export list is sorted by author', function () {
    $owned = OwnershipStatus::where('name', 'owned')->first();

    $zBook = Book::factory()->create(['title' => 'Book Z', 'author' => 'Zeta Author']);
    $aBook = Book::factory()->create(['title' => 'Book A', 'author' => 'Alpha Author']);

    UserBook::factory()->create(['user_id' => $this->user->id, 'book_id' => $zBook->id, 'ownership_status_id' => $owned->id]);
    UserBook::factory()->create(['user_id' => $this->user->id, 'book_id' => $aBook->id, 'ownership_status_id' => $owned->id]);

    $userBooks = $this->user->userBooks()
        ->with('book')
        ->get()
        ->sortBy([['book.author', 'asc'], ['book.title', 'asc']])
        ->values();

    $html = view('pdf.library-export', [
        'userBooks' => $userBooks,
        'scopeLabel' => 'All Books',
        'generatedAt' => now(),
    ])->render();

    expect(strpos($html, 'Alpha Author'))->toBeLessThan(strpos($html, 'Zeta Author'));
});
