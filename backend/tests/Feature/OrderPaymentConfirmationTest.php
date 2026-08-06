<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-003 — Registrar confirmação de pagamento por transição de status.
 * Cobre CA-01 (transições atômicas e auditadas), CA-02 (indireto — ver
 * FinancialCalculatorsTest/GoalProgressCalculatorTest para o consumo de
 * `paid_at`) e CA-03 (vendedor sem permissão recebe 403).
 */
class OrderPaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADER = ['X-CSRF-TOKEN' => 'csrf-token'];

    /**
     * As rotas de escrita passam pelo middleware `web` (CSRF via cookie
     * Sanctum-style, não bearer token — ver AGENTS.md). O client de teste não
     * simula o handshake do cookie sozinho; replica o padrão já usado em
     * AuthFlowTest.
     */
    private function actingAsWithCsrf(User $user)
    {
        return $this->withSession(['_token' => 'csrf-token'])->actingAs($user);
    }

    public function test_order_created_already_paid_confirms_payment_at_creation(): void
    {
        // RN-01
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAsWithCsrf($admin)->postJson('/api/orders', [
            'customerId' => $customer->id,
            'sellerUserId' => $seller->id,
            'items' => [
                ['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 250, 'unitDiscount' => 0],
            ],
            'channel' => 'Instagram',
            'status' => 'Pago',
            'shippingMethod' => 'Sedex',
            'saleDate' => now()->toDateString(),
        ], self::CSRF_HEADER);

        $response->assertCreated()
            ->assertJsonPath('paidByUserId', $admin->id)
            ->assertJsonPath('paidByUserName', $admin->name);

        $orderId = $response->json('id');
        $this->assertNotNull(Order::find($orderId)->paid_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'orders.payment_confirmed',
            'auditable_id' => $orderId,
            'auditable_type' => Order::class,
            'user_id' => $admin->id,
        ]);
    }

    public function test_transition_into_paid_status_confirms_payment_and_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = Order::factory()->create(['status' => 'Aguardando Pagamento']);

        $response = $this->actingAsWithCsrf($admin)
            ->patchJson('/api/orders/'.$order->id, ['status' => 'Pago'], self::CSRF_HEADER);

        $response->assertOk()
            ->assertJsonPath('paidByUserId', $admin->id);

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertSame($admin->id, $order->paid_by_user_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'orders.payment_confirmed',
            'auditable_id' => $order->id,
        ]);
    }

    public function test_transition_back_to_pending_reverts_payment_and_preserves_audit_trail(): void
    {
        $confirmedBy = User::factory()->create(['role' => UserRole::Admin->value]);
        $revertedBy = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = Order::factory()->create(['status' => 'Aguardando Pagamento']);

        $this->actingAsWithCsrf($confirmedBy)
            ->patchJson('/api/orders/'.$order->id, ['status' => 'Pago'], self::CSRF_HEADER)
            ->assertOk();

        $response = $this->actingAsWithCsrf($revertedBy)
            ->patchJson('/api/orders/'.$order->id, ['status' => 'Aguardando Pagamento'], self::CSRF_HEADER);

        $response->assertOk()
            ->assertJsonPath('paidAt', null)
            ->assertJsonPath('paidByUserId', null);

        $order->refresh();
        $this->assertNull($order->paid_at);
        $this->assertNull($order->paid_by_user_id);

        // A confirmação original continua auditada (com o ator que a fez)
        // mesmo depois que `paid_at`/`paid_by_user_id` são apagados.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'orders.payment_confirmed',
            'auditable_id' => $order->id,
            'user_id' => $confirmedBy->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'orders.payment_reverted',
            'auditable_id' => $order->id,
            'user_id' => $revertedBy->id,
        ]);
    }

    public function test_new_confirmation_after_reversal_receives_a_new_date(): void
    {
        // RN-02
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = Order::factory()->create(['status' => 'Aguardando Pagamento']);

        $this->actingAsWithCsrf($admin)
            ->patchJson('/api/orders/'.$order->id, ['status' => 'Pago'], self::CSRF_HEADER)
            ->assertOk();
        $firstPaidAt = $order->fresh()->paid_at;

        $this->actingAsWithCsrf($admin)
            ->patchJson('/api/orders/'.$order->id, ['status' => 'Aguardando Pagamento'], self::CSRF_HEADER)
            ->assertOk();
        $this->assertNull($order->fresh()->paid_at);

        $this->travel(1)->hours();
        $this->actingAsWithCsrf($admin)
            ->patchJson('/api/orders/'.$order->id, ['status' => 'Pago'], self::CSRF_HEADER)
            ->assertOk();

        $secondPaidAt = $order->fresh()->paid_at;
        $this->assertNotNull($secondPaidAt);
        $this->assertFalse($secondPaidAt->equalTo($firstPaidAt));
    }

    public function test_editing_other_fields_without_changing_status_does_not_touch_payment_confirmation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = Order::factory()->create(['status' => 'Pago', 'paid_at' => now()->subDay()]);
        $originalPaidAt = $order->paid_at;

        $this->actingAsWithCsrf($admin)
            ->patchJson('/api/orders/'.$order->id, ['notes' => 'Atualização sem trocar status'], self::CSRF_HEADER)
            ->assertOk();

        $order->refresh();
        $this->assertTrue($order->paid_at->equalTo($originalPaidAt));
    }

    public function test_seller_without_orders_update_permission_cannot_confirm_payment(): void
    {
        // CA-03
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $order = Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Aguardando Pagamento',
        ]);

        $this->actingAsWithCsrf($seller)
            ->patchJson('/api/orders/'.$order->id, ['status' => 'Pago'], self::CSRF_HEADER)
            ->assertStatus(403);

        $this->assertNull($order->fresh()->paid_at);
    }
}
