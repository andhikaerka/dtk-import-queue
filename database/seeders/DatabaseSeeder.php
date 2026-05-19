<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create one user (Admin) and generate a full CSV file.
        $this->call([
            UserSeeder::class,
            CsvDummySeeder::class,
        ]);
    }
}
