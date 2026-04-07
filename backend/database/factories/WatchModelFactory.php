<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Quality;
use App\Models\WatchModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchModel>
 */
class WatchModelFactory extends Factory
{
    protected $model = WatchModel::class;

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'quality_id' => Quality::factory(),
            'name' => fake()->unique()->word(),
            'image_path' => null,
        ];
    }
}
