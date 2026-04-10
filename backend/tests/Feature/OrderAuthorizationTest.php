<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_order_for_a_seller(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $boxProduct = Product::factory()->create([
            'brand_id' => $product->brand_id,
            'model_id' => WatchModel::factory()->box()->create([
                'brand_id' => $product->brand_id,
                'name' => 'Caixa Premium',
            ])->id,
            'cost' => 40,
            'price' => 90,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/orders', [
            'customerId' => $customer->id,
            'sellerUserId' => $seller->id,
            'items' => [
                [
                    'productId' => $product->id,
                    'quantity' => 1,
                    'unitPrice' => 250,
                    'unitDiscount' => 10,
                ],
                [
                    'productId' => $boxProduct->id,
                    'quantity' => 2,
                    'unitPrice' => 90,
                    'unitDiscount' => 5,
                ],
            ],
            'channel' => 'Instagram',
            'status' => 'Novo',
            'freight' => 20,
            'channelFee' => 0,
            'paymentMethod' => 'PIX',
            'shippingMethod' => 'Sedex',
            'trackingCode' => '',
            'saleDate' => now()->toDateString(),
            'shippedDate' => null,
            'notes' => 'Criado em teste',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('createdByUserId', $admin->id)
            ->assertJsonPath('sellerUserId', $seller->id)
            ->assertJsonPath('sellerUserName', $seller->name)
            ->assertJsonPath('salePrice', 430)
            ->assertJsonPath('cost', 180)
            ->assertJsonPath('discount', 20)
            ->assertJsonPath('itemsCount', 3)
            ->assertJsonPath('items.1.productType', 'BOX');
    }

    public function test_manager_can_update_any_order(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $order = Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Novo',
        ]);
        $newProduct = Product::factory()->create([
            'cost' => 120,
            'price' => 310,
        ]);

        $this->actingAs($manager)
            ->patchJson('/api/orders/'.$order->id, [
                'status' => 'Pago',
                'items' => [
                    [
                        'productId' => $newProduct->id,
                        'quantity' => 2,
                        'unitPrice' => 300,
                        'unitDiscount' => 15,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Pago')
            ->assertJsonPath('salePrice', 600)
            ->assertJsonPath('cost', 240)
            ->assertJsonPath('discount', 30)
            ->assertJsonPath('itemsCount', 2);
    }

    public function test_seller_cannot_create_or_update_orders(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create(['seller_user_id' => $seller->id]);

        $this->actingAs($seller)
            ->postJson('/api/orders', [
                'customerId' => $customer->id,
                'sellerUserId' => $seller->id,
                'items' => [
                    [
                        'productId' => $product->id,
                        'quantity' => 1,
                        'unitPrice' => 250,
                        'unitDiscount' => 0,
                    ],
                ],
                'channel' => 'Instagram',
                'shippingMethod' => 'Sedex',
                'saleDate' => now()->toDateString(),
            ])
            ->assertStatus(403);

        $this->actingAs($seller)
            ->patchJson('/api/orders/'.$order->id, [
                'status' => 'Pago',
            ])
            ->assertStatus(403);
    }

    public function test_seller_only_lists_assigned_orders(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $other = User::factory()->create(['role' => UserRole::Seller->value]);

        Order::factory()->create(['seller_user_id' => $seller->id]);
        Order::factory()->create(['seller_user_id' => $other->id]);

        $this->actingAs($seller)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.sellerUserId', $seller->id);
    }

    public function test_order_metadata_returns_assignable_sellers_and_catalogs(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value, 'name' => 'Josue Vendedor']);

        $this->actingAs($manager)
            ->getJson('/api/orders/metadata')
            ->assertOk()
            ->assertJsonPath('channels.0', 'Instagram')
            ->assertJsonPath('statuses.0', 'Novo')
            ->assertJsonFragment([
                'id' => $seller->id,
                'name' => $seller->name,
            ]);
    }
}
