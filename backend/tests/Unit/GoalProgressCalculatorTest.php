<?php

namespace Tests\Unit;

use App\Models\Goal;
use App\Models\GoalInterval;
use App\Models\Order;
use App\Models\User;
use App\Support\GoalProgressCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-003 — CA-02: metas passam a usar `paid_at` (não mais só o status
 * diferente de `Cancelado`) para decidir se um pedido conta para a meta.
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
}
