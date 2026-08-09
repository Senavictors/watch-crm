<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-019 — `CustomerController`: correção do bug de ownership em
 * `index()` (RN-02), duplicidade de telefone (CA-02), `show()` com
 * insights determinísticos de recompra (RN-01/CA-01/CA-03) e marcação de
 * atrito imutável (RN-03).
 */
class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    // ------------------------------------------------------------------
    // Bug de ownership em index() (RN-02)
    // ------------------------------------------------------------------

    public function test_seller_only_sees_own_customers_in_index(): void
    {
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);
        $ownCustomer = Customer::factory()->create(['owner_user_id' => $sellerA->id]);
        Customer::factory()->create(['owner_user_id' => $sellerB->id]);

        $response = $this->actingAs($sellerA)->getJson('/api/customers');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($ownCustomer->id, $response->json('data.0.id'));
    }

    public function test_guarantee_still_sees_all_customers_in_index(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);
        Customer::factory()->create(['owner_user_id' => $sellerA->id]);
        Customer::factory()->create(['owner_user_id' => $sellerB->id]);

        $response = $this->actingAs($guarantee)->getJson('/api/customers');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public static function unrestrictedRoleProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value],
            'admin' => [UserRole::Admin->value],
            'gerente' => [UserRole::Manager->value],
        ];
    }

    #[DataProvider('unrestrictedRoleProvider')]
    public function test_admin_manager_owner_see_all_customers_in_index(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);
        Customer::factory()->create(['owner_user_id' => $sellerA->id]);
        Customer::factory()->create(['owner_user_id' => $sellerB->id]);

        $response = $this->actingAs($user)->getJson('/api/customers');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    // ------------------------------------------------------------------
    // Duplicidade de telefone (CA-02)
    // ------------------------------------------------------------------

    public function test_store_rejects_duplicate_phone(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        Customer::factory()->create(['phone' => '11999990000']);

        $response = $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($admin)
            ->postJson('/api/customers', [
                'name' => 'Novo Cliente',
                'phone' => '11999990000',
            ], self::CSRF_HEADERS);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um cliente cadastrado com este telefone.');

        $this->assertSame(1, Customer::query()->where('phone', '11999990000')->count());
    }

    public function test_update_own_record_without_changing_phone_does_not_false_positive(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create(['phone' => '11988887777', 'name' => 'Original']);

        $response = $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($admin)
            ->patchJson('/api/customers/'.$customer->id, [
                'name' => 'Atualizado',
                'phone' => '11988887777',
            ], self::CSRF_HEADERS);

        $response->assertOk()->assertJsonPath('name', 'Atualizado');
    }

    public function test_update_rejects_phone_already_used_by_another_customer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        Customer::factory()->create(['phone' => '11977776666']);
        $customer = Customer::factory()->create(['phone' => '11955554444']);

        $response = $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($admin)
            ->patchJson('/api/customers/'.$customer->id, [
                'phone' => '11977776666',
            ], self::CSRF_HEADERS);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um cliente cadastrado com este telefone.');

        $this->assertSame('11955554444', $customer->fresh()->phone);
    }

    // ------------------------------------------------------------------
    // show() — 404 / 403 / ownership via Policy
    // ------------------------------------------------------------------

    public function test_show_returns_404_when_customer_does_not_exist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $this->actingAs($admin)
            ->getJson('/api/customers/999999')
            ->assertStatus(404);
    }

    public function test_show_returns_403_when_seller_views_another_sellers_customer(): void
    {
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create(['owner_user_id' => $sellerB->id]);

        $this->actingAs($sellerA)
            ->getJson('/api/customers/'.$customer->id)
            ->assertStatus(403);
    }

    public function test_show_returns_200_for_owning_seller(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create(['owner_user_id' => $seller->id]);

        $this->actingAs($seller)
            ->getJson('/api/customers/'.$customer->id)
            ->assertOk()
            ->assertJsonPath('id', $customer->id)
            ->assertJsonStructure(['insights' => ['totalSpent', 'orderCount', 'averageTicket', 'lastOrderAt', 'possibleRepurchase'], 'frictionNotes', 'hasFrictionHistory']);
    }

    // ------------------------------------------------------------------
    // Insights — reconciliação (CA-01), mesmo padrão da TASK-015
    // ------------------------------------------------------------------

    public function test_insights_total_spent_reconciles_with_an_independently_computed_source(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create(['owner_user_id' => $seller->id]);
        $otherCustomer = Customer::factory()->create(['owner_user_id' => $seller->id]);

        // Pedido A: pago, sem devolução.
        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'customer_id' => $customer->id,
            'status' => 'Pago',
            'paid_at' => '2026-01-05 10:00:00',
            'sale_price' => 500,
            'discount' => 10,
            'freight' => 20,
        ]);

        // Pedido B: pago, com devolução parcial (reembolso efetuado).
        $orderB = Order::factory()->create([
            'seller_user_id' => $seller->id,
            'customer_id' => $customer->id,
            'status' => 'Pago',
            'paid_at' => '2026-02-10 10:00:00',
            'sale_price' => 690,
            'discount' => 0,
            'freight' => 0,
        ]);
        $orderB->items()->delete();
        $watchItem = OrderItem::create([
            'order_id' => $orderB->id,
            'product_name' => 'Relógio Insights',
            'product_type' => 'Relógios',
            'quantity' => 2,
            'unit_price' => 300,
            'unit_cost' => 100,
            'unit_commission' => 30,
            'unit_discount' => 0,
        ]);
        $orderBReturn = ProductReturn::create([
            'order_id' => $orderB->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $admin->id,
            'type' => 'devolucao',
            'status' => 'Reembolso Efetuado',
            'refund_amount' => 300,
        ]);
        ReturnItem::create([
            'return_id' => $orderBReturn->id,
            'order_item_id' => $watchItem->id,
            'product_name' => 'Relógio Insights',
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 300,
        ]);

        // Pedido C: cancelado — excluído.
        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'customer_id' => $customer->id,
            'status' => 'Cancelado',
            'paid_at' => '2026-02-01 10:00:00',
            'sale_price' => 999,
            'discount' => 0,
            'freight' => 0,
        ]);

        // Pedido D: de outro cliente do mesmo vendedor — excluído.
        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'customer_id' => $otherCustomer->id,
            'status' => 'Pago',
            'paid_at' => '2026-02-01 10:00:00',
            'sale_price' => 1000,
            'discount' => 0,
            'freight' => 0,
        ]);

        // Fonte independente: soma direta em orders/returns, sem passar por
        // Support/.
        $grossFromSource = Order::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->selectRaw('COALESCE(SUM(sale_price - discount + freight), 0) as total')
            ->value('total');

        $refundsFromSource = ProductReturn::query()
            ->whereIn('order_id', Order::query()
                ->where('customer_id', $customer->id)
                ->whereNotNull('paid_at')
                ->where('status', '!=', 'Cancelado')
                ->pluck('id'))
            ->where('status', 'Reembolso Efetuado')
            ->sum('refund_amount');

        $expectedTotalSpent = (float) $grossFromSource - (float) $refundsFromSource;

        $response = $this->actingAs($admin)->getJson('/api/customers/'.$customer->id);

        $response->assertOk()->assertJsonPath('insights.orderCount', 2);
        $this->assertEqualsWithDelta($expectedTotalSpent, $response->json('insights.totalSpent'), 0.001);
    }

    // ------------------------------------------------------------------
    // possibleRepurchase — heurística determinística (CA-03)
    // ------------------------------------------------------------------

    public function test_possible_repurchase_is_null_with_zero_paid_orders(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/customers/'.$customer->id);

        $response->assertOk()->assertJsonPath('insights.possibleRepurchase', null);
    }

    public function test_possible_repurchase_is_null_with_a_single_paid_order(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'Pago',
            'paid_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/customers/'.$customer->id);

        $response->assertOk()->assertJsonPath('insights.possibleRepurchase', null);
    }

    public function test_possible_repurchase_is_true_when_elapsed_time_reaches_average_interval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        // Duas compras com 30 dias de intervalo entre si; a última foi há
        // 40 dias — >= intervalo médio (30 dias) => possibleRepurchase=true.
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'Pago',
            'paid_at' => Carbon::now()->subDays(70),
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'Pago',
            'paid_at' => Carbon::now()->subDays(40),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/customers/'.$customer->id);

        $response->assertOk()->assertJsonPath('insights.possibleRepurchase', true);
    }

    public function test_possible_repurchase_is_false_when_elapsed_time_is_below_average_interval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        // Intervalo médio de 30 dias, mas a última compra foi há só 5 dias.
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'Pago',
            'paid_at' => Carbon::now()->subDays(35),
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'Pago',
            'paid_at' => Carbon::now()->subDays(5),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/customers/'.$customer->id);

        $response->assertOk()->assertJsonPath('insights.possibleRepurchase', false);
    }

    // ------------------------------------------------------------------
    // Nota de atrito (RN-03) — histórico imutável
    // ------------------------------------------------------------------

    public function test_admin_can_add_friction_note_and_it_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        $response = $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($admin)
            ->postJson('/api/customers/'.$customer->id.'/friction-notes', [
                'note' => 'Cliente reclamou de atraso na entrega.',
            ], self::CSRF_HEADERS);

        $response->assertCreated()
            ->assertJsonPath('note', 'Cliente reclamou de atraso na entrega.')
            ->assertJsonPath('createdByUserId', $admin->id);

        $this->assertDatabaseHas('customer_friction_notes', [
            'customer_id' => $customer->id,
            'note' => 'Cliente reclamou de atraso na entrega.',
            'created_by_user_id' => $admin->id,
        ]);

        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'customers.friction_note_added')
                ->where('auditable_type', Customer::class)
                ->where('auditable_id', $customer->id)
                ->exists()
        );
    }

    public function test_friction_note_rejects_empty_note(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        $response = $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($admin)
            ->postJson('/api/customers/'.$customer->id.'/friction-notes', [
                'note' => '',
            ], self::CSRF_HEADERS);

        $response->assertStatus(422);
        $this->assertDatabaseCount('customer_friction_notes', 0);
    }

    public function test_friction_notes_appear_chronologically_in_show(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($admin)
            ->postJson('/api/customers/'.$customer->id.'/friction-notes', ['note' => 'Primeira nota.'], self::CSRF_HEADERS)
            ->assertCreated();

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($admin)
            ->postJson('/api/customers/'.$customer->id.'/friction-notes', ['note' => 'Segunda nota.'], self::CSRF_HEADERS)
            ->assertCreated();

        $response = $this->actingAs($admin)->getJson('/api/customers/'.$customer->id);

        $response->assertOk()
            ->assertJsonPath('hasFrictionHistory', true)
            ->assertJsonPath('frictionNotes.0.note', 'Primeira nota.')
            ->assertJsonPath('frictionNotes.1.note', 'Segunda nota.');
    }

    public function test_seller_without_customers_update_permission_cannot_add_friction_note(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create(['owner_user_id' => $seller->id]);

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($seller)
            ->postJson('/api/customers/'.$customer->id.'/friction-notes', [
                'note' => 'Tentativa de nota.',
            ], self::CSRF_HEADERS)
            ->assertStatus(403);
    }
}
