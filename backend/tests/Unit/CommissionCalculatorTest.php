<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\User;
use App\Support\CommissionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-005 — Implementar comissões por produto e venda.
 * CA-01: alteração futura do produto não muda pedidos antigos (snapshot).
 * RN da comissão (docs/domain/financial-rules.md): cancelamento/devolução
 * remove a parcela correspondente.
 */
class CommissionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin->value]);
    }

    private function seller(): User
    {
        return User::factory()->create(['role' => UserRole::Seller->value]);
    }

    private function orderWithItem(User $seller, array $orderAttrs, array $itemAttrs): Order
    {
        $order = Order::factory()->create(array_merge(['seller_user_id' => $seller->id], $orderAttrs));
        $order->items()->delete();
        OrderItem::create(array_merge([
            'order_id' => $order->id,
            'product_name' => 'Produto teste',
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 100,
            'unit_cost' => 50,
            'unit_commission' => 0,
            'unit_discount' => 0,
        ], $itemAttrs));

        return $order->fresh();
    }

    public function test_accrued_sums_unit_commission_times_quantity_for_paid_non_cancelled_orders(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();

        $this->orderWithItem($seller, ['status' => 'Pago', 'paid_at' => now()], ['unit_commission' => 40, 'quantity' => 2]);

        $this->assertEqualsWithDelta(80.0, CommissionCalculator::accrued($admin), 0.001);
    }

    public function test_cancelled_order_does_not_accrue_commission(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();

        // Pago e depois cancelado: paid_at preservado, mas status Cancelado
        // exclui explicitamente (mesmo critério de RevenueCalculator).
        $this->orderWithItem($seller, ['status' => 'Cancelado', 'paid_at' => now()], ['unit_commission' => 150, 'quantity' => 1]);

        $this->assertEqualsWithDelta(0.0, CommissionCalculator::accrued($admin), 0.001);
    }

    public function test_unpaid_order_does_not_accrue_commission(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();

        $this->orderWithItem($seller, ['status' => 'Novo', 'paid_at' => null], ['unit_commission' => 150, 'quantity' => 1]);

        $this->assertEqualsWithDelta(0.0, CommissionCalculator::accrued($admin), 0.001);
    }

    public function test_completed_refund_removes_the_corresponding_commission_share(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $customer = \App\Models\Customer::factory()->create();

        $order = $this->orderWithItem($seller, ['status' => 'Pago', 'paid_at' => now()], [
            'unit_commission' => 40,
            'quantity' => 3,
        ]);
        $item = $order->items->first();

        $return = ProductReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'type' => 'devolucao',
            'status' => 'Reembolso Efetuado',
        ]);
        ReturnItem::create([
            'return_id' => $return->id,
            'order_item_id' => $item->id,
            'product_name' => $item->product_name,
            'product_type' => $item->product_type,
            'quantity' => 1,
            'unit_price' => $item->unit_price,
        ]);

        // 3 unidades vendidas, 1 devolvida e reembolsada: (3-1) * 40 = 80.
        $this->assertEqualsWithDelta(80.0, CommissionCalculator::accrued($admin), 0.001);
    }

    public function test_pending_refund_does_not_remove_commission_yet(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $customer = \App\Models\Customer::factory()->create();

        $order = $this->orderWithItem($seller, ['status' => 'Pago', 'paid_at' => now()], [
            'unit_commission' => 40,
            'quantity' => 2,
        ]);
        $item = $order->items->first();

        $return = ProductReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'type' => 'devolucao',
            'status' => 'Reembolso Pendente',
        ]);
        ReturnItem::create([
            'return_id' => $return->id,
            'order_item_id' => $item->id,
            'product_name' => $item->product_name,
            'product_type' => $item->product_type,
            'quantity' => 1,
            'unit_price' => $item->unit_price,
        ]);

        $this->assertEqualsWithDelta(80.0, CommissionCalculator::accrued($admin), 0.001);
    }

    public function test_seller_scope_only_aggregates_own_commission(): void
    {
        $seller = $this->seller();
        $otherSeller = $this->seller();

        $this->orderWithItem($seller, ['status' => 'Pago', 'paid_at' => now()], ['unit_commission' => 40, 'quantity' => 1]);
        $this->orderWithItem($otherSeller, ['status' => 'Pago', 'paid_at' => now()], ['unit_commission' => 150, 'quantity' => 1]);

        $this->assertEqualsWithDelta(40.0, CommissionCalculator::accrued($seller), 0.001);
    }

    public function test_report_summary_separates_paid_from_pending(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $payer = $this->admin();

        $order = $this->orderWithItem($seller, ['status' => 'Pago', 'paid_at' => now()], ['unit_commission' => 40, 'quantity' => 1]);
        $item = $order->items->first();
        $item->update(['commission_paid_at' => now(), 'commission_paid_by_user_id' => $payer->id]);

        $this->orderWithItem($seller, ['status' => 'Pago', 'paid_at' => now()], ['unit_commission' => 150, 'quantity' => 1]);

        $summary = CommissionCalculator::report($admin)['summary'];

        $this->assertEqualsWithDelta(190.0, $summary['accrued'], 0.001);
        $this->assertEqualsWithDelta(40.0, $summary['paid'], 0.001);
        $this->assertEqualsWithDelta(150.0, $summary['pending'], 0.001);
    }

    public function test_changing_product_commission_does_not_affect_already_snapshotted_items(): void
    {
        // CA-01: o snapshot vive em order_items.unit_commission, não é
        // recalculado a partir do catálogo.
        $admin = $this->admin();
        $seller = $this->seller();
        $product = Product::factory()->create(['commission_amount' => 40]);

        $order = $this->orderWithItem($seller, ['status' => 'Pago', 'paid_at' => now()], [
            'product_id' => $product->id,
            'unit_commission' => 40,
            'quantity' => 1,
        ]);

        $product->update(['commission_amount' => 999]);

        $this->assertEqualsWithDelta(40.0, $order->items->first()->fresh()->unit_commission, 0.001);
        $this->assertEqualsWithDelta(40.0, CommissionCalculator::accrued($admin), 0.001);
    }
}
