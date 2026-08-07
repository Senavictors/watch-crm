<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quality;
use App\Models\User;
use App\Models\WatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * TASK-010: filtros de categoria e período (`from`/`to`) em `GET /api/orders`.
 */
class OrderFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_filter_returns_orders_with_at_least_one_matching_item(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();

        // "Relógios"/"Caixas" já vêm seedados (ver OrderAuthorizationTest) —
        // reaproveita em vez de tentar recriar e colidir com o unique de nome.
        $watchCategory = Category::firstOrCreate(['name' => 'Relógios'], ['has_quality' => true]);
        $boxCategory = Category::firstOrCreate(['name' => 'Caixas'], ['has_quality' => false]);

        $watchProduct = Product::factory()->create([
            'model_id' => WatchModel::factory()->create([
                'category_id' => $watchCategory->id,
                'quality_id' => Quality::factory()->create()->id,
            ])->id,
        ]);

        $boxProduct = Product::factory()->create([
            'model_id' => WatchModel::factory()->box()->create([
                'category_id' => $boxCategory->id,
            ])->id,
        ]);

        // Pedido multi-item: um item Relógios + um item Caixas (RN-02).
        $multiItemOrder = $this->createOrder($admin, $customer, $seller, [
            ['productId' => $watchProduct->id, 'quantity' => 1, 'unitPrice' => 500, 'unitDiscount' => 0],
            ['productId' => $boxProduct->id, 'quantity' => 1, 'unitPrice' => 90, 'unitDiscount' => 0],
        ]);

        // Pedido só com item Caixas — não deve aparecer no filtro por Relógios.
        $boxOnlyOrder = $this->createOrder($admin, $customer, $seller, [
            ['productId' => $boxProduct->id, 'quantity' => 1, 'unitPrice' => 90, 'unitDiscount' => 0],
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/orders?category=Relógios')
            ->assertOk();

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertContains($multiItemOrder['id'], $ids);
        $this->assertNotContains($boxOnlyOrder['id'], $ids);
    }

    public function test_period_filter_uses_paid_at_and_excludes_orders_without_it(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $withinRange = Order::factory()->paid()->create([
            'paid_at' => Carbon::parse('2026-03-10 12:00:00'),
        ]);

        $outsideRange = Order::factory()->paid()->create([
            'paid_at' => Carbon::parse('2026-05-01 12:00:00'),
        ]);

        $unpaid = Order::factory()->create([
            'status' => 'Novo',
            'paid_at' => null,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/orders?from=2026-03-01&to=2026-03-31')
            ->assertOk();

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertContains($withinRange->id, $ids);
        $this->assertNotContains($outsideRange->id, $ids);
        $this->assertNotContains($unpaid->id, $ids);
    }

    public function test_seller_ownership_is_preserved_when_combined_with_category_and_period_filters(): void
    {
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);

        $watchCategory = Category::firstOrCreate(['name' => 'Relógios'], ['has_quality' => true]);
        $product = Product::factory()->create([
            'model_id' => WatchModel::factory()->create([
                'category_id' => $watchCategory->id,
                'quality_id' => Quality::factory()->create()->id,
            ])->id,
        ]);

        $orderA = Order::factory()->paid()->create([
            'seller_user_id' => $sellerA->id,
            'product_id' => $product->id,
            'paid_at' => Carbon::parse('2026-03-10 12:00:00'),
        ]);

        // Pedido de outro vendedor, com os mesmos critérios de categoria/período —
        // não pode aparecer para o vendedor A (RN-03).
        $orderB = Order::factory()->paid()->create([
            'seller_user_id' => $sellerB->id,
            'product_id' => $product->id,
            'paid_at' => Carbon::parse('2026-03-15 12:00:00'),
        ]);

        $response = $this->actingAs($sellerA)
            ->getJson('/api/orders?category=Relógios&from=2026-03-01&to=2026-03-31')
            ->assertOk();

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertContains($orderA->id, $ids);
        $this->assertNotContains($orderB->id, $ids);
    }

    public function test_to_before_from_returns_422(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $this->actingAs($admin)
            ->getJson('/api/orders?from=2026-03-31&to=2026-03-01')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Período inválido: data final anterior à inicial.');
    }

    private function createOrder(User $actor, Customer $customer, User $seller, array $items): array
    {
        return $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($actor)
            ->postJson('/api/orders', [
                'customerId' => $customer->id,
                'sellerUserId' => $seller->id,
                'items' => $items,
                'channel' => 'Instagram',
                'status' => 'Novo',
                'shippingMethod' => 'Sedex',
                'saleDate' => now()->toDateString(),
            ], ['X-CSRF-TOKEN' => 'csrf-token'])
            ->assertCreated()
            ->json();
    }
}
