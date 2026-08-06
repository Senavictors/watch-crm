<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Relógios', 'has_quality' => true],
            ['id' => 2, 'name' => 'Caixas', 'has_quality' => false],
        ];

        Category::upsert($data, ['id'], ['name', 'has_quality']);
    }
}
