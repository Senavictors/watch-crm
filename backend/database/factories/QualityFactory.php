<?php

namespace Database\Factories;

use App\Models\Quality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quality>
 */
class QualityFactory extends Factory
{
    protected $model = Quality::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
