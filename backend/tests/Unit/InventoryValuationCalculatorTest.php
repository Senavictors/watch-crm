<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\InventoryValuationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-007 — Consolidar avaliação financeira do estoque.
 * CA-01: totais reconciliam com unidades listadas.
 * CA-02: venda, devolução e ajuste atualizam o indicador (aqui simulado por
 * uma mudança de `qty`, já que não há dedução de estoque por venda/
 * devolução implementada no sistema — ver docblock da classe).
 * CA-03: o indicador não recebe período (é sempre "atual").
 */
class InventoryValuationCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_sum_cost_and_price_of_in_stock_units_only(): void
    {
        Product::factory()->create(['stock' => 'IN_STOCK', 'cost' => 100, 'price' => 250, 'qty' => 3]);
        Product::factory()->create(['stock' => 'IN_STOCK', 'cost' => 200, 'price' => 500, 'qty' => 2]);

        $valuation = InventoryValuationCalculator::calculate();

        // custo: 100*3 + 200*2 = 700; potencial: 250*3 + 500*2 = 1750.
        $this->assertEqualsWithDelta(700.0, $valuation->totalCost, 0.001);
        $this->assertEqualsWithDelta(1750.0, $valuation->totalPotentialRevenue, 0.001);
        $this->assertSame(5, $valuation->totalUnits);
    }

    public function test_products_still_at_the_supplier_are_excluded(): void
    {
        // RN-03: só estoque físico disponível (IN_STOCK) entra.
        Product::factory()->create(['stock' => 'IN_STOCK', 'cost' => 100, 'price' => 250, 'qty' => 3]);
        Product::factory()->create(['stock' => 'SUPPLIER', 'cost' => 900, 'price' => 2000, 'qty' => 10]);

        $valuation = InventoryValuationCalculator::calculate();

        $this->assertEqualsWithDelta(300.0, $valuation->totalCost, 0.001);
        $this->assertEqualsWithDelta(750.0, $valuation->totalPotentialRevenue, 0.001);
        $this->assertSame(3, $valuation->totalUnits);
    }

    public function test_totals_reconcile_with_a_manual_sum_of_the_listed_units(): void
    {
        // CA-01
        Product::factory()->create(['stock' => 'IN_STOCK', 'cost' => 480, 'price' => 1050, 'qty' => 4]);
        Product::factory()->create(['stock' => 'IN_STOCK', 'cost' => 1800, 'price' => 4000, 'qty' => 1]);
        Product::factory()->create(['stock' => 'SUPPLIER', 'cost' => 160, 'price' => 400, 'qty' => 6]);

        $listed = Product::where('stock', 'IN_STOCK')->get();
        $expectedCost = $listed->sum(fn (Product $p) => $p->cost * $p->qty);
        $expectedRevenue = $listed->sum(fn (Product $p) => $p->price * $p->qty);
        $expectedUnits = $listed->sum('qty');

        $valuation = InventoryValuationCalculator::calculate();

        $this->assertEqualsWithDelta($expectedCost, $valuation->totalCost, 0.001);
        $this->assertEqualsWithDelta($expectedRevenue, $valuation->totalPotentialRevenue, 0.001);
        $this->assertSame($expectedUnits, $valuation->totalUnits);
    }

    public function test_indicator_reflects_a_stock_adjustment_immediately(): void
    {
        // CA-02: sem cache/snapshot — um ajuste de estoque (ex.: "Entrada",
        // ProductController::addQty) já reflete na próxima chamada.
        $product = Product::factory()->create(['stock' => 'IN_STOCK', 'cost' => 100, 'price' => 200, 'qty' => 2]);

        $this->assertEqualsWithDelta(200.0, InventoryValuationCalculator::calculate()->totalCost, 0.001);

        $product->increment('qty', 3);

        $this->assertEqualsWithDelta(500.0, InventoryValuationCalculator::calculate()->totalCost, 0.001);
    }

    public function test_no_stock_returns_zero_valuation_without_errors(): void
    {
        $valuation = InventoryValuationCalculator::calculate();

        $this->assertEqualsWithDelta(0.0, $valuation->totalCost, 0.001);
        $this->assertEqualsWithDelta(0.0, $valuation->totalPotentialRevenue, 0.001);
        $this->assertSame(0, $valuation->totalUnits);
        $this->assertEqualsWithDelta(0.0, $valuation->potentialProfit(), 0.001);
    }

    public function test_potential_profit_is_potential_revenue_minus_cost(): void
    {
        Product::factory()->create(['stock' => 'IN_STOCK', 'cost' => 100, 'price' => 250, 'qty' => 2]);

        $valuation = InventoryValuationCalculator::calculate();

        $this->assertEqualsWithDelta(300.0, $valuation->potentialProfit(), 0.001);
    }
}
