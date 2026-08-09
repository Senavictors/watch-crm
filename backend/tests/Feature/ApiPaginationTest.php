<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_index_returns_bounded_pagination_metadata(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        Customer::factory()->count(25)->create();

        $this->actingAs($admin)
            ->getJson('/api/customers?page=2&perPage=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.currentPage', 2)
            ->assertJsonPath('meta.lastPage', 3)
            ->assertJsonPath('meta.perPage', 10)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.from', 11)
            ->assertJsonPath('meta.to', 20);

        $this->actingAs($admin)
            ->getJson('/api/customers?perPage=1000')
            ->assertOk()
            ->assertJsonPath('meta.perPage', 100);
    }

    public function test_customer_lookup_preserves_seller_ownership(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $otherSeller = User::factory()->create(['role' => UserRole::Seller->value]);
        $ownCustomer = Customer::factory()->create(['name' => 'Cliente do vendedor', 'owner_user_id' => $seller->id]);
        $otherCustomer = Customer::factory()->create(['name' => 'Cliente de outro vendedor', 'owner_user_id' => $otherSeller->id]);
        Order::factory()->create(['seller_user_id' => $seller->id, 'customer_id' => $ownCustomer->id]);
        Order::factory()->create(['seller_user_id' => $otherSeller->id, 'customer_id' => $otherCustomer->id]);

        $response = $this->actingAs($seller)->getJson('/api/customers/lookup?search=Cliente')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownCustomer->id);
    }

    public function test_order_detail_preserves_order_policy_ownership(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $otherSeller = User::factory()->create(['role' => UserRole::Seller->value]);
        $ownOrder = Order::factory()->create(['seller_user_id' => $seller->id]);
        $otherOrder = Order::factory()->create(['seller_user_id' => $otherSeller->id]);

        $this->actingAs($seller)->getJson('/api/orders/'.$ownOrder->id)->assertOk();
        $this->actingAs($seller)->getJson('/api/orders/'.$otherOrder->id)->assertForbidden();
    }
}
