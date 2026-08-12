<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\User;
use App\Support\CommissionCalculator;
use App\Support\ReturnItemResolver;
use App\Support\RevenueCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-028 — integridade da árvore devolução → pedido → item → produto
 * (achado 5).
 *
 * Antes desta task o backend só conferia que cada ID existia isoladamente e
 * gravava nome, categoria e preço vindos do request: dava para apontar a
 * devolução ao cliente errado, usar item de outro pedido, devolver mais do
 * que foi vendido e adulterar os snapshots que alimentam faturamento,
 * comissão e meta.
 */
class ReturnIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF = ['X-CSRF-TOKEN' => 'csrf-token'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
    }

    private function acting()
    {
        return $this->actingAs($this->admin)->withSession(['_token' => 'csrf-token']);
    }

    /**
     * @return array{0: Order, 1: OrderItem, 2: Customer}
     */
    private function soldOrder(int $quantity = 2, ?Customer $customer = null): array
    {
        $customer ??= Customer::factory()->create();
        $product = Product::factory()->create(['qty' => 10]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->admin->id,
            'seller_user_id' => $this->admin->id,
            'product_id' => $product->id,
            'product_name' => 'Relógio Vendido',
            'channel' => 'Instagram',
            'seller' => $this->admin->name,
            'status' => 'Pago',
            'sale_price' => 200 * $quantity,
            'cost' => 80 * $quantity,
            'discount' => 0,
            'freight' => 0,
            'channel_fee' => 0,
            'shipping_method' => 'Sedex',
            'sale_date' => now()->toDateString(),
            'paid_at' => now(),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Relógio Vendido',
            'product_type' => 'Relógios',
            'brand_name' => 'Marca Real',
            'model_name' => 'Modelo Real',
            'quantity' => $quantity,
            'unit_price' => 200,
            'unit_cost' => 80,
            'unit_commission' => 30,
            'unit_discount' => 0,
        ]);

        return [$order, $item, $customer];
    }

    // --- CA-01: combinações cruzadas ---

    public function test_cliente_diferente_do_cliente_do_pedido_e_recusado(): void
    {
        [$order, $item] = $this->soldOrder();
        $outroCliente = Customer::factory()->create();

        $this->acting()->postJson('/api/returns', [
            'orderId' => $order->id,
            'customerId' => $outroCliente->id,
            'type' => 'garantia',
            'items' => [['orderItemId' => $item->id, 'quantity' => 1]],
        ], self::CSRF)
            ->assertStatus(422)
            ->assertJsonPath('code', 'return_customer_mismatch');

        $this->assertSame(0, ProductReturn::count());
    }

    public function test_item_de_outro_pedido_e_recusado(): void
    {
        [$order, , $customer] = $this->soldOrder();
        [, $itemAlheio] = $this->soldOrder();

        $this->acting()->postJson('/api/returns', [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'garantia',
            'items' => [['orderItemId' => $itemAlheio->id, 'quantity' => 1]],
        ], self::CSRF)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, ProductReturn::count());
        $this->assertSame(0, ReturnItem::count());
    }

    public function test_devolucao_vinculada_exige_item_do_pedido(): void
    {
        [$order, , $customer] = $this->soldOrder();
        $produtoAvulso = Product::factory()->create();

        $this->acting()->postJson('/api/returns', [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'garantia',
            'items' => [['productId' => $produtoAvulso->id, 'quantity' => 1]],
        ], self::CSRF)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.orderItemId');
    }

    /** RN-06: sem pedido, o produto vem do catálogo. */
    public function test_devolucao_avulsa_exige_produto_do_catalogo(): void
    {
        $customer = Customer::factory()->create();

        $this->acting()->postJson('/api/returns', [
            'customerId' => $customer->id,
            'type' => 'garantia',
            'items' => [['quantity' => 1]],
        ], self::CSRF)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.productId');
    }

    public function test_devolucao_avulsa_nao_aceita_item_de_pedido(): void
    {
        [, $item] = $this->soldOrder();
        $customer = Customer::factory()->create();

        $this->acting()->postJson('/api/returns', [
            'customerId' => $customer->id,
            'type' => 'garantia',
            'items' => [['orderItemId' => $item->id, 'quantity' => 1]],
        ], self::CSRF)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.orderItemId');
    }

    public function test_trocar_o_pedido_vinculado_exige_reselecionar_itens(): void
    {
        [$order, $item, $customer] = $this->soldOrder();
        [$outroPedido] = $this->soldOrder(2, $customer);

        $created = $this->acting()->postJson('/api/returns', [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'garantia',
            'items' => [['orderItemId' => $item->id, 'quantity' => 1]],
        ], self::CSRF)->assertCreated();

        $this->acting()->patchJson("/api/returns/{$created->json('id')}", [
            'orderId' => $outroPedido->id,
        ], self::CSRF)
            ->assertStatus(422)
            ->assertJsonPath('code', 'return_items_require_reselection');
    }

    // --- CA-02: saldo devolvível ---

    public function test_quantidade_acima_da_vendida_e_recusada(): void
    {
        [$order, $item, $customer] = $this->soldOrder(2);

        $this->acting()->postJson('/api/returns', [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'devolucao',
            'items' => [['orderItemId' => $item->id, 'quantity' => 3]],
        ], self::CSRF)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, ProductReturn::count());
    }

    public function test_saldo_considera_devolucoes_anteriores(): void
    {
        [$order, $item, $customer] = $this->soldOrder(2);

        $payload = fn (int $quantity) => [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'devolucao',
            'items' => [['orderItemId' => $item->id, 'quantity' => $quantity]],
        ];

        $this->acting()->postJson('/api/returns', $payload(1), self::CSRF)->assertCreated();
        $this->acting()->postJson('/api/returns', $payload(1), self::CSRF)->assertCreated();

        // Vendidas 2, devolvidas 2 — a terceira não tem saldo.
        $this->acting()->postJson('/api/returns', $payload(1), self::CSRF)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    /** Repartir a quantidade em várias linhas não fura o saldo. */
    public function test_linhas_repetidas_do_mesmo_item_sao_somadas_antes_de_validar(): void
    {
        [$order, $item, $customer] = $this->soldOrder(2);

        $this->acting()->postJson('/api/returns', [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'devolucao',
            'items' => [
                ['orderItemId' => $item->id, 'quantity' => 1],
                ['orderItemId' => $item->id, 'quantity' => 1],
                ['orderItemId' => $item->id, 'quantity' => 1],
            ],
        ], self::CSRF)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_editar_a_propria_devolucao_nao_consome_o_proprio_saldo(): void
    {
        [$order, $item, $customer] = $this->soldOrder(2);

        $created = $this->acting()->postJson('/api/returns', [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'devolucao',
            'items' => [['orderItemId' => $item->id, 'quantity' => 2]],
        ], self::CSRF)->assertCreated();

        // Reenviar as mesmas 2 unidades na edição precisa continuar válido.
        $this->acting()->patchJson("/api/returns/{$created->json('id')}", [
            'items' => [['orderItemId' => $item->id, 'quantity' => 2]],
        ], self::CSRF)->assertOk();
    }

    /** ADR-007: devolução estornada devolve o saldo. */
    public function test_devolucao_estornada_libera_o_saldo(): void
    {
        [$order, $item, $customer] = $this->soldOrder(1);

        $payload = [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'devolucao',
            'items' => [['orderItemId' => $item->id, 'quantity' => 1]],
        ];

        $first = $this->acting()->postJson('/api/returns', $payload, self::CSRF)->assertCreated();

        $this->acting()->postJson('/api/returns', $payload, self::CSRF)->assertStatus(422);

        $this->acting()->patchJson("/api/returns/{$first->json('id')}/void", [
            'reason' => 'Registrada por engano.',
        ], self::CSRF)->assertOk();

        $this->acting()->postJson('/api/returns', $payload, self::CSRF)->assertCreated();
    }

    // --- CA-03: snapshots derivados, nunca confiados ---

    public function test_payload_adulterado_nao_altera_os_snapshots_persistidos(): void
    {
        [$order, $item, $customer] = $this->soldOrder(2);

        $response = $this->acting()->postJson('/api/returns', [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'devolucao',
            'items' => [[
                'orderItemId' => $item->id,
                'quantity' => 1,
                // Tudo abaixo é ignorado: alimentaria faturamento e comissão.
                'productName' => 'Rolex Submariner FALSIFICADO',
                'productType' => 'Categoria Inventada',
                'brandName' => 'Marca Falsa',
                'modelName' => 'Modelo Falso',
                'qualityName' => 'Qualidade Falsa',
                'unitPrice' => 999999,
                'productId' => Product::factory()->create()->id,
            ]],
        ], self::CSRF)->assertCreated();

        $persisted = ReturnItem::query()->firstOrFail();

        $this->assertSame('Relógio Vendido', $persisted->product_name);
        $this->assertSame('Relógios', $persisted->product_type);
        $this->assertSame('Marca Real', $persisted->brand_name);
        $this->assertSame('Modelo Real', $persisted->model_name);
        $this->assertEqualsWithDelta(200.0, (float) $persisted->unit_price, 0.001);
        $this->assertSame((int) $item->product_id, (int) $persisted->product_id);
        $this->assertSame($response->json('items.0.productName'), 'Relógio Vendido');
    }

    public function test_snapshot_avulso_vem_do_catalogo(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => 777]);

        $this->acting()->postJson('/api/returns', [
            'customerId' => $customer->id,
            'type' => 'garantia',
            'items' => [[
                'productId' => $product->id,
                'quantity' => 1,
                'productName' => 'Nome Inventado',
                'unitPrice' => 1,
            ]],
        ], self::CSRF)->assertCreated();

        $persisted = ReturnItem::query()->firstOrFail();

        $this->assertSame($product->displayLabel(), $persisted->product_name);
        $this->assertEqualsWithDelta(777.0, (float) $persisted->unit_price, 0.001);
        $this->assertNull($persisted->order_item_id);
    }

    // --- CA-05: calculadores continuam coerentes ---

    public function test_calculadores_seguem_coerentes_com_devolucao_valida(): void
    {
        [$order, $item, $customer] = $this->soldOrder(2);

        $this->assertEqualsWithDelta(400.0, RevenueCalculator::calculate($this->admin), 0.001);
        $this->assertEqualsWithDelta(60.0, CommissionCalculator::accrued($this->admin), 0.001);

        $created = $this->acting()->postJson('/api/returns', [
            'orderId' => $order->id,
            'customerId' => $customer->id,
            'type' => 'devolucao',
            'items' => [['orderItemId' => $item->id, 'quantity' => 1]],
        ], self::CSRF)->assertCreated();

        $this->acting()->patchJson("/api/returns/{$created->json('id')}", [
            'status' => 'Recebido',
        ], self::CSRF)->assertOk();
        $this->acting()->patchJson("/api/returns/{$created->json('id')}", [
            'status' => 'Em Análise',
        ], self::CSRF)->assertOk();
        $this->acting()->patchJson("/api/returns/{$created->json('id')}", [
            'status' => 'Reembolso Pendente',
        ], self::CSRF)->assertOk();
        $this->acting()->patchJson("/api/returns/{$created->json('id')}/refund", [
            'amount' => 200,
            'reason' => 'Uma unidade devolvida.',
        ], self::CSRF)->assertOk();

        // Faturamento cai pelo valor reembolsado; comissão cai pela unidade
        // devolvida — os dois usando o snapshot derivado da venda real.
        $this->assertEqualsWithDelta(200.0, RevenueCalculator::calculate($this->admin), 0.001);
        $this->assertEqualsWithDelta(30.0, CommissionCalculator::accrued($this->admin), 0.001);
    }

    // --- Unitário do saldo (estratégia de testes da ficha) ---

    public function test_saldo_devolvivel_desconta_apenas_devolucoes_efetivas(): void
    {
        [$order, $item] = $this->soldOrder(3);

        $this->assertSame(3, ReturnItemResolver::returnableQuantity($item));

        $productReturn = ProductReturn::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'created_by_user_id' => $this->admin->id,
            'type' => 'devolucao',
            'status' => 'Recebido',
        ]);
        ReturnItem::create([
            'return_id' => $productReturn->id,
            'order_item_id' => $item->id,
            'product_name' => 'Relógio Vendido',
            'product_type' => 'Relógios',
            'quantity' => 2,
            'unit_price' => 200,
        ]);

        $this->assertSame(1, ReturnItemResolver::returnableQuantity($item));
        // A própria devolução não consome o próprio saldo ao ser editada.
        $this->assertSame(3, ReturnItemResolver::returnableQuantity($item, $productReturn->id));

        $productReturn->voided_at = now();
        $productReturn->save();

        $this->assertSame(3, ReturnItemResolver::returnableQuantity($item));
    }
}
