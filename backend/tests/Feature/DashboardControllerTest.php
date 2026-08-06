<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-009 — GET /dashboard/summary.
 * CA-01: períodos rápidos e personalizados funcionam.
 * CA-02: comparação anterior em valor e percentual é correta.
 * CA-03: agrupamento diário/semanal/mensal segue a regra.
 * CA-04: payload não vaza campos restritos.
 */
class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public static function permissionRoleProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, true],
            'vendedor' => [UserRole::Seller->value, true],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    #[DataProvider('permissionRoleProvider')]
    public function test_dashboard_view_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

        if ($expected) {
            $response->assertOk();
        } else {
            $response->assertStatus(403);
        }
    }

    public function test_custom_period_is_echoed_back_in_the_response(): void
    {
        // CA-01
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/summary?from=2026-08-01&to=2026-08-10')
            ->assertOk()
            ->assertJsonPath('period.from', '2026-08-01')
            ->assertJsonPath('period.to', '2026-08-10')
            ->assertJsonPath('period.grouping', 'day')
            ->assertJsonPath('comparison.from', '2026-07-22')
            ->assertJsonPath('comparison.to', '2026-07-31');
    }

    public function test_invalid_period_returns_422(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/summary?from=2026-08-31&to=2026-08-01')
            ->assertStatus(422);
    }

    public function test_grouping_reflects_the_requested_period_span(): void
    {
        // CA-03
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/summary?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('period.grouping', 'month');
    }

    public function test_comparison_values_and_percentage_are_correct(): void
    {
        // CA-02
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => '2026-08-05',
            'sale_price' => 200,
            'discount' => 0,
            'freight' => 0,
        ]);
        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => '2026-07-25',
            'sale_price' => 100,
            'discount' => 0,
            'freight' => 0,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/dashboard/summary?from=2026-08-01&to=2026-08-10')
            ->assertOk();

        $this->assertEqualsWithDelta(200.0, $response->json('kpis.revenue.value'), 0.001);
        $this->assertEqualsWithDelta(100.0, $response->json('kpis.revenue.previousValue'), 0.001);
        $this->assertEqualsWithDelta(100.0, $response->json('kpis.revenue.percentageChange'), 0.001);
    }

    public function test_percentage_change_is_null_when_previous_value_was_zero(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => '2026-08-05',
            'sale_price' => 200,
            'discount' => 0,
            'freight' => 0,
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/dashboard/summary?from=2026-08-01&to=2026-08-10')
            ->assertOk();

        $this->assertNull($response->json('kpis.revenue.percentageChange'));
    }

    public function test_manager_does_not_receive_financial_fields(): void
    {
        // CA-04: gerente não tem dashboard.financial.view nem commissions.view.
        $manager = User::factory()->create(['role' => UserRole::Manager->value]);

        $response = $this->actingAs($manager)->getJson('/api/dashboard/summary')->assertOk();
        $payload = $response->json();

        $this->assertArrayNotHasKey('salesProfit', $payload['kpis']);
        $this->assertArrayNotHasKey('netResult', $payload['kpis']);
        $this->assertArrayNotHasKey('generalExpenses', $payload['kpis']);
        $this->assertArrayNotHasKey('revenue', $payload['kpis']);
        $this->assertArrayNotHasKey('stock', $payload);
        $this->assertArrayNotHasKey('categories', $payload);
        $this->assertArrayNotHasKey('channels', $payload);
        $this->assertArrayNotHasKey('commission', $payload);
        foreach ($payload['evolution'] as $bucket) {
            $this->assertArrayNotHasKey('salesProfit', $bucket);
        }
    }

    public function test_seller_receives_own_revenue_and_commission_but_no_profit_or_stock(): void
    {
        // RN (docs/regras-de-negocio-dashboard.md §9): vendedor vê faturamento
        // próprio e comissão própria, mas não lucro/despesas/estoque.
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);

        $response = $this->actingAs($seller)->getJson('/api/dashboard/summary')->assertOk();
        $payload = $response->json();

        $this->assertArrayHasKey('revenue', $payload['kpis']);
        $this->assertArrayHasKey('commission', $payload);
        // Categorias/canais são só uma quebra do faturamento PRÓPRIO (já
        // escopado por ownership) — mesmo raciocínio de `revenue`, não é
        // relatório financeiro da empresa.
        $this->assertArrayHasKey('categories', $payload);
        $this->assertArrayHasKey('channels', $payload);
        $this->assertArrayNotHasKey('salesProfit', $payload['kpis']);
        $this->assertArrayNotHasKey('netResult', $payload['kpis']);
        $this->assertArrayNotHasKey('generalExpenses', $payload['kpis']);
        $this->assertArrayNotHasKey('stock', $payload);
    }

    public function test_seller_revenue_is_scoped_to_own_orders_only(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $otherSeller = User::factory()->create(['role' => UserRole::Seller->value]);

        Order::factory()->create(['seller_user_id' => $seller->id, 'status' => 'Pago', 'paid_at' => now(), 'sale_price' => 100, 'discount' => 0, 'freight' => 0]);
        Order::factory()->create(['seller_user_id' => $otherSeller->id, 'status' => 'Pago', 'paid_at' => now(), 'sale_price' => 900, 'discount' => 0, 'freight' => 0]);

        $response = $this->actingAs($seller)->getJson('/api/dashboard/summary')->assertOk();

        $this->assertEqualsWithDelta(100.0, $response->json('kpis.revenue.value'), 0.001);
    }

    public function test_owner_and_admin_receive_full_financial_payload(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard/summary')->assertOk();
        $payload = $response->json();

        foreach (['revenue', 'salesProfit', 'netResult', 'generalExpenses', 'watchesSold', 'ordersCount', 'activeOrders', 'pendingAmount'] as $key) {
            $this->assertArrayHasKey($key, $payload['kpis'], "kpis.{$key}");
        }
        foreach (['stock', 'categories', 'channels', 'commission', 'goal', 'nextShipments', 'evolution'] as $key) {
            $this->assertArrayHasKey($key, $payload, $key);
        }
    }
}
