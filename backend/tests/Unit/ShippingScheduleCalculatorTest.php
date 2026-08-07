<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\PostingDay;
use App\Support\ShippingScheduleCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * TASK-016 — cálculo puro da agenda de postagem. `RefreshDatabase` roda a
 * migration `create_posting_days_table`, então cada teste começa com a
 * configuração inicial da RN-01 (segunda=1 e quinta=4 habilitadas), a menos
 * que o próprio teste reconfigure.
 */
class ShippingScheduleCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_weekdays_returns_the_default_configuration_ordered(): void
    {
        $this->assertSame([1, 4], ShippingScheduleCalculator::enabledWeekdays());
    }

    public function test_next_posting_date_returns_null_when_from_is_null(): void
    {
        $this->assertNull(ShippingScheduleCalculator::nextPostingDate(null));
    }

    /**
     * 2026-08-03 é segunda-feira (weekday=1), habilitada por padrão — a
     * data esperada É aquela mesma segunda, não a próxima.
     */
    public function test_next_posting_date_includes_the_reference_date_when_it_is_already_a_posting_day(): void
    {
        $monday = Carbon::parse('2026-08-03 14:00:00');

        $next = ShippingScheduleCalculator::nextPostingDate($monday);

        $this->assertNotNull($next);
        $this->assertTrue($next->isSameDay(Carbon::parse('2026-08-03')));
    }

    /**
     * 2026-08-08 é sábado — com só segunda/quinta habilitadas, a próxima
     * data é a segunda seguinte (2026-08-10).
     */
    public function test_next_posting_date_returns_the_next_enabled_weekday(): void
    {
        $saturday = Carbon::parse('2026-08-08');

        $next = ShippingScheduleCalculator::nextPostingDate($saturday);

        $this->assertNotNull($next);
        $this->assertTrue($next->isSameDay(Carbon::parse('2026-08-10')));
    }

    /**
     * 2026-08-06 é quinta-feira (weekday=4), também habilitada por padrão.
     */
    public function test_next_posting_date_includes_the_reference_date_on_the_other_default_enabled_day(): void
    {
        $thursday = Carbon::parse('2026-08-06');

        $next = ShippingScheduleCalculator::nextPostingDate($thursday);

        $this->assertTrue($next->isSameDay(Carbon::parse('2026-08-06')));
    }

    public function test_next_posting_date_returns_null_when_no_weekday_is_enabled(): void
    {
        PostingDay::query()->update(['enabled' => false]);

        $this->assertNull(ShippingScheduleCalculator::nextPostingDate(Carbon::parse('2026-08-03')));
    }

    public function test_next_posting_date_is_deterministic(): void
    {
        $from = Carbon::parse('2026-08-08');

        $first = ShippingScheduleCalculator::nextPostingDate($from);
        $second = ShippingScheduleCalculator::nextPostingDate($from);

        $this->assertTrue($first->isSameDay($second));
    }

    public function test_is_late_is_false_when_next_posting_date_is_null(): void
    {
        $this->assertFalse(ShippingScheduleCalculator::isLate(null, Carbon::parse('2026-08-10')));
    }

    public function test_is_late_is_false_when_today_is_the_posting_date(): void
    {
        $next = Carbon::parse('2026-08-10');

        $this->assertFalse(ShippingScheduleCalculator::isLate($next, Carbon::parse('2026-08-10')));
    }

    public function test_is_late_is_false_when_today_is_before_the_posting_date(): void
    {
        $next = Carbon::parse('2026-08-10');

        $this->assertFalse(ShippingScheduleCalculator::isLate($next, Carbon::parse('2026-08-09')));
    }

    public function test_is_late_is_true_when_today_is_after_the_posting_date(): void
    {
        $next = Carbon::parse('2026-08-10');

        $this->assertTrue(ShippingScheduleCalculator::isLate($next, Carbon::parse('2026-08-11')));
    }

    public function test_order_is_eligible_for_queue_when_paid_not_shipped_and_not_cancelled_or_delivered(): void
    {
        $order = Order::factory()->paid()->create(['status' => 'Pago', 'shipped_date' => null]);

        $this->assertTrue(ShippingScheduleCalculator::isEligibleForQueue($order));
    }

    public function test_order_is_not_eligible_for_queue_when_not_paid(): void
    {
        $order = Order::factory()->create(['status' => 'Novo', 'paid_at' => null, 'shipped_date' => null]);

        $this->assertFalse(ShippingScheduleCalculator::isEligibleForQueue($order));
    }

    public function test_order_is_not_eligible_for_queue_when_already_shipped(): void
    {
        $order = Order::factory()->paid()->create(['status' => 'Enviado', 'shipped_date' => '2026-08-05']);

        $this->assertFalse(ShippingScheduleCalculator::isEligibleForQueue($order));
    }

    public function test_order_is_not_eligible_for_queue_when_cancelled(): void
    {
        $order = Order::factory()->paid()->create(['status' => 'Cancelado', 'shipped_date' => null]);

        $this->assertFalse(ShippingScheduleCalculator::isEligibleForQueue($order));
    }

    public function test_order_is_not_eligible_for_queue_when_delivered(): void
    {
        $order = Order::factory()->paid()->create(['status' => 'Entregue', 'shipped_date' => null]);

        $this->assertFalse(ShippingScheduleCalculator::isEligibleForQueue($order));
    }
}
