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

    public function configure(): static
    {
        return $this
            ->afterMaking(function (WatchModel $model) {
                $model->quality_key = $model->product_type === WatchModel::TYPE_BOX
                    ? 0
                    : (int) $model->quality_id;
            })
            ->afterCreating(function (WatchModel $model) {
                $model->quality_key = $model->product_type === WatchModel::TYPE_BOX
                    ? 0
                    : (int) $model->quality_id;
                $model->save();
            });
    }

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'product_type' => WatchModel::TYPE_WATCH,
            'quality_id' => Quality::factory(),
            'quality_key' => 0,
            'name' => fake()->unique()->word(),
            'image_path' => null,
        ];
    }

    public function box(): static
    {
        return $this->state(fn () => [
            'product_type' => WatchModel::TYPE_BOX,
            'quality_id' => null,
            'quality_key' => 0,
        ]);
    }
}
