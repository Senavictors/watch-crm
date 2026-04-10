<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['id' => 1, 'name' => 'TAG HEUER'],
            ['id' => 2, 'name' => 'Omega'],
            ['id' => 3, 'name' => 'TISSOT'],
            ['id' => 4, 'name' => 'Seiko'],
            ['id' => 5, 'name' => 'Casio'],
            ['id' => 6, 'name' => 'Rolex'],
        ];

        Brand::upsert($data, ['id']);
    }
}
