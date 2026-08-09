<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-005 — comissão configurável por produto (RN-01: valor fixo por
 * unidade). `commissionAmount` é sempre visível a quem tem `products.view`
 * (inclui vendedor) — não é dado de custo/margem, ver ProductController.
 */
class ProductCommissionTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public function test_product_can_be_created_with_a_commission_amount(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $model = WatchModel::factory()->create(['brand_id' => $brand->id]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/products', [
                'brandId' => $brand->id,
                'modelId' => $model->id,
                'cost' => 480,
                'price' => 1050,
                'commissionAmount' => 40,
                'stock' => 'IN_STOCK',
                'qty' => 3,
            ], self::CSRF_HEADERS)
            ->assertCreated()
            ->assertJsonPath('commissionAmount', 40);
    }

    public function test_commission_amount_is_optional_and_defaults_to_null(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $product->id, 'commissionAmount' => null]);
    }

    public function test_seller_sees_commission_amount_on_catalog(): void
    {
        // Diferente de `cost`, a comissão não é um dado financeiro sigiloso —
        // é quanto o próprio vendedor ganha por unidade.
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $product = Product::factory()->create(['commission_amount' => 150]);

        $response = $this->actingAs($seller)->getJson('/api/products')->assertOk();
        $payload = collect($response->json('data'))->firstWhere('id', $product->id);

        $this->assertEqualsWithDelta(150.0, $payload['commissionAmount'], 0.001);
        $this->assertArrayNotHasKey('cost', $payload);
    }

    public function test_commission_amount_can_be_updated_independently(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $product = Product::factory()->create(['commission_amount' => 40]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/products/'.$product->id, [
                'commissionAmount' => 150,
            ], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('commissionAmount', 150);
    }

    public function test_negative_commission_amount_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $model = WatchModel::factory()->create(['brand_id' => $brand->id]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/products', [
                'brandId' => $brand->id,
                'modelId' => $model->id,
                'cost' => 100,
                'price' => 250,
                'commissionAmount' => -5,
                'stock' => 'IN_STOCK',
                'qty' => 3,
            ], self::CSRF_HEADERS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('commissionAmount');
    }

    public function test_new_order_item_snapshots_the_products_current_commission(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = \App\Models\Customer::factory()->create();
        $product = Product::factory()->create(['commission_amount' => 40]);

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/orders', [
                'customerId' => $customer->id,
                'sellerUserId' => $seller->id,
                'items' => [
                    ['productId' => $product->id, 'quantity' => 2, 'unitPrice' => 1050, 'unitDiscount' => 0],
                ],
                'channel' => 'Instagram',
                'shippingMethod' => 'Sedex',
                'saleDate' => now()->toDateString(),
            ], self::CSRF_HEADERS)
            ->assertCreated();

        $orderId = $response->json('id');
        $item = OrderItem::where('order_id', $orderId)->first();
        $this->assertEqualsWithDelta(40.0, (float) $item->unit_commission, 0.001);
    }

    public function test_changing_product_commission_later_does_not_affect_existing_order_items(): void
    {
        // CA-01
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $product = Product::factory()->create(['commission_amount' => 40]);
        $order = Order::factory()->create();
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Produto teste',
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 1050,
            'unit_cost' => $product->cost,
            'unit_commission' => 40,
            'unit_discount' => 0,
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/products/'.$product->id, ['commissionAmount' => 999], self::CSRF_HEADERS)
            ->assertOk();

        $this->assertEqualsWithDelta(40.0, (float) $item->fresh()->unit_commission, 0.001);
    }
}
