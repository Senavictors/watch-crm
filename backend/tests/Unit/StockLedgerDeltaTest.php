<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TASK-024 — cálculo de deltas de `StockLedger`, sem passar pela API.
 *
 * O foco aqui é a aritmética do sync declarativo: dado um estado atual
 * (`stock_reservations`) e um estado desejado (itens + pagamento), quais
 * deltas caem em `qty` e em `reserved_qty`, e qual tipo de movimento os
 * descreve.
 */
class StockLedgerDeltaTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actor = User::factory()->create();
        $this->customer = Customer::factory()->create();
    }

    private function order(Product $product, int $quantity, bool $paid = false, string $status = 'Novo'): Order
    {
        $order = Order::create([
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->actor->id,
            'seller_user_id' => $this->actor->id,
            'product_id' => $product->id,
            'product_name' => 'Teste',
            'channel' => 'Instagram',
            'seller' => $this->actor->name,
            'status' => $status,
            'sale_price' => 100 * $quantity,
            'cost' => 50 * $quantity,
            'discount' => 0,
            'freight' => 0,
            'channel_fee' => 0,
            'shipping_method' => 'Sedex',
            'sale_date' => now()->toDateString(),
            'paid_at' => $paid ? now() : null,
        ]);

        $this->setItems($order, [[$product, $quantity]]);

        return $order;
    }

    /** @param array<int, array{0: Product, 1: int}> $lines */
    private function setItems(Order $order, array $lines): void
    {
        $order->items()->delete();

        foreach ($lines as [$product, $quantity]) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => 'Teste',
                'product_type' => 'Relógios',
                'quantity' => $quantity,
                'unit_price' => 100,
                'unit_cost' => 50,
                'unit_discount' => 0,
            ]);
        }

        $order->load('items');
    }

    private function sync(Order $order): void
    {
        DB::transaction(fn () => StockLedger::syncOrder($order->load('items'), $this->actor));
    }

    public function test_reserva_move_apenas_reserved_qty(): void
    {
        $product = Product::factory()->create(['qty' => 10]);
        $order = $this->order($product, 3);

        $this->sync($order);

        $product->refresh();
        $this->assertSame(10, $product->qty);
        $this->assertSame(3, $product->reserved_qty);

        $movement = StockMovement::latest('id')->first();
        $this->assertSame(StockLedger::TYPE_RESERVE, $movement->type);
        $this->assertSame(0, $movement->qty_delta);
        $this->assertSame(3, $movement->reserved_delta);
    }

    public function test_baixa_move_qty_e_reserved_qty_no_mesmo_movimento(): void
    {
        $product = Product::factory()->create(['qty' => 10]);
        $order = $this->order($product, 3);
        $this->sync($order);

        $order->paid_at = now();
        $order->save();
        $this->sync($order);

        $product->refresh();
        $this->assertSame(7, $product->qty);
        $this->assertSame(0, $product->reserved_qty);

        $movement = StockMovement::latest('id')->first();
        $this->assertSame(StockLedger::TYPE_COMMIT, $movement->type);
        $this->assertSame(-3, $movement->qty_delta);
        $this->assertSame(-3, $movement->reserved_delta);
        $this->assertSame(7, $movement->qty_after);
    }

    public function test_aumentar_itens_de_pedido_ja_pago_baixa_somente_a_diferenca(): void
    {
        $product = Product::factory()->create(['qty' => 10]);
        $order = $this->order($product, 2, paid: true);
        $this->sync($order);
        $this->assertSame(8, $product->fresh()->qty);

        $this->setItems($order, [[$product, 5]]);
        $this->sync($order);

        $product->refresh();
        $this->assertSame(5, $product->qty);

        $movement = StockMovement::latest('id')->first();
        $this->assertSame(StockLedger::TYPE_COMMIT, $movement->type);
        $this->assertSame(-3, $movement->qty_delta, 'Só a diferença sai do saldo.');
    }

    public function test_sync_sem_mudanca_nao_gera_movimento(): void
    {
        $product = Product::factory()->create(['qty' => 10]);
        $order = $this->order($product, 2);

        $this->sync($order);
        $movementsAfterFirst = StockMovement::count();

        $this->sync($order);
        $this->sync($order);

        $this->assertSame($movementsAfterFirst, StockMovement::count(), 'Delta zero não vira linha no ledger.');
        $this->assertSame(2, $product->fresh()->reserved_qty);
    }

    public function test_liberacao_zera_a_reserva_e_marca_a_linha_como_released(): void
    {
        $product = Product::factory()->create(['qty' => 10]);
        $order = $this->order($product, 4);
        $this->sync($order);

        $order->status = 'Cancelado';
        $order->save();
        $this->sync($order);

        $product->refresh();
        $this->assertSame(10, $product->qty);
        $this->assertSame(0, $product->reserved_qty);

        $reservation = StockReservation::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(StockReservation::STATUS_RELEASED, $reservation->status);
        $this->assertSame(0, $reservation->quantity);

        $movement = StockMovement::latest('id')->first();
        $this->assertSame(StockLedger::TYPE_RELEASE, $movement->type);
        $this->assertSame(-4, $movement->reserved_delta);
    }

    public function test_cancelar_pedido_pago_devolve_ao_saldo_fisico(): void
    {
        $product = Product::factory()->create(['qty' => 10]);
        $order = $this->order($product, 4, paid: true);
        $this->sync($order);
        $this->assertSame(6, $product->fresh()->qty);

        $order->status = 'Cancelado';
        $order->save();
        $this->sync($order);

        $product->refresh();
        $this->assertSame(10, $product->qty);
        $this->assertSame(0, $product->reserved_qty);
        $this->assertSame(StockLedger::TYPE_UNCOMMIT, StockMovement::latest('id')->first()->type);
    }

    public function test_itens_do_mesmo_produto_sao_somados_em_uma_unica_reserva(): void
    {
        $product = Product::factory()->create(['qty' => 10]);
        $order = $this->order($product, 1);
        $this->setItems($order, [[$product, 1], [$product, 2], [$product, 3]]);

        $this->sync($order);

        $this->assertSame(6, $product->fresh()->reserved_qty);
        $this->assertSame(1, StockReservation::where('order_id', $order->id)->count());
        $this->assertSame(6, StockReservation::where('order_id', $order->id)->first()->quantity);
    }
}
