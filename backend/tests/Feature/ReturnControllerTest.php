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
 *
 * TASK-017 — estende a suíte para o fluxo operacional formalizado: status
 * inicial forçado, máquina de estados (`ReturnStatusTransition`),
 * `return_status_history`, `withinWarrantyWindow` e os novos filtros de
 * `index()` (`assignedUserId`/`status`).
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

        // Semeado direto em "Reembolso Pendente" (não via HTTP): a partir da
        // TASK-017, "Aguardando Recebimento" → "Reembolso Efetuado" não é
        // mais uma transição válida em uma única etapa (ver
        // `ReturnStatusTransition`) — este teste cobre o efeito financeiro
        // do reembolso, não o fluxo procedimental completo.
        $productReturn = ProductReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $admin->id,
            'type' => 'devolucao',
            'status' => 'Reembolso Pendente',
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

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$returnA->id], $ids);
    }

    public function test_creation_always_starts_in_awaiting_receipt_regardless_of_status_sent(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/returns', [
                'customerId' => $customer->id,
                'type' => 'garantia',
                // RN-03/CA-01: tenta nascer direto em "Concluído" — deve ser
                // ignorado, a devolução sempre nasce em "Aguardando
                // Recebimento".
                'status' => 'Concluído',
                'items' => [
                    ['productName' => 'Relógio Z', 'productType' => 'Relógios', 'quantity' => 1, 'unitPrice' => 250],
                ],
            ], self::CSRF_HEADERS);

        $response->assertCreated()
            ->assertJsonPath('status', 'Aguardando Recebimento')
            ->assertJsonPath('statusHistory.0.fromStatus', null)
            ->assertJsonPath('statusHistory.0.toStatus', 'Aguardando Recebimento')
            ->assertJsonPath('statusHistory.0.actorUserId', $guarantee->id);

        $this->assertDatabaseHas('return_status_history', [
            'from_status' => null,
            'to_status' => 'Aguardando Recebimento',
            'actor_user_id' => $guarantee->id,
        ]);
    }

    public function test_invalid_status_transition_is_rejected_with_422(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();

        $productReturn = ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $response = $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/returns/'.$productReturn->id, [
                // pula direto para "Reembolso Efetuado" — inválido a partir
                // de "Aguardando Recebimento".
                'status' => 'Reembolso Efetuado',
            ], self::CSRF_HEADERS);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Transição de 'Aguardando Recebimento' para 'Reembolso Efetuado' não é permitida.");

        $this->assertSame('Aguardando Recebimento', $productReturn->refresh()->status);
        $this->assertDatabaseMissing('return_status_history', [
            'return_id' => $productReturn->id,
            'to_status' => 'Reembolso Efetuado',
        ]);
    }

    public function test_valid_status_transition_is_recorded_in_history_with_the_right_actor(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();

        $productReturn = ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $response = $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/returns/'.$productReturn->id, [
                'status' => 'Recebido',
            ], self::CSRF_HEADERS);

        $response->assertOk()->assertJsonPath('status', 'Recebido');

        $this->assertDatabaseHas('return_status_history', [
            'return_id' => $productReturn->id,
            'from_status' => 'Aguardando Recebimento',
            'to_status' => 'Recebido',
            'actor_user_id' => $guarantee->id,
        ]);
    }

    public function test_saving_other_fields_without_changing_status_does_not_write_a_history_entry(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();

        $productReturn = ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $this->assertDatabaseCount('return_status_history', 0);

        $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/returns/'.$productReturn->id, [
                'status' => 'Aguardando Recebimento',
                'reason' => 'Detalhe atualizado',
            ], self::CSRF_HEADERS)
            ->assertOk();

        $this->assertDatabaseCount('return_status_history', 0);
    }

    public function test_status_history_appears_in_the_payload_in_chronological_order(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();

        $createResponse = $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/returns', [
                'customerId' => $customer->id,
                'type' => 'garantia',
                'items' => [
                    ['productName' => 'Relógio W', 'productType' => 'Relógios', 'quantity' => 1, 'unitPrice' => 250],
                ],
            ], self::CSRF_HEADERS)
            ->assertCreated();

        $returnId = $createResponse->json('id');

        $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/returns/'.$returnId, ['status' => 'Recebido'], self::CSRF_HEADERS)
            ->assertOk();

        $response = $this->actingAs($guarantee)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/returns/'.$returnId, ['status' => 'Em Análise'], self::CSRF_HEADERS)
            ->assertOk();

        $history = $response->json('statusHistory');

        $this->assertCount(3, $history);
        $this->assertSame([null, 'Aguardando Recebimento', 'Recebido'], array_column($history, 'fromStatus'));
        $this->assertSame(['Aguardando Recebimento', 'Recebido', 'Em Análise'], array_column($history, 'toStatus'));
    }

    public function test_within_warranty_window_is_true_for_a_recent_sale(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['sale_date' => now()->subMonths(2)->toDateString()]);

        $productReturn = ProductReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $response = $this->actingAs($guarantee)
            ->getJson('/api/returns?orderId='.$order->id)
            ->assertOk();

        $payload = collect($response->json('data'))->firstWhere('id', $productReturn->id);

        $this->assertTrue($payload['withinWarrantyWindow']);
    }

    public function test_within_warranty_window_is_false_for_a_sale_older_than_eighteen_months(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['sale_date' => now()->subMonths(19)->toDateString()]);

        $productReturn = ProductReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $response = $this->actingAs($guarantee)
            ->getJson('/api/returns?orderId='.$order->id)
            ->assertOk();

        $payload = collect($response->json('data'))->firstWhere('id', $productReturn->id);

        $this->assertFalse($payload['withinWarrantyWindow']);
    }

    public function test_within_warranty_window_is_null_when_there_is_no_linked_order(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();

        $productReturn = ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $response = $this->actingAs($guarantee)
            ->getJson('/api/returns?customer_id='.$customer->id)
            ->assertOk();

        $payload = collect($response->json('data'))->firstWhere('id', $productReturn->id);

        $this->assertNull($payload['withinWarrantyWindow']);
    }

    public function test_index_can_be_filtered_by_assigned_user_and_status(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $otherAssignee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $customer = Customer::factory()->create();

        $matching = ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'assigned_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Recebido',
        ]);
        ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'assigned_user_id' => $otherAssignee->id,
            'type' => 'garantia',
            'status' => 'Recebido',
        ]);
        ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $guarantee->id,
            'assigned_user_id' => $guarantee->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $response = $this->actingAs($guarantee)
            ->getJson('/api/returns?assignedUserId='.$guarantee->id.'&status=Recebido')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$matching->id], $ids);
    }
}
