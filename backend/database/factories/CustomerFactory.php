<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('119########'),
            'email' => fake()->safeEmail(),
            'instagram' => '@'.fake()->userName(),
            'owner_user_id' => User::factory(),
        ];
    }
}
