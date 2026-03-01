<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['id' => 1, 'brand_id' => 1, 'model_id' => 1, 'cost' => 180, 'price' => 320, 'stock' => 'IN_STOCK', 'qty' => 5],
            ['id' => 2, 'brand_id' => 2, 'model_id' => 2, 'cost' => 220, 'price' => 420, 'stock' => 'IN_STOCK', 'qty' => 3],
            ['id' => 3, 'brand_id' => 3, 'model_id' => 3, 'cost' => 350, 'price' => 680, 'stock' => 'SUPPLIER', 'qty' => 0],
            ['id' => 4, 'brand_id' => 4, 'model_id' => 4, 'cost' => 480, 'price' => 890, 'stock' => 'IN_STOCK', 'qty' => 2],
            ['id' => 5, 'brand_id' => 5, 'model_id' => 5, 'cost' => 90, 'price' => 170, 'stock' => 'SUPPLIER', 'qty' => 0],
        ];
        Product::upsert($data, ['id']);
    }
}
