<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Quality;
use App\Models\WatchModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $brand = Brand::factory()->create();
        $quality = Quality::factory()->create();
        $model = WatchModel::factory()->create([
            'brand_id' => $brand->id,
            'quality_id' => $quality->id,
        ]);

        return [
            'brand_id' => $brand->id,
            'model_id' => $model->id,
            'cost' => 100,
            'price' => 200,
            'stock' => 'IN_STOCK',
            'qty' => 5,
        ];
    }
}
