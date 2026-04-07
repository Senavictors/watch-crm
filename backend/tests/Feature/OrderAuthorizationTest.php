<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
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

        $response = $this->actingAs($admin)->postJson('/api/orders', [
            'customerId' => $customer->id,
            'sellerUserId' => $seller->id,
            'productId' => $product->id,
            'channel' => 'Instagram',
            'status' => 'Novo',
            'salePrice' => 250,
            'discount' => 10,
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
            ->assertJsonPath('sellerUserName', $seller->name);
    }

    public function test_manager_can_update_any_order(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $order = Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Novo',
        ]);

        $this->actingAs($manager)
            ->patchJson('/api/orders/'.$order->id, [
                'status' => 'Pago',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Pago');
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
                'productId' => $product->id,
                'channel' => 'Instagram',
                'salePrice' => 250,
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
