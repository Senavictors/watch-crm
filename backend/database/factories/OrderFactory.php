<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order) {
            if ($order->items()->exists()) {
                return;
            }

            $product = Product::query()->with(['brand', 'watchModel.quality', 'watchModel.category'])->find($order->product_id);

            if (! $product) {
                return;
            }

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $order->product_name,
                'product_type' => $product->watchModel?->category?->name ?? 'Relógios',
                'brand_name' => $product->brand?->name,
                'model_name' => $product->watchModel?->name,
                'quality_name' => $product->watchModel?->category?->has_quality
                    ? $product->watchModel?->quality?->name
                    : null,
                'quantity' => 1,
                'unit_price' => $order->sale_price,
                'unit_cost' => $order->cost,
                'unit_discount' => $order->discount,
            ]);
        });
    }

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
            'payment_expires_at' => null,
            'shipping_method' => 'Sedex',
            'tracking_code' => '',
            'sale_date' => now()->toDateString(),
            'shipped_date' => null,
            'notes' => '',
        ];
    }

    /**
     * Pedido com pagamento confirmado (TASK-003) — usado por testes que
     * criam pedidos "pagos" diretamente via factory, sem passar pelo
     * `OrderController` (que confirma o pagamento sozinho na transição).
     */
    public function paid(?User $confirmedBy = null): static
    {
        return $this->state(fn () => [
            'status' => 'Pago',
            'paid_at' => now(),
            'paid_by_user_id' => $confirmedBy?->id ?? User::factory(),
        ]);
    }
}
