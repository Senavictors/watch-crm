<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-018 — `WaitlistController` (`/api/waitlist`): matriz de permissão,
 * ownership por vendedor (RN-02), duplicidade ativa por cliente/produto,
 * regra de "Convertido" exigir `orderId` e sinal de disponibilidade
 * (`productCurrentQty`/`isAvailable`).
 */
class WaitlistControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public static function viewPermissionProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, true],
            'vendedor' => [UserRole::Seller->value, true],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    #[DataProvider('viewPermissionProvider')]
    public function test_waitlist_view_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->getJson('/api/waitlist');

        if ($expected) {
            $response->assertOk();
        } else {
            $response->assertStatus(403);
        }
    }

    public static function createPermissionProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, true],
            'vendedor' => [UserRole::Seller->value, true],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    #[DataProvider('createPermissionProvider')]
    public function test_waitlist_create_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/waitlist', [
                'customerId' => $customer->id,
                'productName' => 'Rolex Submariner',
            ], self::CSRF_HEADERS);

        if ($expected) {
            $response->assertCreated();
        } else {
            $response->assertStatus(403);
        }
    }

    public static function deletePermissionProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, true],
            // TASK-018: vendedor tem view/create/update, mas não delete.
            'vendedor' => [UserRole::Seller->value, false],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    #[DataProvider('deletePermissionProvider')]
    public function test_waitlist_delete_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);
        $customer = Customer::factory()->create();
        $entry = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Rolex Submariner',
            'seller_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'status' => 'Pendente',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'csrf-token'])
            ->deleteJson('/api/waitlist/'.$entry->id, [], self::CSRF_HEADERS);

        if ($expected) {
            $response->assertOk();
        } else {
            $response->assertStatus(403);
        }
    }

    public function test_creating_an_entry_is_audited_and_defaults_status_and_seller(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($seller)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/waitlist', [
                'customerId' => $customer->id,
                'productName' => 'Omega Seamaster',
            ], self::CSRF_HEADERS);

        $response->assertCreated()
            ->assertJsonPath('status', 'Pendente')
            ->assertJsonPath('sellerUserId', $seller->id)
            ->assertJsonPath('customerId', $customer->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'waitlist.created',
            'user_id' => $seller->id,
        ]);
    }

    public function test_seller_only_sees_and_lists_own_entries(): void
    {
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();

        $entryA = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Produto A',
            'seller_user_id' => $sellerA->id,
            'created_by_user_id' => $sellerA->id,
            'status' => 'Pendente',
        ]);
        WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Produto B',
            'seller_user_id' => $sellerB->id,
            'created_by_user_id' => $sellerB->id,
            'status' => 'Pendente',
        ]);

        // `sellerUserId` de terceiro é ignorado — o vendedor A continua só
        // vendo as próprias entradas mesmo tentando filtrar pelo vendedor B.
        $response = $this->actingAs($sellerA)
            ->getJson('/api/waitlist?sellerUserId='.$sellerB->id);

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertEquals([$entryA->id], $ids);
    }

    public function test_admin_can_filter_by_seller_user_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();

        WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Produto A',
            'seller_user_id' => $sellerA->id,
            'created_by_user_id' => $sellerA->id,
            'status' => 'Pendente',
        ]);
        $entryB = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Produto B',
            'seller_user_id' => $sellerB->id,
            'created_by_user_id' => $sellerB->id,
            'status' => 'Pendente',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/waitlist?sellerUserId='.$sellerB->id);

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertEquals([$entryB->id], $ids);
    }

    public function test_seller_cannot_update_another_sellers_entry(): void
    {
        $sellerA = User::factory()->create(['role' => UserRole::Seller->value]);
        $sellerB = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();

        $entry = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Produto A',
            'seller_user_id' => $sellerB->id,
            'created_by_user_id' => $sellerB->id,
            'status' => 'Pendente',
        ]);

        $this->actingAs($sellerA)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/waitlist/'.$entry->id, ['status' => 'Avisado'], self::CSRF_HEADERS)
            ->assertStatus(403);
    }

    public function test_admin_can_update_any_entry(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();

        $entry = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Produto A',
            'seller_user_id' => $seller->id,
            'created_by_user_id' => $seller->id,
            'status' => 'Pendente',
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/waitlist/'.$entry->id, ['status' => 'Avisado'], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('status', 'Avisado');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'waitlist.updated',
            'auditable_id' => $entry->id,
        ]);
    }

    public function test_active_duplicate_entry_for_same_customer_and_product_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'product_name' => 'Rolex Submariner',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Avisado',
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/waitlist', [
                'customerId' => $customer->id,
                'productId' => $product->id,
                'productName' => 'Rolex Submariner',
            ], self::CSRF_HEADERS)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este cliente já está na lista de espera para este produto.');
    }

    public function test_new_entry_is_allowed_after_previous_one_is_converted_or_closed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id, 'product_id' => $product->id]);

        WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'product_name' => 'Rolex Submariner',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Convertido',
            'order_id' => $order->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/waitlist', [
                'customerId' => $customer->id,
                'productId' => $product->id,
                'productName' => 'Rolex Submariner',
            ], self::CSRF_HEADERS)
            ->assertCreated();
    }

    public function test_converted_status_requires_order_id_on_create(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/waitlist', [
                'customerId' => $customer->id,
                'productName' => 'Rolex Submariner',
                'status' => 'Convertido',
            ], self::CSRF_HEADERS)
            ->assertStatus(422);
    }

    public function test_converted_status_requires_order_id_on_update(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        $entry = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Rolex Submariner',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Avisado',
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/waitlist/'.$entry->id, ['status' => 'Convertido'], self::CSRF_HEADERS)
            ->assertStatus(422);
    }

    public function test_converted_status_succeeds_on_update_with_order_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $entry = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Rolex Submariner',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Avisado',
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/waitlist/'.$entry->id, [
                'status' => 'Convertido',
                'orderId' => $order->id,
            ], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('status', 'Convertido')
            ->assertJsonPath('orderId', $order->id);
    }

    public function test_terminal_status_cannot_be_reopened(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        $entry = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Rolex Submariner',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Encerrado',
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/waitlist/'.$entry->id, ['status' => 'Pendente'], self::CSRF_HEADERS)
            ->assertStatus(422);
    }

    public function test_availability_signal_reflects_product_stock(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();
        $inStockProduct = Product::factory()->create(['qty' => 3]);
        $outOfStockProduct = Product::factory()->create(['qty' => 0]);

        WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_id' => $inStockProduct->id,
            'product_name' => 'Em estoque',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Pendente',
        ]);
        WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_id' => $outOfStockProduct->id,
            'product_name' => 'Sem estoque',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Pendente',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/waitlist');

        $response->assertOk();
        $byName = collect($response->json())->keyBy('productName');

        $this->assertSame(3, $byName['Em estoque']['productCurrentQty']);
        $this->assertTrue($byName['Em estoque']['isAvailable']);
        $this->assertSame(0, $byName['Sem estoque']['productCurrentQty']);
        $this->assertFalse($byName['Sem estoque']['isAvailable']);
    }

    public function test_filters_by_status_and_customer_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();

        $pendente = WaitlistEntry::create([
            'customer_id' => $customerA->id,
            'product_name' => 'Produto A',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Pendente',
        ]);
        WaitlistEntry::create([
            'customer_id' => $customerB->id,
            'product_name' => 'Produto B',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Avisado',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/waitlist?status=Pendente&customerId='.$customerA->id);

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();
        $this->assertEquals([$pendente->id], $ids);
    }

    public function test_destroy_removes_entry_and_audits(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $customer = Customer::factory()->create();

        $entry = WaitlistEntry::create([
            'customer_id' => $customer->id,
            'product_name' => 'Rolex Submariner',
            'seller_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'status' => 'Pendente',
        ]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->deleteJson('/api/waitlist/'.$entry->id, [], self::CSRF_HEADERS)
            ->assertOk();

        $this->assertDatabaseMissing('waitlist_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'waitlist.deleted',
        ]);
    }
}
