<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public function test_admin_can_create_list_update_and_delete_a_category(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $created = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/categories', ['name' => 'Pulseiras', 'hasQuality' => false], self::CSRF_HEADERS)
            ->assertCreated()
            ->assertJsonPath('name', 'Pulseiras')
            ->assertJsonPath('hasQuality', false)
            ->json();

        $this->actingAs($admin)
            ->getJson('/api/categories')
            ->assertOk()
            ->assertJsonFragment(['id' => $created['id'], 'name' => 'Pulseiras']);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->putJson('/api/categories/'.$created['id'], ['name' => 'Pulseiras de Couro', 'hasQuality' => true], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('name', 'Pulseiras de Couro')
            ->assertJsonPath('hasQuality', true);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->deleteJson('/api/categories/'.$created['id'], [], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('categories', ['id' => $created['id']]);
    }

    public function test_category_name_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        Category::factory()->create(['name' => 'Estojos']);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/categories', ['name' => 'Estojos'], self::CSRF_HEADERS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_seller_cannot_manage_categories(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);

        $this->actingAs($seller)
            ->getJson('/api/categories')
            ->assertStatus(403);

        $this->actingAs($seller)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/categories', ['name' => 'Pulseiras'], self::CSRF_HEADERS)
            ->assertStatus(403);
    }
}
