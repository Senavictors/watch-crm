<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\User;
use App\Support\ActiveOrdersCalculator;
use App\Support\FinancialSummaryCalculator;
use App\Support\NetResultCalculator;
use App\Support\PendingAmountCalculator;
use App\Support\RevenueCalculator;
use App\Support\SalesProfitCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-001 — cobre CA-01 (fórmulas centralizadas em Support/), CA-02 (pedido
 * pago, pendente, cancelado e reembolsado) e CA-03 (divisão por zero e
 * período sem movimento).
 */
class FinancialCalculatorsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin->value]);
    }

    private function seller(): User
    {
        return User::factory()->create(['role' => UserRole::Seller->value]);
    }

    public function test_revenue_includes_only_paid_orders_and_adds_freight(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();

        // Pago: entra no faturamento.
        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 100,
            'discount' => 10,
            'freight' => 20,
        ]);

        // Novo (pendente): não entra.
        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Novo',
            'sale_price' => 500,
            'discount' => 0,
            'freight' => 0,
        ]);

        // Cancelado: não entra (RN-01).
        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Cancelado',
            'sale_price' => 999,
            'discount' => 0,
            'freight' => 0,
        ]);

        $revenue = RevenueCalculator::calculate($admin);

        // 100 - 10 + 20 = 110
        $this->assertEqualsWithDelta(110.0, $revenue, 0.001);
    }

    public function test_revenue_excludes_paid_status_orders_without_a_confirmed_payment(): void
    {
        // TASK-003: "pago" passa a ser decidido por `paid_at`, não pelo status
        // sozinho — um pedido nesse estado (ex.: dado legado sem confirmação
        // registrada) não entra em faturamento/lucro até `paid_at` existir.
        $admin = $this->admin();
        $seller = $this->seller();

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => null,
            'sale_price' => 500,
        ]);

        $this->assertEqualsWithDelta(0.0, RevenueCalculator::calculate($admin), 0.001);
    }

    public function test_revenue_subtracts_completed_refund_amount_but_not_pending_refund(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();
        $customer = \App\Models\Customer::factory()->create();

        $paidOrder = Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 300,
            'discount' => 0,
            'freight' => 0,
        ]);

        ProductReturn::create([
            'order_id' => $paidOrder->id,
            'customer_id' => $customer->id,
            'type' => 'devolucao',
            'status' => 'Reembolso Efetuado',
            'refund_amount' => 120,
        ]);

        $anotherPaidOrder = Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 200,
            'discount' => 0,
            'freight' => 0,
        ]);

        // Reembolso ainda pendente: não deve reduzir o faturamento ainda.
        ProductReturn::create([
            'order_id' => $anotherPaidOrder->id,
            'customer_id' => $customer->id,
            'type' => 'devolucao',
            'status' => 'Reembolso Pendente',
            'refund_amount' => 50,
        ]);

        $revenue = RevenueCalculator::calculate($admin);

        // (300 + 200) - 120 (só o reembolso efetuado) = 380
        $this->assertEqualsWithDelta(380.0, $revenue, 0.001);
    }

    public function test_sales_profit_subtracts_cost_and_channel_fee(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 300,
            'cost' => 100,
            'discount' => 0,
            'freight' => 15,
            'channel_fee' => 10,
        ]);

        $profit = SalesProfitCalculator::calculate($admin);

        // faturamento = 300 - 0 + 15 = 315
        // custos diretos = 100 (cost) + 10 (channel_fee) + 15 (freight) = 125
        // lucro = 315 - 125 = 190
        $this->assertEqualsWithDelta(190.0, $profit, 0.001);
    }

    public function test_sales_profit_subtracts_accrued_commission(): void
    {
        // TASK-005: pendência da TASK-001 resolvida — comissão real (via
        // CommissionCalculator) entra na fórmula, não mais como 0 fixo.
        $admin = $this->admin();
        $seller = $this->seller();

        $order = \App\Models\Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 300,
            'cost' => 100,
            'discount' => 0,
            'freight' => 15,
            'channel_fee' => 10,
        ]);
        $order->items()->update(['unit_commission' => 40]);

        $profit = SalesProfitCalculator::calculate($admin);

        // faturamento = 315; custos diretos = 125 (ver teste acima); comissão = 40.
        // lucro = 315 - 125 - 40 = 150.
        $this->assertEqualsWithDelta(150.0, $profit, 0.001);
    }

    public function test_net_result_subtracts_general_expenses_from_sales_profit(): void
    {
        $this->assertEqualsWithDelta(150.0, NetResultCalculator::calculate(200.0, 50.0), 0.001);

        // Sem despesas gerais informadas, resultado líquido = lucro das vendas.
        $this->assertEqualsWithDelta(200.0, NetResultCalculator::calculate(200.0), 0.001);
    }

    public function test_financial_summary_calculator_auto_plugs_general_expenses_of_the_period(): void
    {
        // TASK-006: pendência da TASK-001 resolvida — despesas gerais reais
        // (via GeneralExpenseCalculator) entram automaticamente no resultado
        // líquido quando o chamador não informa um valor explícito.
        $admin = $this->admin();
        $seller = $this->seller();

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 300,
            'cost' => 100,
            'discount' => 0,
            'freight' => 0,
            'channel_fee' => 0,
        ]);
        \App\Models\Expense::factory()->create(['amount' => 80, 'expense_date' => now()->toDateString()]);

        $summary = FinancialSummaryCalculator::calculate($admin);

        // faturamento = 300; custos diretos = 100 (cost); lucro = 200.
        $this->assertEqualsWithDelta(200.0, $summary->salesProfit, 0.001);
        $this->assertEqualsWithDelta(120.0, $summary->netResult, 0.001);
    }

    public function test_pending_amount_sums_only_new_and_awaiting_payment_orders(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Novo',
            'sale_price' => 100,
            'discount' => 5,
            'freight' => 10,
        ]);

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Aguardando Pagamento',
            'sale_price' => 200,
            'discount' => 0,
            'freight' => 0,
        ]);

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 1000,
            'discount' => 0,
            'freight' => 0,
        ]);

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Cancelado',
            'sale_price' => 500,
            'discount' => 0,
            'freight' => 0,
        ]);

        $pending = PendingAmountCalculator::calculate($admin);

        // (100 - 5 + 10) + 200 = 305
        $this->assertEqualsWithDelta(305.0, $pending, 0.001);
    }

    public function test_active_orders_excludes_only_delivered_and_cancelled(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();

        foreach (['Novo', 'Aguardando Pagamento', 'Pago', 'Separação/Fornecedor', 'Pronto para Envio', 'Enviado'] as $status) {
            Order::factory()->create(['seller_user_id' => $seller->id, 'status' => $status]);
        }
        Order::factory()->create(['seller_user_id' => $seller->id, 'status' => 'Entregue']);
        Order::factory()->create(['seller_user_id' => $seller->id, 'status' => 'Cancelado']);

        $this->assertSame(6, ActiveOrdersCalculator::calculate($admin));
    }

    public function test_seller_scope_only_aggregates_own_orders(): void
    {
        $seller = $this->seller();
        $otherSeller = $this->seller();

        Order::factory()->create(['seller_user_id' => $seller->id, 'status' => 'Pago', 'paid_at' => now(), 'sale_price' => 100, 'discount' => 0, 'freight' => 0]);
        Order::factory()->create(['seller_user_id' => $otherSeller->id, 'status' => 'Pago', 'paid_at' => now(), 'sale_price' => 900, 'discount' => 0, 'freight' => 0]);

        $this->assertEqualsWithDelta(100.0, RevenueCalculator::calculate($seller), 0.001);
        $this->assertSame(1, ActiveOrdersCalculator::calculate($seller));
    }

    public function test_revenue_period_uses_paid_at_not_sale_date(): void
    {
        // TASK-009 (RN-01): pedido vendido em janeiro mas confirmado como
        // pago em fevereiro entra no faturamento de FEVEREIRO, não janeiro.
        $admin = $this->admin();
        $seller = $this->seller();

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'sale_date' => '2026-01-15',
            'paid_at' => '2026-02-03',
            'sale_price' => 500,
            'discount' => 0,
            'freight' => 0,
        ]);

        $this->assertEqualsWithDelta(0.0, RevenueCalculator::calculate($admin, '2026-01-01', '2026-01-31'), 0.001);
        $this->assertEqualsWithDelta(500.0, RevenueCalculator::calculate($admin, '2026-02-01', '2026-02-28'), 0.001);
    }

    public function test_revenue_period_includes_confirmations_up_to_the_last_moment_of_the_end_date(): void
    {
        // Regressão de fuso/hora: paid_at tem hora (timestamp), sale_date não
        // — um whereBetween cru cortaria confirmações após 00:00 do último
        // dia do período. Confirma que isso não acontece mais.
        $admin = $this->admin();
        $seller = $this->seller();

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'sale_date' => '2026-02-28',
            'paid_at' => '2026-02-28 23:45:00',
            'sale_price' => 300,
            'discount' => 0,
            'freight' => 0,
        ]);

        $this->assertEqualsWithDelta(300.0, RevenueCalculator::calculate($admin, '2026-02-01', '2026-02-28'), 0.001);
    }

    public function test_period_without_movement_returns_zero_values_not_errors(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();

        // Pedido fora do período consultado.
        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => '2020-01-01',
            'sale_date' => '2020-01-01',
        ]);

        $summary = FinancialSummaryCalculator::calculate($admin, '2026-01-01', '2026-01-31');

        $this->assertEqualsWithDelta(0.0, $summary->revenue, 0.001);
        $this->assertEqualsWithDelta(0.0, $summary->salesProfit, 0.001);
        $this->assertEqualsWithDelta(0.0, $summary->netResult, 0.001);
        $this->assertEqualsWithDelta(0.0, $summary->pendingAmount, 0.001);
        $this->assertSame(0, $summary->activeOrders);
        // Margem não deve gerar divisão por zero (CA-03).
        $this->assertSame(0.0, $summary->salesMarginPercentage());
    }

    public function test_financial_summary_calculator_combines_all_indicators(): void
    {
        $admin = $this->admin();
        $seller = $this->seller();

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 300,
            'cost' => 100,
            'discount' => 0,
            'freight' => 10,
            'channel_fee' => 5,
        ]);

        Order::factory()->create([
            'seller_user_id' => $seller->id,
            'status' => 'Novo',
            'sale_price' => 150,
            'discount' => 0,
            'freight' => 0,
        ]);

        $summary = FinancialSummaryCalculator::calculate($admin);

        $this->assertEqualsWithDelta(310.0, $summary->revenue, 0.001); // 300 - 0 + 10
        $this->assertEqualsWithDelta(195.0, $summary->salesProfit, 0.001); // 310 - (100 + 5 + 10)
        $this->assertEqualsWithDelta(195.0, $summary->netResult, 0.001); // sem despesas gerais ainda
        $this->assertEqualsWithDelta(150.0, $summary->pendingAmount, 0.001);
        $this->assertSame(2, $summary->activeOrders);
        $this->assertEqualsWithDelta(62.9, $summary->salesMarginPercentage(), 0.05);
    }
}
