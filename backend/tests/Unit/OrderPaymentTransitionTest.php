<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderPaymentTransition;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\After;
use Tests\TestCase;

/**
 * TASK-003 — cobre RN-01 (pedido criado pago usa o momento da criação) e
 * RN-02 (novo pagamento após reversão recebe nova data) isoladamente da
 * camada HTTP/DB (ver OrderPaymentConfirmationTest para o fluxo via API).
 */
class OrderPaymentTransitionTest extends TestCase
{
    #[After]
    public function clearTestNow(): void
    {
        Carbon::setTestNow();
    }

    private function actor(int $id = 1): User
    {
        return (new User)->forceFill(['id' => $id]);
    }

    public function test_create_as_pending_status_does_not_confirm_payment(): void
    {
        $order = (new Order)->forceFill(['status' => 'Novo']);

        $event = OrderPaymentTransition::applyOnCreate($order, $this->actor());

        $this->assertNull($event);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->paid_by_user_id);
    }

    public function test_create_already_paid_confirms_payment_at_creation_moment(): void
    {
        Carbon::setTestNow('2026-08-05 10:00:00');
        $order = (new Order)->forceFill(['status' => 'Pago']);
        $actor = $this->actor(7);

        $event = OrderPaymentTransition::applyOnCreate($order, $actor);

        $this->assertSame(OrderPaymentTransition::EVENT_CONFIRMED, $event);
        $this->assertTrue($order->paid_at->equalTo(Carbon::parse('2026-08-05 10:00:00')));
        $this->assertSame(7, $order->paid_by_user_id);
    }

    public function test_create_in_a_later_paid_status_also_confirms_payment(): void
    {
        // RN-01: "criado como pago" cobre qualquer status pago, não só "Pago".
        $order = (new Order)->forceFill(['status' => 'Entregue']);

        $event = OrderPaymentTransition::applyOnCreate($order, $this->actor());

        $this->assertSame(OrderPaymentTransition::EVENT_CONFIRMED, $event);
        $this->assertNotNull($order->paid_at);
    }

    public function test_status_unchanged_does_not_touch_payment_fields(): void
    {
        $order = (new Order)->forceFill(['status' => 'Pago', 'paid_at' => null]);

        $event = OrderPaymentTransition::apply($order, 'Pago', $this->actor());

        $this->assertNull($event);
        $this->assertNull($order->paid_at);
    }

    public function test_transition_into_paid_status_confirms_payment(): void
    {
        Carbon::setTestNow('2026-08-05 11:00:00');
        $order = (new Order)->forceFill(['status' => 'Pago']);
        $actor = $this->actor(9);

        $event = OrderPaymentTransition::apply($order, 'Aguardando Pagamento', $actor);

        $this->assertSame(OrderPaymentTransition::EVENT_CONFIRMED, $event);
        $this->assertTrue($order->paid_at->equalTo(Carbon::parse('2026-08-05 11:00:00')));
        $this->assertSame(9, $order->paid_by_user_id);
    }

    public function test_transition_between_paid_statuses_preserves_existing_confirmation(): void
    {
        $originalPaidAt = Carbon::parse('2026-08-01 09:00:00');
        $order = (new Order)->forceFill([
            'status' => 'Separação/Fornecedor',
            'paid_at' => $originalPaidAt,
            'paid_by_user_id' => 3,
        ]);

        $event = OrderPaymentTransition::apply($order, 'Pago', $this->actor());

        $this->assertNull($event);
        $this->assertTrue($order->paid_at->equalTo($originalPaidAt));
        $this->assertSame(3, $order->paid_by_user_id);
    }

    public function test_transition_back_to_pending_status_reverts_payment_confirmation(): void
    {
        $order = (new Order)->forceFill([
            'status' => 'Aguardando Pagamento',
            'paid_at' => now(),
            'paid_by_user_id' => 5,
        ]);

        $event = OrderPaymentTransition::apply($order, 'Pago', $this->actor());

        $this->assertSame(OrderPaymentTransition::EVENT_REVERTED, $event);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->paid_by_user_id);
    }

    public function test_cancelling_a_paid_order_does_not_clear_the_historical_confirmation(): void
    {
        // "Sair para pendente" refere-se a Novo/Aguardando Pagamento — Cancelado
        // preserva paid_at como registro histórico (a exclusão do faturamento é
        // feita pelo status, não pela ausência de paid_at).
        $paidAt = Carbon::parse('2026-08-01 09:00:00');
        $order = (new Order)->forceFill([
            'status' => 'Cancelado',
            'paid_at' => $paidAt,
            'paid_by_user_id' => 5,
        ]);

        $event = OrderPaymentTransition::apply($order, 'Pago', $this->actor());

        $this->assertNull($event);
        $this->assertTrue($order->paid_at->equalTo($paidAt));
        $this->assertSame(5, $order->paid_by_user_id);
    }

    public function test_new_payment_after_reversal_receives_a_new_date(): void
    {
        // RN-02
        Carbon::setTestNow('2026-08-01 09:00:00');
        $order = (new Order)->forceFill(['status' => 'Pago']);
        OrderPaymentTransition::apply($order, 'Novo', $this->actor(1));
        $firstConfirmation = $order->paid_at;

        $order->status = 'Aguardando Pagamento';
        OrderPaymentTransition::apply($order, 'Pago', $this->actor(1));
        $this->assertNull($order->paid_at);

        Carbon::setTestNow('2026-08-03 15:30:00');
        $order->status = 'Pago';
        $event = OrderPaymentTransition::apply($order, 'Aguardando Pagamento', $this->actor(2));

        $this->assertSame(OrderPaymentTransition::EVENT_CONFIRMED, $event);
        $this->assertNotNull($order->paid_at);
        $this->assertFalse($order->paid_at->equalTo($firstConfirmation));
        $this->assertSame(2, $order->paid_by_user_id);
    }
}
