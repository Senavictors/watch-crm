<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\User;
use App\Support\CrmPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TASK-027 / ADR-008 — segregação da escrita financeira (achado 4).
 *
 * Antes desta task, `orders.update` gravava `paid_at` e `returns.update`
 * levava uma devolução a "Reembolso Efetuado": papéis sem visão financeira
 * produziam efeito financeiro material por permissão operacional genérica.
 *
 * Matriz aprovada (ADR-008):
 * - `orders.payment.confirm`: owner, admin, gerente;
 * - `returns.refund.approve` e `returns.financials.update`: owner e admin.
 */
class FinancialActionSegregationTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF = ['X-CSRF-TOKEN' => 'csrf-token'];

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
    }

    private function acting(User $user)
    {
        return $this->actingAs($user)->withSession(['_token' => 'csrf-token']);
    }

    private function order(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => $this->customer->id,
            'status' => 'Aguardando Pagamento',
            'paid_at' => null,
        ], $overrides));
    }

    private function pendingRefund(): ProductReturn
    {
        return ProductReturn::create([
            'customer_id' => $this->customer->id,
            'created_by_user_id' => User::factory()->create(['role' => UserRole::Admin->value])->id,
            'type' => 'devolucao',
            'status' => 'Reembolso Pendente',
        ]);
    }

    // --- Matriz de permissões: os cinco pontos começam aqui (RN-05) ---

    public static function paymentMatrix(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, true],
            'vendedor' => [UserRole::Seller->value, false],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    public static function refundMatrix(): array
    {
        return [
            'owner' => [UserRole::Owner->value, true],
            'admin' => [UserRole::Admin->value, true],
            'gerente' => [UserRole::Manager->value, false],
            'vendedor' => [UserRole::Seller->value, false],
            'garantia' => [UserRole::Guarantee->value, false],
        ];
    }

    #[DataProvider('paymentMatrix')]
    public function test_matriz_de_confirmacao_de_pagamento(string $role, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);
        $order = $this->order();

        $response = $this->acting($user)
            ->patchJson("/api/orders/{$order->id}/payment", ['confirmed' => true], self::CSRF);

        if ($allowed) {
            $response->assertOk()->assertJsonPath('status', 'Pago');
            $this->assertNotNull($order->fresh()->paid_at);
        } else {
            $response->assertStatus(403);
            $this->assertNull($order->fresh()->paid_at);
        }
    }

    /** CA-02: garantia opera o pós-venda, mas não aprova reembolso. */
    #[DataProvider('refundMatrix')]
    public function test_matriz_de_aprovacao_de_reembolso(string $role, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);
        $productReturn = $this->pendingRefund();

        $response = $this->acting($user)->patchJson(
            "/api/returns/{$productReturn->id}/refund",
            ['amount' => 300, 'reason' => 'Peça sem conserto.'],
            self::CSRF
        );

        if ($allowed) {
            $response->assertOk()->assertJsonPath('status', 'Reembolso Efetuado');
        } else {
            $response->assertStatus(403);
            $this->assertSame('Reembolso Pendente', $productReturn->fresh()->status);
        }
    }

    /** CA-03: as strings existem e estão atribuídas conforme a matriz. */
    public function test_permissoes_novas_estao_atribuidas_conforme_a_matriz(): void
    {
        foreach (['orders.payment.confirm', 'returns.refund.approve', 'returns.financials.update'] as $permission) {
            $this->assertContains($permission, CrmPermissions::ALL, "{$permission} precisa existir na lista mestra.");
        }

        $this->assertContains('orders.payment.confirm', CrmPermissions::manager());
        $this->assertNotContains('returns.refund.approve', CrmPermissions::manager());
        $this->assertNotContains('returns.financials.update', CrmPermissions::manager());

        foreach ([CrmPermissions::seller(), CrmPermissions::guarantee()] as $role) {
            $this->assertNotContains('orders.payment.confirm', $role);
            $this->assertNotContains('returns.refund.approve', $role);
            $this->assertNotContains('returns.financials.update', $role);
        }

        // Regra do `auth-permissoes`: escrita sem a leitura equivalente não
        // existe neste projeto.
        $this->assertContains('orders.view', CrmPermissions::manager());
        $this->assertContains('returns.view', CrmPermissions::admin());
    }

    // --- O update genérico deixa de produzir efeito financeiro (RN-01) ---

    public function test_update_generico_nao_confirma_pagamento(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = $this->order();

        $this->acting($admin)
            ->patchJson("/api/orders/{$order->id}", ['status' => 'Pago'], self::CSRF)
            ->assertStatus(403)
            ->assertJsonPath('code', 'payment_transition_requires_dedicated_action');

        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame('Aguardando Pagamento', $order->fresh()->status);
    }

    public function test_update_generico_nao_reverte_pagamento(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = $this->order(['status' => 'Pago', 'paid_at' => now()]);

        $this->acting($admin)
            ->patchJson("/api/orders/{$order->id}", ['status' => 'Aguardando Pagamento'], self::CSRF)
            ->assertStatus(403)
            ->assertJsonPath('code', 'payment_transition_requires_dedicated_action');

        $this->assertNotNull($order->fresh()->paid_at);
    }

    /** Movimento operacional entre status pagos continua livre. */
    public function test_update_generico_move_entre_status_pagos_sem_bloqueio(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = $this->order(['status' => 'Pago', 'paid_at' => now()]);

        $this->acting($admin)
            ->patchJson("/api/orders/{$order->id}", ['status' => 'Pronto para Envio'], self::CSRF)
            ->assertOk()
            ->assertJsonPath('status', 'Pronto para Envio');
    }

    /** Cancelar não é fronteira de pagamento — continua pelo update. */
    public function test_cancelamento_continua_livre_no_update_generico(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = $this->order(['status' => 'Pago', 'paid_at' => now()]);

        $this->acting($admin)
            ->patchJson("/api/orders/{$order->id}", ['status' => 'Cancelado'], self::CSRF)
            ->assertOk();
    }

    public function test_criar_pedido_ja_pago_exige_permissao_financeira(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);

        // Garantia nem chega ao controller: não tem `orders.create`.
        $this->acting($guarantee)
            ->postJson('/api/orders', [], self::CSRF)
            ->assertStatus(403);

        // Já quem cria pedido tem a permissão de pagamento pela matriz
        // aprovada, então o caminho feliz continua funcionando.
        $manager = User::factory()->create(['role' => UserRole::Manager->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $product = \App\Models\Product::factory()->create(['qty' => 5]);

        $this->acting($manager)->postJson('/api/orders', [
            'customerId' => $this->customer->id,
            'sellerUserId' => $seller->id,
            'items' => [['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 200, 'unitDiscount' => 0]],
            'channel' => 'Instagram',
            'status' => 'Pago',
            'shippingMethod' => 'Sedex',
            'saleDate' => now()->toDateString(),
        ], self::CSRF)->assertCreated()->assertJsonPath('status', 'Pago');
    }

    // --- Campos financeiros da devolução (RN-03) ---

    public function test_garantia_lanca_custos_mas_nao_define_reembolso(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $productReturn = $this->pendingRefund();
        $items = [['productName' => 'Relógio', 'productType' => 'Relógios', 'quantity' => 1, 'unitPrice' => 300]];

        // Custo operacional: liberado, é quem tem a nota do frete.
        $this->acting($guarantee)->patchJson("/api/returns/{$productReturn->id}", [
            'freightCostIn' => 30,
            'watchmakerCost' => 120,
            'items' => $items,
        ], self::CSRF)->assertOk()->assertJsonPath('watchmakerCost', 120);

        // Valor do reembolso: bloqueado.
        $this->acting($guarantee)->patchJson("/api/returns/{$productReturn->id}", [
            'refundAmount' => 300,
            'items' => $items,
        ], self::CSRF)
            ->assertStatus(403)
            ->assertJsonPath('code', 'refund_amount_not_allowed');

        $this->assertNull($productReturn->fresh()->refund_amount);
    }

    public function test_criar_devolucao_com_valor_de_reembolso_exige_permissao_financeira(): void
    {
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);

        $this->acting($guarantee)->postJson('/api/returns', [
            'customerId' => $this->customer->id,
            'type' => 'devolucao',
            'refundAmount' => 500,
            'items' => [['productName' => 'Relógio', 'productType' => 'Relógios', 'quantity' => 1, 'unitPrice' => 500]],
        ], self::CSRF)
            ->assertStatus(403)
            ->assertJsonPath('code', 'refund_amount_not_allowed');

        $this->assertSame(0, ProductReturn::count());
    }

    public function test_reenviar_o_mesmo_valor_de_reembolso_nao_e_bloqueado(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $guarantee = User::factory()->create(['role' => UserRole::Guarantee->value]);
        $productReturn = $this->pendingRefund();
        $productReturn->refund_amount = 300;
        $productReturn->save();

        // O formulário reenvia o payload inteiro; repetir o valor que já está
        // gravado não é uma alteração financeira e não pode travar o
        // trabalho operacional da garantia.
        $this->acting($guarantee)->patchJson("/api/returns/{$productReturn->id}", [
            'refundAmount' => 300,
            'otherCosts' => 10,
            'items' => [['productName' => 'Relógio', 'productType' => 'Relógios', 'quantity' => 1, 'unitPrice' => 300]],
        ], self::CSRF)->assertOk();

        $this->assertEqualsWithDelta(300.0, (float) $productReturn->fresh()->refund_amount, 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $productReturn->fresh()->other_costs, 0.001);
    }

    public function test_update_generico_nao_aprova_reembolso_nem_para_gerente(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager->value]);
        $productReturn = $this->pendingRefund();

        $this->acting($manager)->patchJson("/api/returns/{$productReturn->id}", [
            'status' => 'Reembolso Efetuado',
            'items' => [['productName' => 'Relógio', 'productType' => 'Relógios', 'quantity' => 1, 'unitPrice' => 300]],
        ], self::CSRF)
            ->assertStatus(403)
            ->assertJsonPath('code', 'refund_approval_not_allowed');

        $this->assertSame('Reembolso Pendente', $productReturn->fresh()->status);
    }

    // --- Rastro (RN-04) ---

    public function test_aprovacao_de_reembolso_registra_ator_valores_e_motivo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $productReturn = $this->pendingRefund();
        $productReturn->refund_amount = 100;
        $productReturn->save();

        $this->acting($admin)->patchJson("/api/returns/{$productReturn->id}/refund", [
            'amount' => 250,
            'reason' => 'Acordo com o cliente após laudo.',
        ], self::CSRF)
            ->assertOk()
            ->assertJsonPath('refundApprovedByUserId', $admin->id)
            ->assertJsonPath('refundReason', 'Acordo com o cliente após laudo.');

        $fresh = $productReturn->fresh();
        $this->assertNotNull($fresh->refund_approved_at);
        $this->assertEqualsWithDelta(250.0, (float) $fresh->refund_amount, 0.001);

        $this->assertDatabaseHas('audit_logs', ['action' => 'returns.refund_approved']);
        $this->assertDatabaseHas('return_status_history', [
            'return_id' => $productReturn->id,
            'from_status' => 'Reembolso Pendente',
            'to_status' => 'Reembolso Efetuado',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_reversao_de_pagamento_exige_motivo(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = $this->order(['status' => 'Pago', 'paid_at' => now()]);

        $this->acting($admin)
            ->patchJson("/api/orders/{$order->id}/payment", ['confirmed' => false], self::CSRF)
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->acting($admin)->patchJson("/api/orders/{$order->id}/payment", [
            'confirmed' => false,
            'reason' => 'PIX não compensou.',
        ], self::CSRF)->assertOk();

        $this->assertNull($order->fresh()->paid_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.payment_reverted']);
    }

    public function test_confirmacao_repetida_e_recusada(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = $this->order(['status' => 'Pago', 'paid_at' => now()]);

        $this->acting($admin)
            ->patchJson("/api/orders/{$order->id}/payment", ['confirmed' => true], self::CSRF)
            ->assertStatus(422);
    }

    public function test_pedido_cancelado_nao_confirma_pagamento(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $order = $this->order(['status' => 'Cancelado']);

        $this->acting($admin)
            ->patchJson("/api/orders/{$order->id}/payment", ['confirmed' => true], self::CSRF)
            ->assertStatus(422)
            ->assertJsonPath('code', 'order_cancelled');
    }

    /** TASK-026 continua valendo: escopo antes da ação financeira. */
    public function test_aprovacao_respeita_o_escopo_de_ownership(): void
    {
        $productReturn = $this->pendingRefund();

        $this->acting(User::factory()->create(['role' => UserRole::Seller->value]))
            ->patchJson("/api/returns/{$productReturn->id}/refund", [
                'amount' => 100,
                'reason' => 'Tentativa indevida.',
            ], self::CSRF)
            ->assertStatus(403);
    }
}
