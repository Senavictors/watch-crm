<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\Quality;
use App\Models\ReturnItem;
use App\Models\ReturnStatusHistory;
use App\Models\User;
use App\Models\WatchModel;
use App\Support\GeneralExpenseCalculator;
use App\Support\RevenueCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-025 / ADR-007 — nenhuma operação autorizada pela API pode apagar
 * histórico financeiro.
 */
class HistoryPreservationTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF = ['X-CSRF-TOKEN' => 'csrf-token'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
    }

    private function acting()
    {
        return $this->actingAs($this->admin)->withSession(['_token' => 'csrf-token']);
    }

    private function orderFor(Customer $customer, array $overrides = []): Order
    {
        $product = Product::factory()->create();

        $order = Order::create(array_merge([
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->admin->id,
            'seller_user_id' => $this->admin->id,
            'product_id' => $product->id,
            'product_name' => 'Teste',
            'channel' => 'Instagram',
            'seller' => $this->admin->name,
            'status' => 'Pago',
            'sale_price' => 1000,
            'cost' => 400,
            'discount' => 0,
            'freight' => 0,
            'channel_fee' => 0,
            'shipping_method' => 'Sedex',
            'sale_date' => now()->toDateString(),
            'paid_at' => now(),
            'paid_by_user_id' => $this->admin->id,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Teste',
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 1000,
            'unit_cost' => 400,
            'unit_discount' => 0,
        ]);

        return $order;
    }

    /** CA-01: o pior cenário do achado 2 — cliente cascateando em tudo. */
    public function test_excluir_cliente_com_historico_nao_remove_nada(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer);
        $return = ProductReturn::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'created_by_user_id' => $this->admin->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);

        $response = $this->acting()->deleteJson("/api/customers/{$customer->id}", [], self::CSRF);

        $response->assertStatus(409)->assertJsonPath('code', 'customer_has_history');

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);
        $this->assertDatabaseHas('returns', ['id' => $return->id]);
    }

    public function test_cliente_sem_historico_ainda_pode_ser_excluido(): void
    {
        $customer = Customer::factory()->create();

        $this->acting()->deleteJson("/api/customers/{$customer->id}", [], self::CSRF)->assertOk();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    /** CA-02: alternativa reversível e documentada à exclusão. */
    public function test_arquivar_tira_o_cliente_das_listas_sem_apagar_historico(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer);

        $this->acting()->patchJson("/api/customers/{$customer->id}/archive", [], self::CSRF)
            ->assertOk()
            ->assertJsonPath('archivedAt', fn ($value) => $value !== null);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);

        // Sai da listagem e do lookup padrão...
        $this->assertCount(0, $this->acting()->getJson('/api/customers')->json('data'));
        $this->assertCount(0, $this->acting()->getJson('/api/customers/lookup')->json('data'));
        // ...mas continua consultável explicitamente.
        $this->assertCount(1, $this->acting()->getJson('/api/customers?archived=1')->json('data'));

        // CA-03: o faturamento histórico dele não muda.
        $this->assertSame(1000.0, RevenueCalculator::calculate($this->admin));

        $this->acting()->patchJson("/api/customers/{$customer->id}/unarchive", [], self::CSRF)->assertOk();
        $this->assertCount(1, $this->acting()->getJson('/api/customers')->json('data'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'customers.archived']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customers.unarchived']);
    }

    /** RN-02: pedido pago não é apagado; o caminho é cancelar. */
    public function test_pedido_pago_nao_pode_ser_excluido(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer);

        $this->acting()->deleteJson("/api/orders/{$order->id}", [], self::CSRF)
            ->assertStatus(409)
            ->assertJsonPath('code', 'order_has_financial_history');

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id]);
    }

    public function test_pedido_nunca_pago_ainda_pode_ser_excluido(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer, ['status' => 'Novo', 'paid_at' => null, 'paid_by_user_id' => null]);

        $this->acting()->deleteJson("/api/orders/{$order->id}", [], self::CSRF)->assertOk();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_pedido_com_comissao_paga_nao_pode_ser_excluido(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer, ['status' => 'Novo', 'paid_at' => null, 'paid_by_user_id' => null]);
        $order->items()->update(['commission_paid_at' => now(), 'commission_paid_by_user_id' => $this->admin->id]);

        $this->acting()->deleteJson("/api/orders/{$order->id}", [], self::CSRF)
            ->assertStatus(409)
            ->assertJsonPath('blockers', ['comissão paga']);
    }

    /** RN-04: devolução com impacto financeiro/histórico é estornada. */
    public function test_devolucao_com_impacto_nao_e_excluida_e_pode_ser_estornada(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer);

        $return = ProductReturn::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'created_by_user_id' => $this->admin->id,
            'type' => 'devolucao',
            'status' => 'Reembolso Efetuado',
            'refund_amount' => 400,
        ]);
        ReturnItem::create([
            'return_id' => $return->id,
            'order_item_id' => $order->items()->first()->id,
            'product_name' => 'Teste',
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 1000,
        ]);

        $this->acting()->deleteJson("/api/returns/{$return->id}", [], self::CSRF)
            ->assertStatus(409)
            ->assertJsonPath('code', 'return_has_financial_history');

        // CA-03: enquanto vale, o reembolso desconta do faturamento...
        $this->assertSame(600.0, RevenueCalculator::calculate($this->admin));

        $this->acting()->patchJson("/api/returns/{$return->id}/void", ['reason' => 'Reembolso lançado por engano.'], self::CSRF)
            ->assertOk()
            ->assertJsonPath('voidReason', 'Reembolso lançado por engano.');

        // ...e depois do estorno o fato continua no banco, o efeito não.
        $this->assertDatabaseHas('returns', ['id' => $return->id]);
        $this->assertSame(1000.0, RevenueCalculator::calculate($this->admin));
        $this->assertDatabaseHas('audit_logs', ['action' => 'returns.voided']);
    }

    public function test_devolucao_estornada_nao_aceita_mais_alteracao(): void
    {
        $customer = Customer::factory()->create();
        $return = ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->admin->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);
        // `voided_at` não é fillable de propósito: estorno só acontece pela
        // rota dedicada, nunca por mass assignment.
        $return->voided_at = now();
        $return->voided_by_user_id = $this->admin->id;
        $return->void_reason = 'Duplicada.';
        $return->save();

        $this->acting()->patchJson("/api/returns/{$return->id}", ['status' => 'Recebido'], self::CSRF)
            ->assertStatus(422)
            ->assertJsonPath('code', 'return_voided');
    }

    public function test_devolucao_recem_criada_sem_impacto_ainda_pode_ser_excluida(): void
    {
        $customer = Customer::factory()->create();
        $return = ProductReturn::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $this->admin->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
        ]);
        ReturnStatusHistory::create([
            'return_id' => $return->id,
            'from_status' => null,
            'to_status' => 'Aguardando Recebimento',
            'actor_user_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        $this->acting()->deleteJson("/api/returns/{$return->id}", [], self::CSRF)->assertOk();

        $this->assertDatabaseMissing('returns', ['id' => $return->id]);
    }

    /** CA-03: despesa estornada sai dos agregados sem sumir do histórico. */
    public function test_despesa_estornada_sai_do_resultado_mas_continua_no_banco(): void
    {
        $expense = Expense::factory()->create(['amount' => 1500, 'expense_date' => '2026-08-05']);

        $this->assertSame(1500.0, GeneralExpenseCalculator::total('2026-08-01', '2026-08-31'));

        $this->acting()->patchJson("/api/expenses/{$expense->id}/void", ['reason' => 'Duplicada.'], self::CSRF)->assertOk();

        $this->assertSame(0.0, GeneralExpenseCalculator::total('2026-08-01', '2026-08-31'));
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_estorno_exige_motivo_e_nao_repete(): void
    {
        $expense = Expense::factory()->create();

        $this->acting()->patchJson("/api/expenses/{$expense->id}/void", [], self::CSRF)
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->acting()->patchJson("/api/expenses/{$expense->id}/void", ['reason' => 'Erro de digitação.'], self::CSRF)->assertOk();
        $this->acting()->patchJson("/api/expenses/{$expense->id}/void", ['reason' => 'De novo.'], self::CSRF)->assertStatus(422);
    }

    /** RN-05: cascatas do catálogo viraram conflito explícito. */
    public function test_catalogo_em_uso_nao_e_apagado_em_cascata(): void
    {
        $product = Product::factory()->create();
        $model = WatchModel::query()->find($product->model_id);
        $category = Category::firstOrCreate(['name' => 'Relógios'], ['has_quality' => true]);
        $model->category_id = $category->id;
        $model->save();

        $this->acting()->deleteJson("/api/brands/{$product->brand_id}", [], self::CSRF)
            ->assertStatus(409)
            ->assertJsonPath('code', 'brand_in_use');

        $this->acting()->deleteJson("/api/models/{$model->id}", [], self::CSRF)
            ->assertStatus(409)
            ->assertJsonPath('code', 'model_in_use');

        $this->acting()->deleteJson("/api/categories/{$category->id}", [], self::CSRF)
            ->assertStatus(409)
            ->assertJsonPath('code', 'category_in_use');

        $this->acting()->deleteJson("/api/qualities/{$model->quality_id}", [], self::CSRF)
            ->assertStatus(409)
            ->assertJsonPath('code', 'quality_in_use');

        $this->assertDatabaseHas('brands', ['id' => $product->brand_id]);
        $this->assertDatabaseHas('models', ['id' => $model->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_produto_vendido_nao_pode_ser_excluido(): void
    {
        $customer = Customer::factory()->create();
        $order = $this->orderFor($customer);
        $productId = $order->items()->first()->product_id;

        $this->acting()->deleteJson("/api/products/{$productId}", [], self::CSRF)
            ->assertStatus(409)
            ->assertJsonPath('code', 'product_in_use');

        $this->assertDatabaseHas('products', ['id' => $productId]);
    }

    public function test_catalogo_sem_uso_continua_excluivel(): void
    {
        $brand = Brand::factory()->create();
        $quality = Quality::factory()->create();

        $this->acting()->deleteJson("/api/brands/{$brand->id}", [], self::CSRF)->assertOk();
        $this->acting()->deleteJson("/api/qualities/{$quality->id}", [], self::CSRF)->assertOk();

        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
        $this->assertDatabaseMissing('qualities', ['id' => $quality->id]);
    }

    /** CA-04: auditoria e mutação na mesma transação. */
    public function test_exclusao_bloqueada_nao_gera_registro_de_auditoria(): void
    {
        $customer = Customer::factory()->create();
        $this->orderFor($customer);

        $this->acting()->deleteJson("/api/customers/{$customer->id}", [], self::CSRF)->assertStatus(409);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'customers.deleted']);
    }
}
