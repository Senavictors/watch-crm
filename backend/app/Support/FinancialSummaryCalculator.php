<?php

namespace App\Support;

use App\Models\User;

/**
 * Ponto de entrada único dos cinco indicadores da TASK-001. Controllers
 * futuros (ex.: API agregada da TASK-009) devem consumir esta classe em vez
 * de recalcular qualquer uma das fórmulas.
 */
class FinancialSummaryCalculator
{
    public static function calculate(
        User $user,
        ?string $startDate = null,
        ?string $endDate = null,
        float $generalExpenses = 0.0
    ): FinancialSummary {
        $revenue = RevenueCalculator::calculate($user, $startDate, $endDate);
        $salesProfit = SalesProfitCalculator::calculate($user, $startDate, $endDate, $revenue);
        $netResult = NetResultCalculator::calculate($salesProfit, $generalExpenses);
        $pendingAmount = PendingAmountCalculator::calculate($user, $startDate, $endDate);
        $activeOrders = ActiveOrdersCalculator::calculate($user, $startDate, $endDate);

        return new FinancialSummary($revenue, $salesProfit, $netResult, $pendingAmount, $activeOrders);
    }
}
