<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'created_by_user_id' => User::factory(),
            'seller_user_id' => User::factory()->state(['role' => 'vendedor']),
            'product_id' => Product::factory(),
            'product_name' => fake()->word(),
            'channel' => 'Instagram',
            'seller' => 'Josue Vendedor',
            'status' => 'Novo',
            'sale_price' => 100,
            'cost' => 70,
            'discount' => 0,
            'freight' => 10,
            'channel_fee' => 0,
            'payment_method' => 'PIX',
            'shipping_method' => 'Sedex',
            'tracking_code' => '',
            'sale_date' => now()->toDateString(),
            'shipped_date' => null,
            'notes' => '',
        ];
    }
}
