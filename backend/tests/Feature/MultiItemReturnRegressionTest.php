<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductReturn;
use App\Models\ReturnItem;
use App\Models\User;
use App\Support\CommissionCalculator;
use App\Support\RevenueCalculator;
use App\Support\SalesProfitCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-015 (RN-01) — pedido multi-item com devolução PARCIAL de apenas um
 * dos itens: garante que faturamento, lucro das vendas, comissão e a rosca
 * de categorias do dashboard refletem só a fração devolvida daquele item
 * específico, mantendo o restante do pedido intacto.
 *
 * Decisão de local: arquivo dedicado (`MultiItemReturnRegressionTest`) em
 * vez de acrescentar a `DashboardControllerTest`/`FinancialCalculatorsTest`
 * — o cenário amarra três camadas ao mesmo tempo (Order/OrderItem/
 * ProductReturn diretamente no banco + calculadores de `Support/` +
 * payload HTTP do dashboard). Não é "mais um caso" de nenhum dos dois
 * arquivos existentes; é uma prova de integração própria, e um nome de
 * arquivo específico deixa a lacuna fechada fácil de achar depois.
 */
class MultiItemReturnRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_return_of_one_item_in_a_multi_item_order_reflects_only_that_fraction(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $seller = User::factory()->create(['role' => UserRole::Seller->value]);
        $customer = Customer::factory()->create();

        $order = Order::factory()->create([
            'seller_user_id' => $seller->id,
            'customer_id' => $customer->id,
            'status' => 'Pago',
            'paid_at' => now(),
            'sale_price' => 690, // 2x300 (Relógios) + 1x90 (Caixas)
            'discount' => 0,
            'freight' => 0,
            'cost' => 220, // 2x100 (Relógios) + 20 (Caixas)
            'channel_fee' => 0,
        ]);
        $order->items()->delete();

        $watchItem = OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Relógio Multi',
            'product_type' => 'Relógios',
            'quantity' => 2,
            'unit_price' => 300,
            'unit_cost' => 100,
            'unit_commission' => 30,
            'unit_discount' => 0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Caixa Multi',
            'product_type' => 'Caixas',
            'quantity' => 1,
            'unit_price' => 90,
            'unit_cost' => 20,
            'unit_commission' => 5,
            'unit_discount' => 0,
        ]);

        // Devolução PARCIAL: só 1 das 2 unidades do item "Relógios", com
        // reembolso já efetuado. O item "Caixas" não é tocado.
        $productReturn = ProductReturn::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $admin->id,
            'type' => 'devolucao',
            'status' => 'Reembolso Efetuado',
            'refund_amount' => 300, // 1 unidade a 300
        ]);
        ReturnItem::create([
            'return_id' => $productReturn->id,
            'order_item_id' => $watchItem->id,
            'product_name' => 'Relógio Multi',
            'product_type' => 'Relógios',
            'quantity' => 1,
            'unit_price' => 300,
        ]);

        // Faturamento: (690 - 0 + 0) - 300 (reembolso efetuado) = 390.
        $this->assertEqualsWithDelta(390.0, RevenueCalculator::calculate($admin), 0.001);

        // Comissão: item "Relógios" líquido = 2 - 1 = 1 unidade * 30 = 30;
        // item "Caixas" intacto = 1 * 5 = 5. Total = 35.
        $this->assertEqualsWithDelta(35.0, CommissionCalculator::accrued($admin), 0.001);

        // Lucro das vendas: faturamento(390) - custos diretos(cost + channel_fee + freight = 220) - comissão(35) = 135.
        $this->assertEqualsWithDelta(135.0, SalesProfitCalculator::calculate($admin), 0.001);

        // Categorias do dashboard (via DashboardSummaryCalculator::categories,
        // privado — inspecionado pelo payload HTTP, mesmo padrão de
        // `DashboardControllerTest`): "Relógios" reflete só a unidade não
        // devolvida; "Caixas" permanece intacta.
        $payload = $this->actingAs($admin)->getJson('/api/dashboard/summary')->assertOk()->json();
        $categories = collect($payload['categories'])->keyBy('category');

        $this->assertEqualsWithDelta(300.0, $categories['Relógios']['revenue'], 0.001); // 1 unidade líquida * 300
        $this->assertSame(1, $categories['Relógios']['units']);
        $this->assertEqualsWithDelta(90.0, $categories['Caixas']['revenue'], 0.001); // intacto
        $this->assertSame(1, $categories['Caixas']['units']);
    }
}
