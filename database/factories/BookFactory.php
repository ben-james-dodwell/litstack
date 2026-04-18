<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'open_library_id' => '/works/OL'.$this->faker->unique()->numerify('#######').'W',
            'title' => $this->faker->sentence(3, false),
            'author' => $this->faker->name(),
            'description' => $this->faker->paragraph(),
            'cover_url' => null,
            'published_year' => $this->faker->numberBetween(1900, 2024),
            'page_count' => $this->faker->numberBetween(100, 800),
            'publisher' => $this->faker->company(),
            'genres' => null,
        ];
    }
}
