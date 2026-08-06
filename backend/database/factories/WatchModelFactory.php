<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
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
                $model->quality_key = $this->resolveQualityKey($model);
            })
            ->afterCreating(function (WatchModel $model) {
                $model->quality_key = $this->resolveQualityKey($model);
                $model->save();
            });
    }

    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'quality_id' => Quality::factory(),
            'quality_key' => 0,
            'name' => fake()->unique()->word(),
            'image_path' => null,
        ];
    }

    public function box(): static
    {
        return $this->state(fn () => [
            'category_id' => Category::factory()->withoutQuality(),
            'quality_id' => null,
            'quality_key' => 0,
        ]);
    }

    private function resolveQualityKey(WatchModel $model): int
    {
        $hasQuality = Category::find($model->category_id)?->has_quality ?? true;

        return $hasQuality ? (int) $model->quality_id : 0;
    }
}
