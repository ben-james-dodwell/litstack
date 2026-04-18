<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OwnershipStatusSeeder::class,
            ReadingStatusSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@litstack.app',
            'password' => 'Passw0rd',
        ]);

        $this->call(ShelfSeeder::class);
    }
}
