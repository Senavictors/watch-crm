<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'has_quality' => true,
        ];
    }

    public function withoutQuality(): static
    {
        return $this->state(fn () => [
            'has_quality' => false,
        ]);
    }
}
