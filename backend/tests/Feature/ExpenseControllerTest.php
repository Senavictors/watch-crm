<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-006 — Criar módulo de despesas gerais.
 * CA-01: CRUD e filtros por período/categoria funcionam.
 * CA-02: alterações são auditadas.
 */
class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_HEADERS = ['X-CSRF-TOKEN' => 'csrf-token'];

    public static function permissionRoleProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, false],
            'vendedor' => [UserRole::Seller->value, false],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    #[DataProvider('permissionRoleProvider')]
    public function test_expenses_permission_matrix(string $role, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->getJson('/api/expenses');

        if ($expected) {
            $response->assertOk();
        } else {
            $response->assertStatus(403);
        }
    }

    public function test_admin_can_create_an_expense_and_it_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/expenses', [
                'category' => 'Marketing e Anúncios',
                'description' => 'Anúncios Instagram - agosto',
                'amount' => 5000,
                'expenseDate' => '2026-08-01',
            ], self::CSRF_HEADERS);

        $response->assertCreated()
            ->assertJsonPath('category', 'Marketing e Anúncios')
            ->assertJsonPath('amount', 5000)
            ->assertJsonPath('createdByUserName', $admin->name);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'expenses.created',
            'user_id' => $admin->id,
        ]);
    }

    public function test_expense_requires_a_valid_category(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->postJson('/api/expenses', [
                'category' => 'Categoria Inexistente',
                'description' => 'Teste',
                'amount' => 100,
                'expenseDate' => '2026-08-01',
            ], self::CSRF_HEADERS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');
    }

    public function test_expense_can_be_updated_and_it_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $expense = Expense::factory()->create(['amount' => 100]);

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->patchJson('/api/expenses/'.$expense->id, ['amount' => 150], self::CSRF_HEADERS)
            ->assertOk()
            ->assertJsonPath('amount', 150);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'expenses.updated',
            'auditable_id' => $expense->id,
        ]);
    }

    public function test_expense_can_be_deleted_and_it_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $expense = Expense::factory()->create();

        $this->actingAs($admin)
            ->withSession(['_token' => 'csrf-token'])
            ->deleteJson('/api/expenses/'.$expense->id, [], self::CSRF_HEADERS)
            ->assertOk();

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'expenses.deleted',
        ]);
    }

    public function test_expenses_can_be_filtered_by_category_and_period(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        Expense::factory()->create(['category' => 'Salários', 'expense_date' => '2026-08-05']);
        Expense::factory()->create(['category' => 'Fretes', 'expense_date' => '2026-08-05']);
        Expense::factory()->create(['category' => 'Salários', 'expense_date' => '2025-01-01']);

        $response = $this->actingAs($admin)
            ->getJson('/api/expenses?category=Salários&startDate=2026-08-01&endDate=2026-08-31')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_metadata_returns_the_fixed_category_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);

        $this->actingAs($admin)
            ->getJson('/api/expenses/metadata')
            ->assertOk()
            ->assertJsonFragment(['categories' => \App\Support\ExpenseMetadata::CATEGORIES]);
    }
}
