<?php

use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('deletes the book when the last user removes it from their shelf', function () {
    $user = User::factory()->create();
    $book = Book::factory()->create();
    $userBook = UserBook::factory()->for($user)->for($book)->create();

    $userBook->delete();

    expect(Book::find($book->id))->toBeNull();
});

it('keeps the book when another user still has it on their shelf', function () {
    [$userA, $userB] = User::factory()->count(2)->create();
    $book = Book::factory()->create();
    $ubA = UserBook::factory()->for($userA)->for($book)->create();
    UserBook::factory()->for($userB)->for($book)->create();

    $ubA->delete();

    expect(Book::find($book->id))->not->toBeNull();
});

it('deletes the cached cover file when the last user removes the book', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $book = Book::factory()->create(['cover_url' => '/storage/covers/99.jpg']);
    Storage::disk('public')->put("covers/{$book->id}.jpg", 'fake-image-data');
    $userBook = UserBook::factory()->for($user)->for($book)->create();

    $userBook->delete();

    Storage::disk('public')->assertMissing("covers/{$book->id}.jpg");
    expect(Book::find($book->id))->toBeNull();
});

it('does not attempt file deletion when no cover file is cached', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $book = Book::factory()->create(['cover_url' => null]);
    $userBook = UserBook::factory()->for($user)->for($book)->create();

    $userBook->delete();

    expect(Book::find($book->id))->toBeNull();
});
