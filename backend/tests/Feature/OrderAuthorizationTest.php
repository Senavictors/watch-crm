<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
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
        // TASK-002 substituiu o tipo fixo WATCH/BOX por categorias
        // cadastráveis — "Caixas" é o nome real usado no catálogo (seedado
        // pela própria migration de `categories`, ver
        // docs/modules/catalog-categories.md), não um valor gerado pelo
        // factory, daí reaproveitar a categoria existente em vez de depender
        // do nome aleatório de `Category::factory()`.
        $boxCategory = Category::firstOrCreate(['name' => 'Caixas'], ['has_quality' => false]);
        $boxProduct = Product::factory()->create([
            'brand_id' => $product->brand_id,
            'model_id' => WatchModel::factory()->box()->create([
                'brand_id' => $product->brand_id,
                'category_id' => $boxCategory->id,
                'name' => 'Caixa Premium',
            ])->id,
            'cost' => 40,
            'price' => 90,
        ]);

        $response = $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($admin)
            ->postJson('/api/orders', [
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
            ], ['X-CSRF-TOKEN' => 'csrf-token']);

        $response
            ->assertCreated()
            ->assertJsonPath('createdByUserId', $admin->id)
            ->assertJsonPath('sellerUserId', $seller->id)
            ->assertJsonPath('sellerUserName', $seller->name)
            ->assertJsonPath('salePrice', 430)
            ->assertJsonPath('cost', 180)
            ->assertJsonPath('discount', 20)
            ->assertJsonPath('itemsCount', 3)
            ->assertJsonPath('items.1.productType', 'Caixas');
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

        // TASK-027 (ADR-008): o update genérico não confirma mais pagamento
        // — a mudança de status para "Pago" passa pelo endpoint dedicado.
        // Gerente mantém `orders.payment.confirm`, então continua fazendo as
        // duas coisas, agora por caminhos separados.
        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($manager)
            ->patchJson('/api/orders/'.$order->id, [
                'items' => [
                    [
                        'productId' => $newProduct->id,
                        'quantity' => 2,
                        'unitPrice' => 300,
                        'unitDiscount' => 15,
                    ],
                ],
            ], ['X-CSRF-TOKEN' => 'csrf-token'])
            ->assertOk();

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($manager)
            ->patchJson('/api/orders/'.$order->id.'/payment', [
                'confirmed' => true,
            ], ['X-CSRF-TOKEN' => 'csrf-token'])
            ->assertOk()
            ->assertJsonPath('status', 'Pago')
            ->assertJsonPath('salePrice', 600)
            // TASK-013: gerente perdeu dashboard.financial.view — não recebe
            // `cost` no payload (ver FinancialFieldVisibilityTest).
            ->assertJsonMissingPath('cost')
            ->assertJsonPath('discount', 30)
            ->assertJsonPath('itemsCount', 2)
            ->assertJsonPath('paidByUserId', $manager->id)
            ->assertJsonPath('paidByUserName', $manager->name);

        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_seller_cannot_create_or_update_orders(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create(['seller_user_id' => $seller->id]);

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($seller)
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
            ], ['X-CSRF-TOKEN' => 'csrf-token'])
            ->assertStatus(403);

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($seller)
            ->patchJson('/api/orders/'.$order->id, [
                'status' => 'Pago',
            ], ['X-CSRF-TOKEN' => 'csrf-token'])
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
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sellerUserId', $seller->id);
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
