<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\User;
use App\Support\CommissionCalculator;
use App\Support\RevenueCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-015 — `ReturnController` (`/api/returns`) não tinha nenhuma
 * cobertura de Feature test antes desta suíte: nem criação, nem
 * autorização, nem o efeito do reembolso parcial nos calculadores
 * financeiros de `Support/` (só havia testes unitários dos calculadores
 * assumindo dados já no banco).
 */
class ReturnControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public static function createPermissionProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, true],
            'garantia' => [UserRole::Guarantee->value, true],
            // RN da task: vendedor tem só `returns.view`, não `returns.create`
            // (ver `CrmPermissions::seller()`).
            'vendedor' => [UserRole::Seller->value, false],
        ];
    }

    #[DataProvider('createPermissionProvider')]
    public function test_returns_create_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/returns', [
                'customerId' => $customer->id,
                'type' => 'garantia',
                'items' => [
                    ['productName' => 'Relógio Teste', 'productType' => 'Relógios', 'quantity' => 1, 'unitPrice' => 250],
                ],
            ], self::CSRF_HEADERS);

        if ($expected) {
            $response->assertCreated();
        } else {
            $response->assertStatus(403);
        }
    }

    public function test_guarantee_can_create_a_return_and_it_is_audited(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create();

        $response = $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/returns', [
                'customerId' => $customer->id,
                'orderId' => $order->id,
                'type' => 'garantia',
                'status' => 'Aguardando Recebimento',
                'reason' => 'Coroa travada',
                'items' => [
                    ['productName' => 'Relógio Y', 'productType' => 'Relógios', 'quantity' => 1, 'unitPrice' => 250],
                ],
            ], self::CSRF_HEADERS);

        $response->assertCreated()
            ->assertJsonPath('type', 'garantia')
            ->assertJsonPath('status', 'Aguardando Recebimento')
            ->assertJsonPath('customerId', $customer->id)
            ->assertJsonPath('items.0.productName', 'Relógio Y');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'returns.created',
            'user_id' => $guarantee->id,
        ]);
    }

    public function test_return_creation_requires_at_least_one_item(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();

        $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/returns', [
                'customerId' => $customer->id,
                'type' => 'garantia',
                'items' => [],
            ], self::CSRF_HEADERS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_seller_cannot_update_a_return(): void
    {
        // RN da task: vendedor só tem `returns.view` (não `returns.update`).
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();

        $productReturn = ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $seller->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $this->actingAs($seller)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/returns/'.$productReturn->id, [
                'status' => 'Recebido',
            ], self::CSRF_HEADERS)
            ->assertStatus(403);
    }

    public function test_guarantee_can_update_a_return_to_refunded_status_with_a_partial_amount_and_it_reflects_in_the_financial_calculators(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();

        $order = Order::factory()->create([
            'seller_user_id' => $seller->id,
            'customer_id' => $customer->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 400,
            'discount' => 0,
            'freight' => 0,
            'cost' => 100,
            'channel_fee' => 0,
        ]);
        $order->items()->delete();
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Relógio X',
            'product_type' => 'Relógios',
            'quantity' => 2,
            'unit_price' => 200,
            'unit_cost' => 50,
            'unit_commission' => 30,
            'unit_discount' => 0,
        ]);

        $productReturn = ProductReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $admin->id,
            'type' => 'devolucao',
            'status' => 'Aguardando Recebimento',
        ]);
        ReturnItem::create([
            'return_id' => $productReturn->id,
            'order_item_id' => $item->id,
            'product_name' => 'Relógio X',
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 200,
        ]);

        // Antes do reembolso efetuado: faturamento/comissão ainda cheios.
        $this->assertEqualsWithDelta(400.0, RevenueCalculator::calculate($admin), 0.001);
        $this->assertEqualsWithDelta(60.0, CommissionCalculator::accrued($admin), 0.001); // 30 * 2

        $response = $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/returns/'.$productReturn->id, [
                'status' => 'Reembolso Efetuado',
                'refundAmount' => 200,
                'items' => [
                    ['orderItemId' => $item->id, 'productName' => 'Relógio X', 'productType' => 'Relógios', 'quantity' => 1, 'unitPrice' => 200],
                ],
            ], self::CSRF_HEADERS);

        $response->assertOk()
            ->assertJsonPath('status', 'Reembolso Efetuado')
            ->assertJsonPath('refundAmount', 200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'returns.updated',
            'auditable_id' => $productReturn->id,
        ]);

        // Depois do reembolso efetuado: faturamento reduzido pelo valor
        // devolvido; comissão reduzida proporcional à quantidade devolvida
        // (não pelo valor manual `refund_amount`, ver `CommissionCalculator`).
        $this->assertEqualsWithDelta(200.0, RevenueCalculator::calculate($admin), 0.001); // 400 - 200
        $this->assertEqualsWithDelta(30.0, CommissionCalculator::accrued($admin), 0.001); // netQty=1 * 30
    }

    public function test_returns_index_can_be_filtered_by_order(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();
        $orderA = Order::factory()->create();
        $orderB = Order::factory()->create();

        $returnA = ProductReturn::create([
            'order_id' => $orderA->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);
        ProductReturn::create([
            'order_id' => $orderB->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $response = $this->actingAs($guarantee)
            ->getJson('/api/returns?orderId='.$orderA->id)
            ->assertOk();

        $ids = collect($response->json())->pluck('id')->all();

        $this->assertSame([$returnA->id], $ids);
    }
}
