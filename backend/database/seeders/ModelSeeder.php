<?php

namespace Database\Seeders;

use App\Models\WatchModel;
use App\Models\Quality;
use Illuminate\Database\Seeder;

class ModelSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $baseId = Quality::where('name', 'Base ETA')->value('id');

        $data = [
            ['brand_id' => 1, 'name' => 'F1 SENNA', 'quality_id' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['brand_id' => 2, 'name' => 'SpeedMaster', 'quality_id' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['brand_id' => 3, 'name' => 'PRX', 'quality_id' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['brand_id' => 4, 'name' => '5 Sports SRPD55', 'quality_id' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['brand_id' => 5, 'name' => 'Vintage A168WA', 'quality_id' => $baseId, 'created_at' => $now, 'updated_at' => $now],
        ];

        WatchModel::upsert($data, ['brand_id', 'name', 'quality_id'], ['updated_at']);
    }
}
