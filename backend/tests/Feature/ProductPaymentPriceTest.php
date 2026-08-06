<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use App\Models\WatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-004 — Suportar preços por forma de pagamento.
 * CA-01: catálogo aceita os dois preços (pricePix/priceCard), opcionais.
 */
class ProductPaymentPriceTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public function test_product_can_be_created_with_pix_and_card_prices(): void
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
                'pricePix' => 230,
                'priceCard' => 270,
                'stock' => 'IN_STOCK',
                'qty' => 3,
            ], self::CSRF_HEADERS)
            ->assertCreated()
            ->assertJsonPath('price', 250)
            ->assertJsonPath('pricePix', 230)
            ->assertJsonPath('priceCard', 270);
    }

    public function test_pix_and_card_prices_are_optional_and_default_to_null(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $brand = Brand::factory()->create();
        $model = WatchModel::factory()->create(['brand_id' => $brand->id]);

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/products', [
                'brandId' => $brand->id,
                'modelId' => $model->id,
                'cost' => 100,
                'price' => 250,
                'stock' => 'IN_STOCK',
                'qty' => 3,
            ], self::CSRF_HEADERS)
            ->assertCreated()
            ->assertJsonPath('pricePix', null)
            ->assertJsonPath('priceCard', null);

        // RN-02: mudar o catálogo depois não deve exigir migração de vendas —
        // aqui só confirmamos que o produto legado (sem preços por forma de
        // pagamento) continua persistindo normalmente.
        $this->assertDatabaseHas('products', [
            'id' => $response->json('id'),
            'price_pix' => null,
            'price_card' => null,
        ]);
    }

    public function test_pix_and_card_prices_can_be_updated_independently(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $product = Product::factory()->create(['price' => 250, 'price_pix' => 230, 'price_card' => 270]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/products/'.$product->id, [
                'priceCard' => 280,
            ], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('pricePix', 230)
            ->assertJsonPath('priceCard', 280);
    }

    public function test_negative_pix_or_card_price_is_rejected(): void
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
                'pricePix' => -10,
                'stock' => 'IN_STOCK',
                'qty' => 3,
            ], self::CSRF_HEADERS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('pricePix');
    }

    public function test_existing_order_item_price_is_unaffected_by_later_catalog_price_changes(): void
    {
        // RN-02: preço efetivo já congelado no item (OrderItem.unit_price)
        // não deve mudar quando o catálogo é atualizado depois da venda.
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $product = Product::factory()->create(['price' => 250, 'price_pix' => 230, 'price_card' => 270]);

        $order = \App\Models\Order::factory()->create();
        $item = \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Produto de teste',
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 230,
            'unit_cost' => $product->cost,
            'unit_discount' => 0,
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/products/'.$product->id, [
                'pricePix' => 199,
            ], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('pricePix', 199);

        $this->assertEqualsWithDelta(230.0, $item->fresh()->unit_price, 0.001);
    }
}
