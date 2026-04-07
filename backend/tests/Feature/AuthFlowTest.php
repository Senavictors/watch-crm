<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'password' => 'Admin123456!',
            'role' => UserRole::Admin->value,
        ]);

        $response = $this->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'Admin123456!',
            ], [
                'X-CSRF-TOKEN' => 'csrf-token',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', UserRole::Admin->value);

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => 'Admin123456!',
            'is_active' => false,
        ]);

        $response = $this->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'Admin123456!',
            ], [
                'X-CSRF-TOKEN' => 'csrf-token',
            ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_protected_route_requires_authentication(): void
    {
        $this->getJson('/api/customers')->assertStatus(401);
    }

    public function test_vendor_only_sees_owned_records(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::Seller->value]);
        $other = User::factory()->create(['role' => UserRole::Seller->value]);

        $vendorCustomer = Customer::factory()->create(['owner_user_id' => $vendor->id]);
        $otherCustomer = Customer::factory()->create(['owner_user_id' => $other->id]);

        Order::factory()->create(['seller_user_id' => $vendor->id, 'customer_id' => $vendorCustomer->id]);
        Order::factory()->create(['seller_user_id' => $other->id, 'customer_id' => $otherCustomer->id]);

        $this->actingAs($vendor)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(2);

        $this->actingAs($vendor)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_manager_can_view_all_records(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager->value]);

        Customer::factory()->count(2)->create();

        $this->actingAs($manager)
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_vendor_cannot_access_settings_resources(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::Seller->value]);

        $this->actingAs($vendor)
            ->getJson('/api/brands')
            ->assertStatus(403);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/logout', [], [
                'X-CSRF-TOKEN' => 'csrf-token',
            ])
            ->assertOk();

        $this->assertGuest();
    }

    public function test_reset_password_changes_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NovaSenha12345',
                'password_confirmation' => 'NovaSenha12345',
            ], [
                'X-CSRF-TOKEN' => 'csrf-token',
            ])
            ->assertOk();
    }
}
