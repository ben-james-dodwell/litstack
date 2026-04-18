<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\OwnershipStatus;
use App\Models\ReadingStatus;
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

    public function onWishlist(): static
    {
        return $this->state(fn () => [
            'ownership_status_id' => OwnershipStatus::firstOrCreate(['name' => 'wishlist'])->id,
            'reading_status_id' => null,
            'started_at' => null,
            'ended_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'ownership_status_id' => OwnershipStatus::firstOrCreate(['name' => 'owned'])->id,
            'reading_status_id' => ReadingStatus::firstOrCreate(['name' => 'in_progress'])->id,
            'started_at' => $this->faker->dateTimeBetween('-2 months', '-1 week'),
            'ended_at' => null,
        ]);
    }

    public function completed(): static
    {
        $started = $this->faker->dateTimeBetween('-2 years', '-2 months');

        return $this->state(fn () => [
            'ownership_status_id' => OwnershipStatus::firstOrCreate(['name' => 'owned'])->id,
            'reading_status_id' => ReadingStatus::firstOrCreate(['name' => 'completed'])->id,
            'started_at' => $started,
            'ended_at' => $this->faker->dateTimeBetween($started, '-1 month'),
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => [
            'ownership_status_id' => OwnershipStatus::firstOrCreate(['name' => 'owned'])->id,
            'reading_status_id' => ReadingStatus::firstOrCreate(['name' => 'abandoned'])->id,
            'started_at' => $this->faker->dateTimeBetween('-1 year', '-2 months'),
            'ended_at' => null,
        ]);
    }
}
