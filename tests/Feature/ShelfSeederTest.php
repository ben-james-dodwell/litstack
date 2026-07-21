<?php

use App\Models\Review;
use App\Models\User;
use App\Models\UserBook;
use Database\Seeders\OwnershipStatusSeeder;
use Database\Seeders\ReadingStatusSeeder;
use Database\Seeders\ShelfSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->seed(OwnershipStatusSeeder::class);
    $this->seed(ReadingStatusSeeder::class);

    User::factory()->create(['email' => 'demo@litstack.app']);
});

test('shelf seeder can be run more than once without duplicate key errors', function () {
    $this->seed(ShelfSeeder::class);

    $userBookCount = UserBook::count();
    $reviewCount = Review::count();

    $this->seed(ShelfSeeder::class);

    expect(UserBook::count())->toBe($userBookCount)
        ->and(Review::count())->toBe($reviewCount);
});
