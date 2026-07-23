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

        User::firstOrCreate(
            ['email' => 'demo@litstack.app'],
            ['name' => 'Demo User', 'password' => 'Passw0rd'],
        );

        $this->call(ShelfSeeder::class);
    }
}
