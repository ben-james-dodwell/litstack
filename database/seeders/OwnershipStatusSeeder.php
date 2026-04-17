<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OwnershipStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('ownership_statuses')->insert([
            ['name' => 'owned'],
            ['name' => 'wishlist'],
        ]);
    }
}
