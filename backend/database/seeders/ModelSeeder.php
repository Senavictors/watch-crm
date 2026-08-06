<?php

namespace Database\Seeders;

use App\Models\Quality;
use App\Models\WatchModel;
use Illuminate\Database\Seeder;

class ModelSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $baseId = Quality::where('name', 'Base ETA')->value('id');
        $watchesCategoryId = 1;
        $boxesCategoryId = 2;

        $data = [
            ['id' => 1, 'brand_id' => 1, 'name' => 'F1 SENNA', 'category_id' => $watchesCategoryId, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'brand_id' => 2, 'name' => 'SpeedMaster', 'category_id' => $watchesCategoryId, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'brand_id' => 3, 'name' => 'PRX', 'category_id' => $watchesCategoryId, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'brand_id' => 4, 'name' => '5 Sports SRPD55', 'category_id' => $watchesCategoryId, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'brand_id' => 5, 'name' => 'Vintage A168WA', 'category_id' => $watchesCategoryId, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'brand_id' => 6, 'name' => 'Caixa Rolex', 'category_id' => $boxesCategoryId, 'quality_id' => null, 'quality_key' => 0, 'created_at' => $now, 'updated_at' => $now],
        ];

        WatchModel::upsert($data, ['id'], ['brand_id', 'name', 'category_id', 'quality_id', 'quality_key', 'updated_at']);
    }
}
