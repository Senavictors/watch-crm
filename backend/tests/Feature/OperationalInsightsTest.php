<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AiSetting;
use App\Models\Goal;
use App\Models\GoalInterval;
use App\Models\Order;
use App\Models\PostingDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationalInsightsTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-08 12:00:00');
        config()->set('services.openai.summary_enabled', true);
        config()->set('services.openai.api_key', null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_calculates_payment_conversion_over_created_order_cohort_and_channels(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $this->order($admin, 'WhatsApp', 'Pago', '2026-08-03', '2026-08-03 10:00:00');
        $this->order($admin, 'WhatsApp', 'Pago', '2026-08-04', '2026-08-04 10:00:00');
        $this->order($admin, 'WhatsApp', 'Aguardando Pagamento', '2026-08-05');
        $this->order($admin, 'Site', 'Cancelado', '2026-08-06', '2026-08-06 10:00:00');
        $this->order($admin, 'Site', 'Pago', '2026-07-10', '2026-07-10 10:00:00');

        $response = $this->actingAs($admin)
            ->getJson('/api/dashboard/summary?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('conversion.current.ordersCreated', 4)
            ->assertJsonPath('conversion.current.paidOrders', 2)
            ->assertJsonPath('conversion.current.rate', 50)
            ->assertJsonPath('conversion.previous.rate', 100)
            ->assertJsonPath('conversion.percentagePointChange', -50)
            ->assertJsonPath('kpis.conversionRate.value', 50);

        $whatsApp = collect($response->json('conversion.current.channels'))->firstWhere('channel', 'WhatsApp');
        $this->assertSame(3, $whatsApp['ordersCreated']);
        $this->assertSame(2, $whatsApp['paidOrders']);
        $this->assertSame(66.7, $whatsApp['rate']);
    }

    public function test_operational_alerts_are_deterministic_and_available_without_openai(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        PostingDay::query()->updateOrCreate(
            ['weekday' => Carbon::parse('2026-08-07')->dayOfWeek],
            ['enabled' => true]
        );

        $this->order($admin, 'Site', 'Pago', '2026-08-07', '2026-08-07 10:00:00');
        $this->order($admin, 'WhatsApp', 'Aguardando Pagamento', '2026-08-08', null, '2026-08-08 11:00:00');
        $this->order($admin, 'Instagram', 'Novo', '2026-08-08', null, '2026-08-08 13:00:00');

        $response = $this->actingAs($admin)
            ->getJson('/api/dashboard/summary?from=2026-08-01&to=2026-08-31')
            ->assertOk()
            ->assertJsonPath('pendingPayments.expiredCount', 1)
            ->assertJsonPath('pendingPayments.expiringSoonCount', 1);

        $types = collect($response->json('operationalAlerts'))->pluck('type');
        $this->assertTrue($types->contains('payments.expired'));
        $this->assertTrue($types->contains('payments.expiring_soon'));
        $this->assertTrue($types->contains('shipping.late'));
    }

    public function test_quantity_goal_is_typed_and_never_formatted_as_currency_for_ai(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $goal = Goal::query()->create([
            'created_by_user_id' => $admin->id,
            'name' => 'Meta de peças',
            'scope' => 'company',
            'calculation_type' => 'quantity',
            'period_cycle' => 'monthly',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'active',
        ]);
        GoalInterval::query()->create([
            'goal_id' => $goal->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'target_value' => 15,
        ]);

        $order = $this->order($admin, 'WhatsApp', 'Pago', '2026-08-08', '2026-08-08 10:00:00');
        $order->items()->update(['quantity' => 12]);
        AiSetting::query()->create([
            'api_key' => 'sk-proj-typed-facts-test-1234567890',
            'model' => 'gpt-5.6-luna',
            'enabled' => true,
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiResponse([
                'goal.company',
                'sales.volume',
                'sales.conversion',
            ])),
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/ai/summary', [
                'from' => '2026-08-01',
                'to' => '2026-08-31',
                'refresh' => false,
            ], self::CSRF_HEADERS)
            ->assertOk();

        $goalFact = collect($response->json('items'))->firstWhere('id', 'goal.company');
        $this->assertSame('quantity', $goalFact['type']);
        $this->assertStringContainsString('12 unidades', $goalFact['text']);
        $this->assertStringContainsString('15 unidades', $goalFact['text']);
        $this->assertStringNotContainsString('R$', $goalFact['text']);

        Http::assertSent(function ($request) {
            $external = json_decode(data_get($request->data(), 'input.1.content'), true, flags: JSON_THROW_ON_ERROR);
            $goalFact = collect($external['facts'])->firstWhere('id', 'goal.company');

            $this->assertSame('quantity', $goalFact['type']);
            $this->assertEquals(12, $goalFact['values']['currentValue']);
            $this->assertEquals(15, $goalFact['values']['targetValue']);

            return true;
        });
    }

    public function test_payment_expiration_can_be_updated_and_is_returned_as_iso_datetime(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = $this->order($admin, 'WhatsApp', 'Aguardando Pagamento', '2026-08-08');

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/orders/'.$order->id, [
                'paymentExpiresAt' => '2026-08-08T15:30:00-03:00',
            ], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('paymentExpiresAt', '2026-08-08T18:30:00+00:00');

        $this->assertNotNull($order->fresh()->payment_expires_at);
    }

    public function test_pending_payment_action_filter_returns_all_pending_statuses_only(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->order($admin, 'WhatsApp', 'Novo', '2026-08-08');
        $this->order($admin, 'Site', 'Aguardando Pagamento', '2026-08-08');
        $this->order($admin, 'Instagram', 'Pago', '2026-08-08', '2026-08-08 10:00:00');

        $response = $this->actingAs($admin)
            ->getJson('/api/orders?paymentStatus=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->assertEqualsCanonicalizing(
            ['Novo', 'Aguardando Pagamento'],
            collect($response->json('data'))->pluck('status')->all()
        );
    }

    private function order(
        User $seller,
        string $channel,
        string $status,
        string $createdAt,
        ?string $paidAt = null,
        ?string $paymentExpiresAt = null
    ): Order {
        return Order::factory()->create([
            'seller_user_id' => $seller->id,
            'channel' => $channel,
            'status' => $status,
            'paid_at' => $paidAt,
            'paid_by_user_id' => $paidAt ? $seller->id : null,
            'sale_date' => substr($createdAt, 0, 10),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'payment_expires_at' => $paymentExpiresAt,
            'shipped_date' => null,
        ]);
    }

    private function openAiResponse(array $factIds): array
    {
        return [
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['factIds' => $factIds], JSON_THROW_ON_ERROR),
                ]],
            ]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10, 'total_tokens' => 110],
        ];
    }
}
