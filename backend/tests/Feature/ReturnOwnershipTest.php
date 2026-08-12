<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\User;
use App\Support\AiOperationContextBuilder;
use App\Support\DashboardPeriodResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-026 — ownership e IDOR em garantias/devoluções (achado 3).
 *
 * Antes desta task, `ReturnController::index()` não filtrava por usuário e
 * `show()` não autorizava o registro: qualquer vendedor com `returns.view`
 * listava e enumerava as devoluções dos colegas, recebendo telefone do
 * cliente, notas internas, custos e reembolso.
 *
 * Matriz aprovada (DOCUMENTACAO.md 5.12):
 * - owner/admin/gerente e garantia: todas as devoluções;
 * - vendedor: pedido próprio, criada por ele ou atribuída a ele;
 * - fora do escopo: 404, nunca 403 (não confirma existência do ID).
 */
class ReturnOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF = ['X-CSRF-TOKEN' => 'csrf-token'];

    private User $seller;

    private User $otherSeller;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $this->otherSeller = User::factory()->create(['role' => UserRole::Seller->value]);
        $this->customer = Customer::factory()->create();
    }

    private function orderFor(User $seller): Order
    {
        return Order::create([
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $seller->id,
            'seller_user_id' => $seller->id,
            'product_name' => 'Teste',
            'channel' => 'Instagram',
            'seller' => $seller->name,
            'status' => 'Pago',
            'sale_price' => 1000,
            'cost' => 400,
            'discount' => 0,
            'freight' => 0,
            'channel_fee' => 0,
            'shipping_method' => 'Sedex',
            'sale_date' => now()->toDateString(),
            'paid_at' => now(),
        ]);
    }

    private function returnFor(?Order $order, array $overrides = []): ProductReturn
    {
        return ProductReturn::create(array_merge([
            'order_id' => $order?->id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->otherSeller->id,
            'type' => 'garantia',
            'status' => 'Aguardando Recebimento',
            'internal_notes' => 'Nota interna sensível.',
            'watchmaker_cost' => 150,
        ], $overrides));
    }

    /** CA-01: o furo do achado 3, no caminho da listagem. */
    public function test_vendedor_nao_lista_devolucao_de_outro_vendedor(): void
    {
        $mine = $this->returnFor($this->orderFor($this->seller));
        $this->returnFor($this->orderFor($this->otherSeller));

        $response = $this->actingAs($this->seller)->getJson('/api/returns')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);
        $this->assertSame(1, $response->json('meta.total'));
    }

    /** CA-01: e no caminho do detalhe (IDOR por ID conhecido). */
    public function test_vendedor_nao_consulta_devolucao_de_outro_vendedor(): void
    {
        $alheia = $this->returnFor($this->orderFor($this->otherSeller));

        $this->actingAs($this->seller)
            ->getJson("/api/returns/{$alheia->id}")
            // RN-04: 404, não 403 — 403 confirmaria que o ID existe.
            ->assertStatus(404)
            ->assertJsonPath('message', 'Garantia/Troca não encontrada.');
    }

    public function test_404_de_fora_do_escopo_e_indistinguivel_de_id_inexistente(): void
    {
        $alheia = $this->returnFor($this->orderFor($this->otherSeller));

        $inexistente = $this->actingAs($this->seller)->getJson('/api/returns/999999');
        $foraDoEscopo = $this->actingAs($this->seller)->getJson("/api/returns/{$alheia->id}");

        $this->assertSame($inexistente->status(), $foraDoEscopo->status());
        $this->assertSame($inexistente->json('message'), $foraDoEscopo->json('message'));
    }

    public function test_vendedor_ve_devolucao_que_ele_mesmo_registrou_sem_pedido(): void
    {
        $avulsa = $this->returnFor(null, ['created_by_user_id' => $this->seller->id]);

        $this->actingAs($this->seller)->getJson("/api/returns/{$avulsa->id}")->assertOk();
        $this->assertCount(1, $this->actingAs($this->seller)->getJson('/api/returns')->json('data'));
    }

    public function test_vendedor_ve_devolucao_atribuida_a_ele(): void
    {
        $atribuida = $this->returnFor($this->orderFor($this->otherSeller), [
            'assigned_user_id' => $this->seller->id,
        ]);

        $this->actingAs($this->seller)->getJson("/api/returns/{$atribuida->id}")->assertOk();
        $this->assertCount(1, $this->actingAs($this->seller)->getJson('/api/returns')->json('data'));
    }

    /** CA-02: filtro estreita, nunca amplia. */
    public function test_filtros_nao_ampliam_o_escopo_do_vendedor(): void
    {
        $alheia = $this->returnFor($this->orderFor($this->otherSeller));
        $ordemAlheia = $alheia->order_id;

        $porPedido = $this->actingAs($this->seller)->getJson("/api/returns?orderId={$ordemAlheia}")->assertOk();
        $this->assertCount(0, $porPedido->json('data'));

        $porResponsavel = $this->actingAs($this->seller)
            ->getJson("/api/returns?assignedUserId={$this->otherSeller->id}")->assertOk();
        $this->assertCount(0, $porResponsavel->json('data'));

        $porCliente = $this->actingAs($this->seller)
            ->getJson("/api/returns?customer_id={$this->customer->id}")->assertOk();
        $this->assertCount(0, $porCliente->json('data'));

        $porStatus = $this->actingAs($this->seller)
            ->getJson('/api/returns?status=Aguardando Recebimento')->assertOk();
        $this->assertCount(0, $porStatus->json('data'));
    }

    /** CA-03: garantia continua com a fila inteira — decisão explícita. */
    public function test_papel_garantia_continua_vendo_toda_a_fila(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $primeira = $this->returnFor($this->orderFor($this->seller));
        $segunda = $this->returnFor($this->orderFor($this->otherSeller));

        $response = $this->actingAs($guarantee)->getJson('/api/returns')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->actingAs($guarantee)->getJson("/api/returns/{$primeira->id}")->assertOk();
        $this->actingAs($guarantee)->getJson("/api/returns/{$segunda->id}")->assertOk();
    }

    public static function fullAccessRoleProvider(): array
    {
        return [
            'owner' => [UserRole::Owner->value],
            'admin' => [UserRole::Admin->value],
            'gerente' => [UserRole::Manager->value],
            'garantia' => [UserRole::Guarantee->value],
        ];
    }

    /** CA-05: matriz de papéis no index e no show. */
    #[DataProvider('fullAccessRoleProvider')]
    public function test_papeis_com_acesso_global_veem_todas(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->returnFor($this->orderFor($this->seller));
        $this->returnFor($this->orderFor($this->otherSeller));

        $this->assertCount(2, $this->actingAs($user)->getJson('/api/returns')->json('data'));
    }

    /** RN-03: escrita também respeita o escopo, não só a leitura. */
    public function test_vendedor_nao_altera_nem_estorna_devolucao_alheia(): void
    {
        $alheia = $this->returnFor($this->orderFor($this->otherSeller));

        // Vendedor não tem `returns.update`/`returns.delete` — 403 do
        // middleware de permissão vem antes do escopo.
        $this->actingAs($this->seller)->withSession(['_token' => 'csrf-token'])
            ->patchJson("/api/returns/{$alheia->id}", ['status' => 'Recebido'], self::CSRF)
            ->assertStatus(403);

        // Já o papel garantia tem a permissão e o escopo global: passa.
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $this->actingAs($guarantee)->withSession(['_token' => 'csrf-token'])
            ->patchJson("/api/returns/{$alheia->id}", ['status' => 'Recebido'], self::CSRF)
            ->assertOk();
    }

    /** RN-03: o contexto de IA usa o mesmo escopo da tela. */
    public function test_contexto_de_ia_conta_apenas_devolucoes_visiveis(): void
    {
        $this->returnFor($this->orderFor($this->seller));
        $this->returnFor($this->orderFor($this->otherSeller));
        $this->returnFor(null, ['created_by_user_id' => $this->otherSeller->id]);

        $period = DashboardPeriodResolver::resolve(now()->subMonth()->toDateString(), now()->toDateString());

        $sellerFact = AiOperationContextBuilder::build($this->seller, $period)['facts']['returns.open'];

        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $adminFact = AiOperationContextBuilder::build($admin, $period)['facts']['returns.open'];

        $this->assertStringContainsString('1 garantias', $sellerFact['text']);
        $this->assertStringContainsString('3 garantias', $adminFact['text']);
    }
}
