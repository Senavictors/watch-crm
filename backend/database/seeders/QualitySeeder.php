<?php

namespace Database\Seeders;

use App\Models\Quality;
use Illuminate\Database\Seeder;

class QualitySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Base ETA'],
            ['id' => 2, 'name' => 'Clone'],
        ];

        Quality::upsert($data, ['id']);
    }
}
