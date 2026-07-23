<?php

namespace Database\Seeders;

use App\Models\OwnershipStatus;
use Illuminate\Database\Seeder;

class OwnershipStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['owned', 'wishlist'] as $name) {
            OwnershipStatus::firstOrCreate(['name' => $name]);
        }
    }
}
