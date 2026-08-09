<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\PostingDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-016 — agenda de postagem (`/shipping/schedule`) e fila de envios
 * (`/shipping/queue`), base backend para o que hoje é hardcoded no frontend
 * (`ShippingQueue.tsx`/`helpers.ts::nextShippingDay()`).
 */
class ShippingControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public static function scheduleViewPermissionProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value],
            'admin' => [UserRole::Admin->value],
            'gerente' => [UserRole::Manager->value],
            'vendedor' => [UserRole::Seller->value],
            'garantia' => [UserRole::Guarantee->value],
        ];
    }

    #[DataProvider('scheduleViewPermissionProvider')]
    public function test_any_authenticated_role_can_view_the_schedule(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->getJson('/api/shipping/schedule')->assertOk();
    }

    #[DataProvider('scheduleViewPermissionProvider')]
    public function test_any_authenticated_role_can_view_the_queue(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->getJson('/api/shipping/queue')->assertOk();
    }

    public function test_schedule_returns_the_seven_weekdays_with_default_configuration(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)->getJson('/api/shipping/schedule')->assertOk();

        $days = $response->json();
        $this->assertCount(7, $days);

        $enabled = collect($days)->where('enabled', true)->pluck('weekday')->sort()->values()->all();
        $this->assertSame([1, 4], $enabled);

        $monday = collect($days)->firstWhere('weekday', 1);
        $this->assertSame('Segunda-feira', $monday['label']);
    }

    public static function scheduleUpdatePermissionProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, true],
            'vendedor' => [UserRole::Seller->value, false],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    #[DataProvider('scheduleUpdatePermissionProvider')]
    public function test_shipping_update_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'csrf-token'])
            ->putJson('/api/shipping/schedule', [
                'days' => $this->daysPayload([1, 4]),
            ], self::CSRF_HEADERS);

        if ($expected) {
            $response->assertOk();
        } else {
            $response->assertStatus(403);
        }
    }

    public function test_admin_can_update_the_schedule_and_it_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->putJson('/api/shipping/schedule', [
                'days' => $this->daysPayload([2, 5]),
            ], self::CSRF_HEADERS);

        $response->assertOk();

        $enabled = collect($response->json())->where('enabled', true)->pluck('weekday')->sort()->values()->all();
        $this->assertSame([2, 5], $enabled);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'shipping.schedule_updated',
            'user_id' => $admin->id,
        ]);
    }

    public function test_update_is_rejected_when_it_results_in_zero_enabled_days(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->putJson('/api/shipping/schedule', [
                'days' => $this->daysPayload([]),
            ], self::CSRF_HEADERS);

        $response->assertStatus(422);

        // A configuração original (RN-01) não deve ter sido tocada.
        $this->assertSame([1, 4], PostingDay::query()->where('enabled', true)->orderBy('weekday')->pluck('weekday')->all());
    }

    public function test_update_is_rejected_when_a_weekday_is_missing(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $days = $this->daysPayload([1]);
        unset($days[6]); // remove sábado -> só 6 dias

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->putJson('/api/shipping/schedule', ['days' => $days], self::CSRF_HEADERS);

        $response->assertStatus(422);
    }

    /**
     * 2026-08-03 é segunda-feira, habilitada por padrão (RN-01) — a data
     * esperada É aquela mesma segunda.
     */
    public function test_order_paid_on_a_posting_day_expects_the_same_day(): void
    {
        Carbon::setTestNow('2026-08-03');
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $order = Order::factory()->paid()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => Carbon::parse('2026-08-03 10:00:00'),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)->getJson('/api/shipping/queue')->assertOk();

        $item = collect($response->json('data'))->firstWhere('id', $order->id);
        $this->assertNotNull($item);
        $this->assertSame('2026-08-03', $item['nextPostingDate']);
        $this->assertFalse($item['isLate']);
    }

    /**
     * 2026-08-08 é sábado — com só segunda/quinta habilitadas, a próxima
     * data é a segunda seguinte (2026-08-10).
     */
    public function test_order_paid_on_saturday_expects_the_next_monday(): void
    {
        Carbon::setTestNow('2026-08-08');
        $order = Order::factory()->paid()->create([
            'status' => 'Pago',
            'paid_at' => Carbon::parse('2026-08-08 10:00:00'),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)->getJson('/api/shipping/queue')->assertOk();

        $item = collect($response->json('data'))->firstWhere('id', $order->id);
        $this->assertSame('2026-08-10', $item['nextPostingDate']);
    }

    public function test_order_is_late_when_today_is_after_the_expected_posting_date(): void
    {
        $order = Order::factory()->paid()->create([
            'status' => 'Pago',
            'paid_at' => Carbon::parse('2026-08-03 10:00:00'),
        ]);

        Carbon::setTestNow('2026-08-05'); // depois da segunda esperada

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)->getJson('/api/shipping/queue')->assertOk();

        $item = collect($response->json('data'))->firstWhere('id', $order->id);
        $this->assertTrue($item['isLate']);
    }

    public static function ineligibleStatusProvider(): array
    {
        return [
            'Cancelado' => ['Cancelado'],
            'Entregue' => ['Entregue'],
        ];
    }

    #[DataProvider('ineligibleStatusProvider')]
    public function test_cancelled_or_delivered_orders_never_appear_in_the_queue(string $status): void
    {
        $order = Order::factory()->paid()->create([
            'status' => $status,
            'paid_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)->getJson('/api/shipping/queue')->assertOk();

        $this->assertNull(collect($response->json('data'))->firstWhere('id', $order->id));
    }

    public function test_order_already_shipped_never_appears_in_the_queue(): void
    {
        $order = Order::factory()->paid()->create([
            'status' => 'Enviado',
            'paid_at' => now(),
            'shipped_date' => now()->toDateString(),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)->getJson('/api/shipping/queue')->assertOk();

        $this->assertNull(collect($response->json('data'))->firstWhere('id', $order->id));
    }

    public function test_correios_shipment_requires_tracking_code(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = Order::factory()->paid()->create([
            'status' => 'Pronto para Envio',
            'shipping_method' => 'Sedex',
            'tracking_code' => null,
            'shipped_date' => null,
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/orders/'.$order->id, [
                'status' => 'Enviado',
                'trackingCode' => '',
            ], self::CSRF_HEADERS)
            ->assertStatus(422)
            ->assertJsonPath('message', 'O código de rastreio é obrigatório para envios pelos Correios.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'Pronto para Envio',
            'shipped_date' => null,
        ]);
    }

    public function test_updating_shipment_sets_date_and_removes_order_from_queue(): void
    {
        Carbon::setTestNow('2026-08-09 14:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = Order::factory()->paid()->create([
            'status' => 'Pronto para Envio',
            'shipping_method' => 'Correios PAC',
            'tracking_code' => null,
            'shipped_date' => null,
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/orders/'.$order->id, [
                'status' => 'Enviado',
                'trackingCode' => 'BR123456789BR',
            ], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('status', 'Enviado')
            ->assertJsonPath('trackingCode', 'BR123456789BR')
            ->assertJsonPath('shippedDate', '2026-08-09');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'Enviado',
            'tracking_code' => 'BR123456789BR',
            'shipped_date' => '2026-08-09',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/shipping/queue')->assertOk();
        $this->assertNull(collect($response->json('data'))->firstWhere('id', $order->id));
    }

    public function test_unpaid_order_never_appears_in_the_queue(): void
    {
        $order = Order::factory()->create([
            'status' => 'Novo',
            'paid_at' => null,
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)->getJson('/api/shipping/queue')->assertOk();

        $this->assertNull(collect($response->json('data'))->firstWhere('id', $order->id));
    }

    public function test_seller_only_sees_their_own_orders_in_the_queue(): void
    {
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);

        $ownOrder = Order::factory()->paid()->create([
            'seller_user_id' => $sellerA->id,
            'status' => 'Pago',
            'paid_at' => now(),
        ]);
        $otherOrder = Order::factory()->paid()->create([
            'seller_user_id' => $sellerB->id,
            'status' => 'Pago',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($sellerA)->getJson('/api/shipping/queue')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ownOrder->id, $ids);
        $this->assertNotContains($otherOrder->id, $ids);
    }

    /**
     * `garantia` não é vendedor (`seller_user_id` nunca é dele) — o mesmo
     * escopo de ownership usado por `DashboardSummaryCalculator::nextShipments()`
     * faz o papel dele naturalmente ver a fila vazia.
     */
    public function test_guarantee_role_sees_an_empty_queue(): void
    {
        Order::factory()->paid()->create(['status' => 'Pago', 'paid_at' => now()]);

        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);

        $response = $this->actingAs($guarantee)->getJson('/api/shipping/queue')->assertOk();

        $this->assertSame([], $response->json('data'));
    }

    public function test_manager_sees_all_orders_in_the_queue_regardless_of_seller(): void
    {
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);

        Order::factory()->paid()->create(['seller_user_id' => $sellerA->id, 'status' => 'Pago', 'paid_at' => now()]);
        Order::factory()->paid()->create(['seller_user_id' => $sellerB->id, 'status' => 'Pago', 'paid_at' => now()]);

        $manager = User::factory()->create(['role' => UserRole::Manager->value]);

        $response = $this->actingAs($manager)->getJson('/api/shipping/queue')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    /**
     * @return list<array{weekday: int, enabled: bool}>
     */
    private function daysPayload(array $enabledWeekdays): array
    {
        return collect(range(0, 6))
            ->map(fn (int $weekday) => [
                'weekday' => $weekday,
                'enabled' => in_array($weekday, $enabledWeekdays, true),
            ])
            ->values()
            ->all();
    }
}
