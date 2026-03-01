<?php

namespace Database\Seeders;

use App\Models\WatchModel;
use Illuminate\Database\Seeder;

class ModelSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['id' => 1, 'brand_id' => 1, 'name' => 'F1 SENNA'],
            ['id' => 2, 'brand_id' => 2, 'name' => 'SpeedMaster'],
            ['id' => 3, 'brand_id' => 3, 'name' => 'PRX'],
            ['id' => 4, 'brand_id' => 4, 'name' => '5 Sports SRPD55'],
            ['id' => 5, 'brand_id' => 5, 'name' => 'Vintage A168WA'],
        ];

        WatchModel::upsert($data, ['id']);
    }
}
