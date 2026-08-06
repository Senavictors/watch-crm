<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Goal;
use App\Models\GoalInterval;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\User;
use App\Support\GoalProgressCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-003 — CA-02: metas passam a usar `paid_at` (não mais só o status
 * diferente de `Cancelado`) para decidir se um pedido conta para a meta.
 * TASK-008 (RN-02, docs/regras-de-negocio-dashboard.md #10): reembolso
 * efetivado reduz proporcionalmente o progresso da meta.
 */
class GoalProgressCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function goalWithInterval(): Goal
    {
        $creator = User::factory()->create();

        $goal = Goal::create([
            'created_by_user_id' => $creator->id,
            'name' => 'Meta de teste',
            'scope' => 'company',
            'calculation_type' => 'total_value',
            'period_cycle' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'active',
        ]);

        GoalInterval::create([
            'goal_id' => $goal->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'target_value' => 1000,
        ]);

        return $goal;
    }

    public function test_only_paid_orders_count_towards_the_goal(): void
    {
        $goal = $this->goalWithInterval();

        // Pago (paid_at preenchido): conta.
        Order::factory()->create([
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_date' => '2026-08-10',
        ]);

        // Status pago mas sem confirmação registrada: não conta (CA-02).
        Order::factory()->create([
            'status' => 'Pago',
            'paid_at' => null,
            'sale_date' => '2026-08-11',
        ]);

        // Ainda não pago: não conta.
        Order::factory()->create([
            'status' => 'Aguardando Pagamento',
            'paid_at' => null,
            'sale_date' => '2026-08-12',
        ]);

        // Pago e depois cancelado: não conta (status Cancelado é excluído
        // mesmo com paid_at preenchido).
        Order::factory()->create([
            'status' => 'Cancelado',
            'paid_at' => now(),
            'sale_date' => '2026-08-13',
        ]);

        $intervals = GoalProgressCalculator::calculate($goal);

        // Item da fábrica: sale_price=100, discount=0 -> 100.
        $this->assertEqualsWithDelta(100.0, $intervals->first()['currentValue'], 0.001);
    }

    public function test_goal_interval_uses_paid_at_not_sale_date(): void
    {
        // TASK-009 (RN-01): pedido vendido dentro do intervalo mas confirmado
        // como pago fora dele não conta pra meta desse intervalo.
        $goal = $this->goalWithInterval();

        Order::factory()->create([
            'status' => 'Pago',
            'sale_date' => '2026-08-15',
            'paid_at' => '2026-09-01',
            'sale_price' => 500,
            'discount' => 0,
        ]);

        $intervals = GoalProgressCalculator::calculate($goal);

        $this->assertEqualsWithDelta(0.0, $intervals->first()['currentValue'], 0.001);
    }

    public function test_effective_refund_reduces_goal_progress_proportionally_by_returned_quantity(): void
    {
        // TASK-008 RN-02: reembolso efetivado reduz a meta só pelos itens
        // efetivamente devolvidos, não o pedido inteiro.
        $goal = $this->goalWithInterval();
        $customer = Customer::factory()->create();

        $order = Order::factory()->create([
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_date' => '2026-08-10',
        ]);
        $item = $order->items->first();
        $item->update(['unit_price' => 100, 'unit_discount' => 0, 'quantity' => 4]);

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

        $intervals = GoalProgressCalculator::calculate($goal);

        // 4 unidades vendidas, 1 devolvida e reembolsada: (4-1) * 100 = 300.
        $this->assertEqualsWithDelta(300.0, $intervals->first()['currentValue'], 0.001);
    }

    public function test_pending_refund_does_not_reduce_goal_progress_yet(): void
    {
        $goal = $this->goalWithInterval();
        $customer = Customer::factory()->create();

        $order = Order::factory()->create([
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_date' => '2026-08-10',
        ]);
        $item = $order->items->first();
        $item->update(['unit_price' => 100, 'unit_discount' => 0, 'quantity' => 4]);

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

        $intervals = GoalProgressCalculator::calculate($goal);

        $this->assertEqualsWithDelta(400.0, $intervals->first()['currentValue'], 0.001);
    }

    public function test_effective_refund_reduces_quantity_type_goal_by_returned_units(): void
    {
        $creator = User::factory()->create();
        $goal = Goal::create([
            'created_by_user_id' => $creator->id,
            'name' => 'Meta de unidades',
            'scope' => 'company',
            'calculation_type' => 'quantity',
            'period_cycle' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'active',
        ]);
        GoalInterval::create([
            'goal_id' => $goal->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'target_value' => 30,
        ]);
        $customer = Customer::factory()->create();

        $order = Order::factory()->create(['status' => 'Pago', 'paid_at' => now(), 'sale_date' => '2026-08-10']);
        $item = $order->items->first();
        $item->update(['quantity' => 5]);

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
            'quantity' => 2,
            'unit_price' => $item->unit_price,
        ]);

        $intervals = GoalProgressCalculator::calculate($goal);

        $this->assertEqualsWithDelta(3.0, $intervals->first()['currentValue'], 0.001);
    }
}
