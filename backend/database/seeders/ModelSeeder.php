<?php

namespace Database\Seeders;

use App\Models\WatchModel;
use Illuminate\Database\Seeder;

class ModelSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $data = [
            ['brand_id' => 1, 'name' => 'F1 SENNA', 'created_at' => $now, 'updated_at' => $now],
            ['brand_id' => 2, 'name' => 'SpeedMaster', 'created_at' => $now, 'updated_at' => $now],
            ['brand_id' => 3, 'name' => 'PRX', 'created_at' => $now, 'updated_at' => $now],
            ['brand_id' => 4, 'name' => '5 Sports SRPD55', 'created_at' => $now, 'updated_at' => $now],
            ['brand_id' => 5, 'name' => 'Vintage A168WA', 'created_at' => $now, 'updated_at' => $now],
        ];

        WatchModel::upsert($data, ['brand_id', 'name'], ['updated_at']);
    }
}
