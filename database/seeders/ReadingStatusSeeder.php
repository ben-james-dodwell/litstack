<?php

namespace Database\Seeders;

use App\Models\ReadingStatus;
use Illuminate\Database\Seeder;

class ReadingStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['in_progress', 'completed', 'abandoned'] as $name) {
            ReadingStatus::firstOrCreate(['name' => $name]);
        }
    }
}
