<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\InventoryValuationCalculator;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TASK-024 / ADR-006 — reserva, baixa, liberação e ledger de estoque.
 */
class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $seller;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $this->customer = Customer::factory()->create();
    }

    private function createOrder(array $items, array $overrides = [], ?User $actor = null)
    {
        return $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($actor ?? $this->admin)
            ->postJson('/api/orders', array_merge([
                'customerId' => $this->customer->id,
                'sellerUserId' => $this->seller->id,
                'items' => $items,
                'channel' => 'Instagram',
                'status' => 'Novo',
                'freight' => 0,
                'channelFee' => 0,
                'paymentMethod' => 'PIX',
                'shippingMethod' => 'Sedex',
                'trackingCode' => '',
                'saleDate' => now()->toDateString(),
                'shippedDate' => null,
                'notes' => null,
            ], $overrides), ['X-CSRF-TOKEN' => 'csrf-token']);
    }

    private function patchOrder(int $orderId, array $payload, ?User $actor = null)
    {
        return $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($actor ?? $this->admin)
            ->patchJson("/api/orders/{$orderId}", $payload, ['X-CSRF-TOKEN' => 'csrf-token']);
    }

    private function item(Product $product, int $quantity = 1): array
    {
        return [
            'productId' => $product->id,
            'quantity' => $quantity,
            'unitPrice' => 200,
            'unitDiscount' => 0,
        ];
    }

    public function test_criacao_reserva_sem_alterar_saldo_fisico(): void
    {
        $product = Product::factory()->create(['qty' => 5]);

        $this->createOrder([$this->item($product, 2)])->assertCreated();

        $product->refresh();
        $this->assertSame(5, $product->qty, 'A peça ainda está fisicamente na loja.');
        $this->assertSame(2, $product->reserved_qty);
        $this->assertSame(3, $product->availableQty());

        $this->assertDatabaseHas('stock_reservations', [
            'product_id' => $product->id,
            'quantity' => 2,
            'status' => StockReservation::STATUS_RESERVED,
        ]);
    }

    public function test_confirmacao_de_pagamento_converte_reserva_em_baixa(): void
    {
        $product = Product::factory()->create(['qty' => 5]);
        $orderId = $this->createOrder([$this->item($product, 2)])->json('id');

        $this->patchOrder($orderId, ['status' => 'Pago'])->assertOk();

        $product->refresh();
        $this->assertSame(3, $product->qty);
        $this->assertSame(0, $product->reserved_qty);
        $this->assertSame(3, $product->availableQty());

        $this->assertDatabaseHas('stock_reservations', [
            'order_id' => $orderId,
            'status' => StockReservation::STATUS_COMMITTED,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'order_id' => $orderId,
            'type' => StockLedger::TYPE_COMMIT,
            'qty_delta' => -2,
        ]);
    }

    public function test_reversao_de_pagamento_devolve_a_baixa_para_reserva(): void
    {
        $product = Product::factory()->create(['qty' => 5]);
        $orderId = $this->createOrder([$this->item($product, 2)], ['status' => 'Pago'])->json('id');

        $this->assertSame(3, $product->fresh()->qty);

        $this->patchOrder($orderId, ['status' => 'Aguardando Pagamento'])->assertOk();

        $product->refresh();
        $this->assertSame(5, $product->qty);
        $this->assertSame(2, $product->reserved_qty);
        $this->assertDatabaseHas('stock_movements', [
            'order_id' => $orderId,
            'type' => StockLedger::TYPE_UNCOMMIT,
            'qty_delta' => 2,
        ]);
    }

    /** CA-01 (caminho sequencial): esgotado o disponível, a venda seguinte é recusada. */
    public function test_segunda_venda_da_ultima_unidade_e_recusada(): void
    {
        $product = Product::factory()->create(['qty' => 1]);

        $this->createOrder([$this->item($product, 1)])->assertCreated();

        $response = $this->createOrder([$this->item($product, 1)]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('available', 0)
            ->assertJsonPath('requested', 1);

        $this->assertSame(1, Order::count(), 'A segunda venda não pode ter sido persistida.');
        $product->refresh();
        $this->assertSame(1, $product->qty);
        $this->assertSame(1, $product->reserved_qty);
    }

    /** CA-02: itens repetidos do mesmo produto são somados antes de validar. */
    public function test_itens_repetidos_do_mesmo_produto_sao_agregados(): void
    {
        $product = Product::factory()->create(['qty' => 2]);

        $response = $this->createOrder([
            $this->item($product, 1),
            $this->item($product, 1),
            $this->item($product, 1),
        ]);

        $response->assertStatus(422)->assertJsonPath('requested', 3);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $product->fresh()->reserved_qty);
    }

    public function test_edicao_reduzindo_quantidade_libera_a_diferenca(): void
    {
        $product = Product::factory()->create(['qty' => 5]);
        $orderId = $this->createOrder([$this->item($product, 4)])->json('id');
        $this->assertSame(4, $product->fresh()->reserved_qty);

        $this->patchOrder($orderId, ['items' => [$this->item($product, 1)]])->assertOk();

        $this->assertSame(1, $product->fresh()->reserved_qty);
    }

    public function test_edicao_removendo_produto_libera_reserva_daquele_produto(): void
    {
        $productA = Product::factory()->create(['qty' => 5]);
        $productB = Product::factory()->create(['qty' => 5]);

        $orderId = $this->createOrder([
            $this->item($productA, 2),
            $this->item($productB, 1),
        ])->json('id');

        $this->patchOrder($orderId, ['items' => [$this->item($productB, 1)]])->assertOk();

        $this->assertSame(0, $productA->fresh()->reserved_qty);
        $this->assertSame(1, $productB->fresh()->reserved_qty);
    }

    /** CA-03: reprocessar cancelamento não libera duas vezes. */
    public function test_cancelamento_libera_uma_unica_vez_mesmo_reprocessado(): void
    {
        $product = Product::factory()->create(['qty' => 5]);
        $orderId = $this->createOrder([$this->item($product, 3)], ['status' => 'Pago'])->json('id');
        $this->assertSame(2, $product->fresh()->qty);

        $this->patchOrder($orderId, ['status' => 'Cancelado'])->assertOk();
        $product->refresh();
        $this->assertSame(5, $product->qty);
        $this->assertSame(0, $product->reserved_qty);

        // Reprocessamento: mesma operação aplicada de novo, direto no ledger.
        $order = Order::with('items')->find($orderId);
        DB::transaction(fn () => StockLedger::syncOrder($order, $this->admin));
        StockLedger::syncOrder($order, $this->admin);

        $product->refresh();
        $this->assertSame(5, $product->qty, 'Reposição não pode ser duplicada.');
        $this->assertSame(0, $product->reserved_qty);
    }

    /** RN-04: cancelar um pedido não devolve o que outro pedido segura. */
    public function test_cancelamento_libera_somente_o_que_o_proprio_pedido_reservou(): void
    {
        $product = Product::factory()->create(['qty' => 5]);
        $first = $this->createOrder([$this->item($product, 2)])->json('id');
        $this->createOrder([$this->item($product, 1)])->assertCreated();

        $this->assertSame(3, $product->fresh()->reserved_qty);

        $this->patchOrder($first, ['status' => 'Cancelado'])->assertOk();

        $product->refresh();
        $this->assertSame(1, $product->reserved_qty, 'Só as 2 unidades do primeiro pedido saem.');
        $this->assertSame(4, $product->availableQty());
    }

    public function test_exclusao_do_pedido_libera_a_reserva(): void
    {
        $product = Product::factory()->create(['qty' => 5]);
        $orderId = $this->createOrder([$this->item($product, 2)])->json('id');

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($this->admin)
            ->deleteJson("/api/orders/{$orderId}", [], ['X-CSRF-TOKEN' => 'csrf-token'])
            ->assertOk();

        $product->refresh();
        $this->assertSame(5, $product->qty);
        $this->assertSame(0, $product->reserved_qty);
        // O ledger sobrevive à exclusão do pedido (sem FK, ADR-006 item 5).
        $this->assertTrue(StockMovement::where('order_id', $orderId)->exists());
    }

    /** CA-06: produto de fornecedor não valida nem consome estoque local. */
    public function test_produto_supplier_nao_consome_estoque_local(): void
    {
        $supplier = Product::factory()->create(['stock' => 'SUPPLIER', 'qty' => 0]);

        $this->createOrder([$this->item($supplier, 10)])->assertCreated();

        $supplier->refresh();
        $this->assertSame(0, $supplier->qty);
        $this->assertSame(0, $supplier->reserved_qty);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    /** RN-05 / ADR-006 item 4: devolução não repõe estoque automaticamente. */
    public function test_devolucao_nao_repoe_estoque_automaticamente(): void
    {
        $product = Product::factory()->create(['qty' => 3]);
        $orderId = $this->createOrder([$this->item($product, 1)], ['status' => 'Pago'])->json('id');
        $this->assertSame(2, $product->fresh()->qty);

        $return = $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($this->admin)
            ->postJson('/api/returns', [
                'orderId' => $orderId,
                'customerId' => $this->customer->id,
                'type' => 'devolucao',
                'items' => [[
                    'productId' => $product->id,
                    'productName' => 'Relógio',
                    'productType' => 'Relógios',
                    'quantity' => 1,
                    'unitPrice' => 200,
                ]],
            ], ['X-CSRF-TOKEN' => 'csrf-token']);

        $return->assertCreated();

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($this->admin)
            ->patchJson("/api/returns/{$return->json('id')}", ['status' => 'Recebido'], ['X-CSRF-TOKEN' => 'csrf-token'])
            ->assertOk();

        $this->assertSame(1, ProductReturn::count());
        $this->assertSame(2, $product->fresh()->qty, 'Reentrada é decisão manual do catálogo.');
    }

    /** CA-05: a reentrada manual (caminho real da devolução) fica no ledger. */
    public function test_entrada_manual_registra_movimento_auditavel(): void
    {
        $product = Product::factory()->create(['qty' => 2]);

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($this->admin)
            ->patchJson("/api/products/{$product->id}/add-qty", ['qty' => 3], ['X-CSRF-TOKEN' => 'csrf-token'])
            ->assertOk()
            ->assertJsonPath('qty', 5)
            ->assertJsonPath('availableQty', 5);

        $movement = StockMovement::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(StockLedger::TYPE_MANUAL_ENTRY, $movement->type);
        $this->assertSame(3, $movement->qty_delta);
        $this->assertSame(5, $movement->qty_after);
        $this->assertSame($this->admin->id, (int) $movement->actor_user_id);
        $this->assertNotEmpty($movement->idempotency_key);
    }

    /** RN-02: edição manual não pode derrubar o saldo abaixo do reservado. */
    public function test_reducao_manual_abaixo_do_reservado_e_recusada(): void
    {
        $product = Product::factory()->create(['qty' => 5]);
        $this->createOrder([$this->item($product, 4)])->assertCreated();

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($this->admin)
            ->patchJson("/api/products/{$product->id}", ['qty' => 1], ['X-CSRF-TOKEN' => 'csrf-token'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'reserved_stock_conflict');

        $this->assertSame(5, $product->fresh()->qty);
    }

    /** CA-04: a avaliação de estoque acompanha venda, liberação e reposição. */
    public function test_avaliacao_de_estoque_reflete_venda_liberacao_e_reposicao(): void
    {
        $product = Product::factory()->create(['qty' => 4, 'cost' => 100, 'price' => 250]);

        $this->assertSame(4, InventoryValuationCalculator::calculate()->totalUnits);

        $orderId = $this->createOrder([$this->item($product, 2)], ['status' => 'Pago'])->json('id');
        $this->assertSame(2, InventoryValuationCalculator::calculate()->totalUnits, 'Venda reduz o estoque avaliado.');
        $this->assertSame(200.0, InventoryValuationCalculator::calculate()->totalCost);

        $this->patchOrder($orderId, ['status' => 'Cancelado'])->assertOk();
        $this->assertSame(4, InventoryValuationCalculator::calculate()->totalUnits, 'Liberação devolve as unidades.');

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($this->admin)
            ->patchJson("/api/products/{$product->id}/add-qty", ['qty' => 1], ['X-CSRF-TOKEN' => 'csrf-token'])
            ->assertOk();

        $valuation = InventoryValuationCalculator::calculate();
        $this->assertSame(5, $valuation->totalUnits);
        $this->assertSame(1250.0, $valuation->totalPotentialRevenue);
    }

    /** RN-06: origem, ator, quantidade e chave de idempotência em todo movimento. */
    public function test_todo_movimento_tem_origem_ator_e_chave_de_idempotencia(): void
    {
        $product = Product::factory()->create(['qty' => 5]);
        $orderId = $this->createOrder([$this->item($product, 2)])->json('id');
        $this->patchOrder($orderId, ['status' => 'Pago'])->assertOk();

        $movements = StockMovement::where('order_id', $orderId)->orderBy('id')->get();

        $this->assertCount(2, $movements);
        $this->assertSame(
            [StockLedger::TYPE_RESERVE, StockLedger::TYPE_COMMIT],
            $movements->pluck('type')->all(),
        );

        foreach ($movements as $movement) {
            $this->assertSame($orderId, (int) $movement->order_id);
            $this->assertSame($this->admin->id, (int) $movement->actor_user_id);
            $this->assertGreaterThan(0, $movement->quantity);
            $this->assertNotEmpty($movement->idempotency_key);
        }

        $this->assertSame(
            $movements->count(),
            $movements->pluck('idempotency_key')->unique()->count(),
            'Chaves de idempotência precisam ser únicas.',
        );
    }
}
