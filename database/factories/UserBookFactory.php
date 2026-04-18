<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\OwnershipStatus;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBook>
 */
class UserBookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'ownership_status_id' => fn () => OwnershipStatus::firstOrCreate(['name' => 'owned'])->id,
            'reading_status_id' => null,
            'started_at' => null,
            'ended_at' => null,
        ];
    }
}
