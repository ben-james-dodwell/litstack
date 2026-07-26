<?php

use App\Models\Book;
use App\Models\OwnershipStatus;
use App\Models\User;
use App\Models\UserBook;
use Database\Seeders\OwnershipStatusSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(OwnershipStatusSeeder::class);
    $this->user = User::factory()->create();
});

test('scanning a valid isbn shows the matching book in results without opening the modal', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response([
            'docs' => [[
                'key' => '/works/OL1W',
                'title' => 'Dune',
                'author_name' => ['Frank Herbert'],
                'isbn' => ['9780441013593'],
            ]],
        ]),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::books.search')
        ->call('scanIsbn', '9780441013593')
        ->assertSet('selectedOpenLibraryId', null)
        ->assertNotDispatched('modal-show', name: 'add-to-shelf')
        ->assertDispatched('toast-show')
        ->assertSee('Dune');
});

test('scanning a barcode with no matching book toasts instead of opening the modal', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(['docs' => []]),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::books.search')
        ->call('scanIsbn', '9780441013593')
        ->assertSet('selectedOpenLibraryId', null)
        ->assertNotDispatched('modal-show', name: 'add-to-shelf')
        ->assertDispatched('toast-show');
});

test('scanning a non isbn barcode toasts without performing a lookup', function () {
    Livewire::actingAs($this->user)
        ->test('pages::books.search')
        ->call('scanIsbn', '123')
        ->assertSet('selectedOpenLibraryId', null)
        ->assertNotDispatched('modal-show', name: 'add-to-shelf')
        ->assertDispatched('toast-show');

    Http::assertNothingSent();
});

test('scanning a book already on the shelf toasts instead of reopening the modal', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response([
            'docs' => [[
                'key' => '/works/OL1W',
                'title' => 'Dune',
                'author_name' => ['Frank Herbert'],
                'isbn' => ['9780441013593'],
            ]],
        ]),
    ]);

    $book = Book::factory()->create(['open_library_id' => '/works/OL1W']);
    UserBook::factory()->create([
        'user_id' => $this->user->id,
        'book_id' => $book->id,
        'ownership_status_id' => OwnershipStatus::where('name', 'owned')->value('id'),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::books.search')
        ->call('scanIsbn', '978-0-4410-1359-3')
        ->assertSet('selectedOpenLibraryId', null)
        ->assertNotDispatched('modal-show', name: 'add-to-shelf')
        ->assertDispatched('toast-show');
});
