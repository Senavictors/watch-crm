<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CustomerSeeder::class,
            BrandSeeder::class,
            QualitySeeder::class,
            ModelSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
