<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReturn;
use App\Models\ReturnStatusHistory;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * TASK-029 — atomicidade de transição e auditoria (achado 6).
 *
 * A parte de exclusão mútua sob concorrência real vive em
 * `AtomicTransitionConcurrencyMySqlTest` (SQLite ignora `lockForUpdate()`).
 * Aqui ficam as garantias verificáveis em qualquer banco: a auditoria
 * participa do mesmo commit, e uma falha nela desfaz a operação inteira.
 */
class AtomicTransitionTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF = ['X-CSRF-TOKEN' => 'csrf-token'];

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $this->customer = Customer::factory()->create();
    }

    private function acting(?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->withSession(['_token' => 'csrf-token']);
    }

    /**
     * Faz a próxima gravação de auditoria falhar. É a forma mais direta de
     * exercitar CA-03 sem instrumentar o `AuditLogger`: se a auditoria
     * participa do mesmo commit, a mutação principal tem de voltar atrás.
     */
    private function breakAuditLog(): void
    {
        AuditLog::creating(function () {
            throw new RuntimeException('Falha simulada ao gravar auditoria.');
        });
    }

    private function order(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => $this->customer->id,
            'seller_user_id' => $this->admin->id,
            'status' => 'Aguardando Pagamento',
            'paid_at' => null,
        ], $overrides));
    }

    private function returnRecord(string $status = 'Recebido'): ProductReturn
    {
        return ProductReturn::create([
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->admin->id,
            'type' => 'devolucao',
            'status' => $status,
        ]);
    }

    // --- CA-03: falha de auditoria derruba a operação ---

    public function test_falha_de_auditoria_desfaz_a_edicao_do_pedido(): void
    {
        $order = $this->order(['channel' => 'Instagram']);
        $this->breakAuditLog();

        $this->acting()->patchJson("/api/orders/{$order->id}", ['channel' => 'Site'], self::CSRF)
            ->assertStatus(500);

        $this->assertSame('Instagram', $order->fresh()->channel, 'A edição precisa ter voltado atrás.');
        $this->assertDatabaseMissing('audit_logs', ['action' => 'orders.updated']);
    }

    public function test_falha_de_auditoria_desfaz_a_confirmacao_de_pagamento(): void
    {
        $order = $this->order();
        $this->breakAuditLog();

        $this->acting()->patchJson("/api/orders/{$order->id}/payment", ['confirmed' => true], self::CSRF)
            ->assertStatus(500);

        $fresh = $order->fresh();
        $this->assertNull($fresh->paid_at, 'O pagamento não pode ficar confirmado sem rastro.');
        $this->assertSame('Aguardando Pagamento', $fresh->status);
    }

    public function test_falha_de_auditoria_desfaz_a_criacao_do_pedido(): void
    {
        $product = Product::factory()->create(['qty' => 5]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $this->breakAuditLog();

        $this->acting()->postJson('/api/orders', [
            'customerId' => $this->customer->id,
            'sellerUserId' => $seller->id,
            'items' => [['productId' => $product->id, 'quantity' => 1, 'unitPrice' => 200, 'unitDiscount' => 0]],
            'channel' => 'Instagram',
            'status' => 'Novo',
            'shippingMethod' => 'Sedex',
            'saleDate' => now()->toDateString(),
        ], self::CSRF)->assertStatus(500);

        $this->assertSame(0, Order::count());
        // A reserva de estoque também volta atrás, por estar no mesmo commit.
        $this->assertSame(0, $product->fresh()->reserved_qty);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_falha_de_auditoria_desfaz_a_transicao_da_devolucao(): void
    {
        $productReturn = $this->returnRecord('Recebido');
        $this->breakAuditLog();

        $this->acting()->patchJson("/api/returns/{$productReturn->id}", ['status' => 'Em Análise'], self::CSRF)
            ->assertStatus(500);

        $this->assertSame('Recebido', $productReturn->fresh()->status);
        // CA-02: nem o histórico pode sobrar sozinho.
        $this->assertDatabaseMissing('return_status_history', [
            'return_id' => $productReturn->id,
            'to_status' => 'Em Análise',
        ]);
    }

    public function test_falha_de_auditoria_desfaz_a_aprovacao_de_reembolso(): void
    {
        $productReturn = $this->returnRecord('Reembolso Pendente');
        $this->breakAuditLog();

        $this->acting()->patchJson("/api/returns/{$productReturn->id}/refund", [
            'amount' => 300,
            'reason' => 'Teste de atomicidade.',
        ], self::CSRF)->assertStatus(500);

        $fresh = $productReturn->fresh();
        $this->assertSame('Reembolso Pendente', $fresh->status);
        $this->assertNull($fresh->refund_amount);
        $this->assertNull($fresh->refund_approved_at);
        $this->assertSame(0, ReturnStatusHistory::where('return_id', $productReturn->id)->count());
    }

    public function test_falha_de_auditoria_desfaz_a_atualizacao_da_lista_de_espera(): void
    {
        $entry = WaitlistEntry::create([
            'customer_id' => $this->customer->id,
            'product_name' => 'Relógio X',
            'seller_user_id' => $this->admin->id,
            'created_by_user_id' => $this->admin->id,
            'status' => 'Pendente',
        ]);
        $this->breakAuditLog();

        $this->acting()->patchJson("/api/waitlist/{$entry->id}", ['status' => 'Avisado'], self::CSRF)
            ->assertStatus(500);

        $this->assertSame('Pendente', $entry->fresh()->status);
    }

    public function test_falha_de_auditoria_desfaz_o_estorno(): void
    {
        $productReturn = $this->returnRecord('Recebido');
        $this->breakAuditLog();

        $this->acting()->patchJson("/api/returns/{$productReturn->id}/void", [
            'reason' => 'Teste de atomicidade.',
        ], self::CSRF)->assertStatus(500);

        $this->assertNull($productReturn->fresh()->voided_at);
    }

    // --- CA-02: histórico sempre parte do estado imediatamente anterior ---

    public function test_transicao_invalida_a_partir_do_estado_atual_e_recusada(): void
    {
        $productReturn = $this->returnRecord('Recebido');

        // "Recebido" → "Reembolso Efetuado" não é uma transição válida em um
        // passo; a validação usa o status lido dentro do lock.
        $this->acting()->patchJson("/api/returns/{$productReturn->id}/refund", [
            'amount' => 100,
            'reason' => 'Pulando etapas.',
        ], self::CSRF)->assertStatus(422);

        $this->assertSame('Recebido', $productReturn->fresh()->status);
        $this->assertSame(0, ReturnStatusHistory::where('return_id', $productReturn->id)->count());
    }

    public function test_historico_registra_a_origem_real_da_transicao(): void
    {
        $productReturn = $this->returnRecord('Recebido');

        $this->acting()->patchJson("/api/returns/{$productReturn->id}", ['status' => 'Em Análise'], self::CSRF)
            ->assertOk();
        $this->acting()->patchJson("/api/returns/{$productReturn->id}", ['status' => 'Reembolso Pendente'], self::CSRF)
            ->assertOk();

        $historico = ReturnStatusHistory::where('return_id', $productReturn->id)
            ->orderBy('id')
            ->get(['from_status', 'to_status']);

        $this->assertSame([
            ['from_status' => 'Recebido', 'to_status' => 'Em Análise'],
            ['from_status' => 'Em Análise', 'to_status' => 'Reembolso Pendente'],
        ], $historico->map(fn ($h) => $h->only(['from_status', 'to_status']))->all());
    }

    // --- Regressão: o caminho feliz continua auditado ---

    public function test_operacao_bem_sucedida_grava_mutacao_e_auditoria_juntas(): void
    {
        $order = $this->order();

        $this->acting()->patchJson("/api/orders/{$order->id}/payment", ['confirmed' => true], self::CSRF)
            ->assertOk();

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'orders.payment_confirmed']);
    }

    /**
     * CA-04 — a auditoria dentro da transação é o que garante que uma
     * tentativa descartada (rollback por deadlock e retry) não deixe linha
     * duplicada: se a mutação volta, a auditoria volta junto.
     */
    public function test_rollback_nao_deixa_auditoria_orfa(): void
    {
        $order = $this->order(['channel' => 'Instagram']);
        $this->breakAuditLog();

        $this->acting()->patchJson("/api/orders/{$order->id}", ['channel' => 'Site'], self::CSRF)
            ->assertStatus(500);

        $this->assertSame(0, AuditLog::count());
    }
}
