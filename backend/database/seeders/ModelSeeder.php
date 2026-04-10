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
            ['id' => 1, 'brand_id' => 1, 'name' => 'F1 SENNA', 'product_type' => WatchModel::TYPE_WATCH, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'brand_id' => 2, 'name' => 'SpeedMaster', 'product_type' => WatchModel::TYPE_WATCH, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'brand_id' => 3, 'name' => 'PRX', 'product_type' => WatchModel::TYPE_WATCH, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'brand_id' => 4, 'name' => '5 Sports SRPD55', 'product_type' => WatchModel::TYPE_WATCH, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'brand_id' => 5, 'name' => 'Vintage A168WA', 'product_type' => WatchModel::TYPE_WATCH, 'quality_id' => $baseId, 'quality_key' => $baseId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'brand_id' => 6, 'name' => 'Caixa Rolex', 'product_type' => WatchModel::TYPE_BOX, 'quality_id' => null, 'quality_key' => 0, 'created_at' => $now, 'updated_at' => $now],
        ];

        WatchModel::upsert($data, ['id'], ['brand_id', 'name', 'product_type', 'quality_id', 'quality_key', 'updated_at']);
    }
}
