<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-005 — Implementar comissões por produto e venda.
 * CA-02: relatório fecha por vendedor e período.
 * CA-03: pagamento de comissão é auditável.
 * RN-02: vendedor só vê a própria projeção.
 */
class CommissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public static function viewPermissionRoleProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, false],
            'vendedor' => [UserRole::Seller->value, true],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    public static function payPermissionRoleProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, false],
            'vendedor' => [UserRole::Seller->value, false],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    private function paidOrderWithCommission(User $seller, float $unitCommission, int $quantity = 1): Order
    {
        $order = Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => now(),
        ]);
        $order->items()->delete();
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Produto teste',
            'product_type' => 'Relógios',
            'quantity' => $quantity,
            'unit_price' => 100,
            'unit_cost' => 50,
            'unit_commission' => $unitCommission,
            'unit_discount' => 0,
        ]);

        return $order->fresh(['items']);
    }

    #[DataProvider('viewPermissionRoleProvider')]
    public function test_commissions_view_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->getJson('/api/commissions');

        if ($expected) {
            $response->assertOk();
        } else {
            $response->assertStatus(403);
        }
    }

    #[DataProvider('payPermissionRoleProvider')]
    public function test_commissions_pay_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $order = $this->paidOrderWithCommission($seller, 40);

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/commissions/pay', [
                'orderItemIds' => [$order->items->first()->id],
            ], self::CSRF_HEADERS);

        if ($expected) {
            $response->assertOk();
        } else {
            $response->assertStatus(403);
        }
    }

    public function test_seller_only_sees_own_commission_projection(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $otherSeller = User::factory()->create(['role' => UserRole::Seller->value]);

        $this->paidOrderWithCommission($seller, 40);
        $this->paidOrderWithCommission($otherSeller, 150);

        $response = $this->actingAs($seller)->getJson('/api/commissions')->assertOk();

        $this->assertEqualsWithDelta(40.0, $response->json('summary.accrued'), 0.001);
        $this->assertCount(1, $response->json('data'));
        $this->assertArrayNotHasKey('sellers', $response->json());
    }

    public function test_seller_cannot_override_scope_via_seller_user_id_param(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $otherSeller = User::factory()->create(['role' => UserRole::Seller->value]);

        $this->paidOrderWithCommission($seller, 40);
        $this->paidOrderWithCommission($otherSeller, 150);

        $response = $this->actingAs($seller)
            ->getJson('/api/commissions?sellerUserId='.$otherSeller->id)
            ->assertOk();

        $this->assertEqualsWithDelta(40.0, $response->json('summary.accrued'), 0.001);
    }

    public function test_admin_can_filter_report_by_seller(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $otherSeller = User::factory()->create(['role' => UserRole::Seller->value]);

        $this->paidOrderWithCommission($seller, 40);
        $this->paidOrderWithCommission($otherSeller, 150);

        $response = $this->actingAs($admin)
            ->getJson('/api/commissions?sellerUserId='.$seller->id)
            ->assertOk();

        $this->assertEqualsWithDelta(40.0, $response->json('summary.accrued'), 0.001);
        $this->assertIsArray($response->json('sellers'));
    }

    public function test_owner_or_admin_can_mark_commission_as_paid_and_it_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $order = $this->paidOrderWithCommission($seller, 40, quantity: 2);
        $item = $order->items->first();

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/commissions/pay', [
                'orderItemIds' => [$item->id],
            ], self::CSRF_HEADERS);

        $response->assertOk();
        $this->assertEqualsWithDelta(80.0, $response->json('totalAmount'), 0.001);

        $item->refresh();
        $this->assertNotNull($item->commission_paid_at);
        $this->assertSame($admin->id, $item->commission_paid_by_user_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'commissions.paid',
            'user_id' => $admin->id,
        ]);
    }

    public function test_paying_an_already_paid_item_again_is_idempotent_not_an_error(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $order = $this->paidOrderWithCommission($seller, 40);
        $item = $order->items->first();

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/commissions/pay', ['orderItemIds' => [$item->id]], self::CSRF_HEADERS)
            ->assertOk();

        $firstPaidAt = $item->fresh()->commission_paid_at;

        // Reprocessar o mesmo item não deve gerar erro nem sobrescrever a
        // data original — o item já pago simplesmente não é elegível de novo.
        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/commissions/pay', ['orderItemIds' => [$item->id]], self::CSRF_HEADERS);

        $response->assertStatus(422);
        $this->assertTrue($firstPaidAt->equalTo($item->fresh()->commission_paid_at));
    }

    public function test_cancelled_order_item_is_not_eligible_for_commission_payment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $order = $this->paidOrderWithCommission($seller, 40);
        $order->update(['status' => 'Cancelado']);
        $item = $order->items->first();

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/commissions/pay', ['orderItemIds' => [$item->id]], self::CSRF_HEADERS)
            ->assertStatus(422);

        $this->assertNull($item->fresh()->commission_paid_at);
    }

    public function test_editing_items_of_an_order_with_paid_commission_is_rejected(): void
    {
        // TASK-005 (CA-03): não pode reabrir uma comissão já conciliada
        // recriando os itens do pedido por baixo dela.
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $order = $this->paidOrderWithCommission($seller, 40);
        $order->items->first()->update(['commission_paid_at' => now(), 'commission_paid_by_user_id' => $admin->id]);

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/orders/'.$order->id, [
                'items' => [
                    ['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 300, 'unitDiscount' => 0],
                ],
            ], self::CSRF_HEADERS);

        $response->assertStatus(422);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'unit_price' => 100]);
    }
}
