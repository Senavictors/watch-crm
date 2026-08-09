<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-013 — Revisar papéis e permissões financeiras (ADR-003).
 *
 * CA-02: espelhamento da permissão `dashboard.financial.view` (backend +
 * ownership) cobrindo `cost` em pedidos e produtos.
 * CA-03: matriz automatizada owner/admin/gerente/vendedor/garantia.
 */
class FinancialPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public static function financialViewRoleProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, false],
            'vendedor' => [UserRole::Seller->value, false],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    /**
     * Igual ao provider acima, mas sem garantia — esse papel não tem
     * `orders.view` (nunca teve, não é escopo desta task), então não faz
     * sentido testar visibilidade de campo numa rota que ele nem acessa.
     */
    public static function orderViewingRoleProvider(): array
    {
        return array_filter(
            self::financialViewRoleProvider(),
            fn ($role) => $role[0] !== UserRole::Guarantee->value
        );
    }

    #[DataProvider('financialViewRoleProvider')]
    public function test_dashboard_financial_view_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath(
                'user.permissions',
                fn (array $permissions) => in_array('dashboard.financial.view', $permissions, true) === $expected
            );
    }

    #[DataProvider('orderViewingRoleProvider')]
    public function test_order_cost_visibility_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);
        // Vendedor só vê os próprios pedidos (ownership) — atribuído a ele
        // pra garantir que o pedido apareça na listagem de todo papel testado.
        $order = Order::factory()->create(['seller_user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/orders');

        $response->assertOk();
        $payload = $response->json('data');
        $orderPayload = collect($payload)->firstWhere('id', $order->id);

        $this->assertSame($expected, array_key_exists('cost', $orderPayload), "role={$role}");
        $detail = $this->actingAs($user)->getJson('/api/orders/'.$order->id)->assertOk()->json();
        $this->assertSame($expected, array_key_exists('unitCost', $detail['items'][0] ?? []), "role={$role} item");
    }

    #[DataProvider('financialViewRoleProvider')]
    public function test_product_cost_visibility_for_view_only_roles(string $role, bool $canViewFinancials): void
    {
        // Vendedor e garantia só têm products.view — sem custo. Owner/admin
        // têm dashboard.financial.view OU products.update, então sempre veem.
        // Gerente tem products.update (gerencia catálogo) mesmo sem
        // dashboard.financial.view — visão de catálogo != relatório
        // financeiro (ver User::canViewCatalogCost()).
        $user = User::factory()->create(['role' => $role]);
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/products')->assertOk();
        $payload = collect($response->json('data'))->firstWhere('id', $product->id);

        $expectCost = $canViewFinancials || in_array($role, [UserRole::Manager->value], true);
        $this->assertSame($expectCost, array_key_exists('cost', $payload), "role={$role}");
    }

    public function test_owner_can_be_assigned_as_order_seller(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner->value]);
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->getJson('/api/orders/metadata')
            ->assertOk()
            ->assertJsonFragment(['id' => $owner->id, 'name' => $owner->name]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/orders', [
                'customerId' => $customer->id,
                'sellerUserId' => $owner->id,
                'items' => [
                    ['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 100, 'unitDiscount' => 0],
                ],
                'channel' => 'Instagram',
                'shippingMethod' => 'Sedex',
                'saleDate' => now()->toDateString(),
            ], self::CSRF_HEADERS)
            ->assertCreated()
            ->assertJsonPath('sellerUserId', $owner->id);
    }

    public function test_owner_sees_all_orders_regardless_of_seller(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner->value]);
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);
        Order::factory()->create(['seller_user_id' => $sellerA->id]);
        Order::factory()->create(['seller_user_id' => $sellerB->id]);

        $this->actingAs($owner)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_cannot_create_or_promote_owner_user(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager->value]);
        $existing = User::factory()->create(['role' => UserRole::Seller->value]);

        $this->actingAs($manager)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/users', [
                'name' => 'Tentativa',
                'email' => 'tentativa@watchcrm.local',
                'password' => 'Senha123456!',
                'role' => 'owner',
            ], self::CSRF_HEADERS)
            ->assertStatus(403);

        $this->actingAs($manager)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/users/'.$existing->id, ['role' => 'owner'], self::CSRF_HEADERS)
            ->assertStatus(403);
    }

    public function test_manager_cannot_edit_or_deactivate_owner_user(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager->value]);
        $owner = User::factory()->create(['role' => UserRole::Owner->value]);

        $this->actingAs($manager)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/users/'.$owner->id, ['name' => 'Outro nome'], self::CSRF_HEADERS)
            ->assertStatus(403);

        $this->actingAs($manager)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/users/'.$owner->id.'/active', [], self::CSRF_HEADERS)
            ->assertStatus(403);
    }
}
