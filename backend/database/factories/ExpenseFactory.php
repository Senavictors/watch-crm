<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use App\Support\ExpenseMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(ExpenseMetadata::CATEGORIES),
            'description' => fake()->sentence(4),
            'amount' => fake()->randomFloat(2, 20, 2000),
            'expense_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'created_by_user_id' => User::factory(),
        ];
    }
}
