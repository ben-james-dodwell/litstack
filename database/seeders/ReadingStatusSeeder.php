<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReadingStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('reading_statuses')->insert([
            ['name' => 'in_progress'],
            ['name' => 'completed'],
            ['name' => 'abandoned'],
        ]);
    }
}
