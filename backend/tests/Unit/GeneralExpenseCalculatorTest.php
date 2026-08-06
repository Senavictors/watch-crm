<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Support\GeneralExpenseCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-006 — módulo de despesas gerais.
 * RN-03: total do período usa `expense_date`, não `created_at`.
 */
class GeneralExpenseCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_sums_all_expenses_when_no_period_is_given(): void
    {
        Expense::factory()->create(['amount' => 100, 'expense_date' => '2026-01-05']);
        Expense::factory()->create(['amount' => 250, 'expense_date' => '2026-03-20']);

        $this->assertEqualsWithDelta(350.0, GeneralExpenseCalculator::total(), 0.001);
    }

    public function test_total_filters_by_expense_date_not_created_at(): void
    {
        $inPeriod = Expense::factory()->create(['amount' => 100, 'expense_date' => '2026-01-15']);
        $inPeriod->forceFill(['created_at' => '2020-01-01'])->save();

        Expense::factory()->create(['amount' => 999, 'expense_date' => '2026-02-01']);

        $total = GeneralExpenseCalculator::total('2026-01-01', '2026-01-31');

        $this->assertEqualsWithDelta(100.0, $total, 0.001);
    }

    public function test_total_returns_zero_for_a_period_without_expenses(): void
    {
        Expense::factory()->create(['amount' => 500, 'expense_date' => '2025-01-01']);

        $this->assertEqualsWithDelta(0.0, GeneralExpenseCalculator::total('2026-01-01', '2026-01-31'), 0.001);
    }
}
