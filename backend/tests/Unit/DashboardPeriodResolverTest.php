<?php

namespace Tests\Unit;

use App\Support\DashboardPeriodResolver;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * TASK-009 — CA-01 (períodos), CA-02 (período de comparação), CA-03
 * (agrupamento diário/semanal/mensal).
 */
class DashboardPeriodResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_defaults_to_current_month_when_no_dates_are_given(): void
    {
        Carbon::setTestNow('2026-08-15');

        $period = DashboardPeriodResolver::resolve(null, null);

        $this->assertSame('2026-08-01', $period->from);
        $this->assertSame('2026-08-31', $period->to);
        $this->assertSame('day', $period->grouping);
    }

    public function test_comparison_period_is_the_immediately_preceding_period_of_the_same_length(): void
    {
        $period = DashboardPeriodResolver::resolve('2026-08-01', '2026-08-10');

        // 10 dias (01-10 de agosto) -> comparação: 10 dias terminando em 31/07.
        $this->assertSame('2026-07-22', $period->comparisonFrom);
        $this->assertSame('2026-07-31', $period->comparisonTo);
    }

    public function test_grouping_is_daily_up_to_31_days(): void
    {
        $period = DashboardPeriodResolver::resolve('2026-08-01', '2026-08-31');
        $this->assertSame('day', $period->grouping);
    }

    public function test_grouping_is_weekly_between_32_days_and_6_months(): void
    {
        $period = DashboardPeriodResolver::resolve('2026-01-01', '2026-04-01'); // ~91 dias
        $this->assertSame('week', $period->grouping);
    }

    public function test_grouping_is_monthly_above_6_months(): void
    {
        $period = DashboardPeriodResolver::resolve('2026-01-01', '2026-12-31');
        $this->assertSame('month', $period->grouping);
    }

    public function test_rejects_a_start_date_after_the_end_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DashboardPeriodResolver::resolve('2026-08-31', '2026-08-01');
    }
}
