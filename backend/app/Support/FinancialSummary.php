<?php

namespace App\Support;

/**
 * Value object de resultado do FinancialSummaryCalculator. Reúne os cinco
 * indicadores centralizados pela TASK-001: faturamento, lucro das vendas,
 * resultado líquido, valores pendentes e pedidos ativos.
 */
class FinancialSummary
{
    public function __construct(
        public readonly float $revenue,
        public readonly float $salesProfit,
        public readonly float $netResult,
        public readonly float $pendingAmount,
        public readonly int $activeOrders,
    ) {}

    /**
     * Margem do lucro das vendas sobre o faturamento, em percentual.
     * CA-03: período sem faturamento retorna 0, nunca divide por zero.
     */
    public function salesMarginPercentage(): float
    {
        if ($this->revenue <= 0.0) {
            return 0.0;
        }

        return round(($this->salesProfit / $this->revenue) * 100, 1);
    }

    public function toArray(): array
    {
        return [
            'revenue' => round($this->revenue, 2),
            'salesProfit' => round($this->salesProfit, 2),
            'netResult' => round($this->netResult, 2),
            'pendingAmount' => round($this->pendingAmount, 2),
            'activeOrders' => $this->activeOrders,
            'salesMarginPercentage' => $this->salesMarginPercentage(),
        ];
    }
}
