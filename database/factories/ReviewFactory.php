<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\UserBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_book_id' => UserBook::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'body' => $this->faker->paragraph(),
        ];
    }
}
