<?php

namespace App\Support;

/**
 * Value object de resultado do InventoryValuationCalculator (TASK-007).
 */
class InventoryValuation
{
    public function __construct(
        public readonly float $totalCost,
        public readonly float $totalPotentialRevenue,
        public readonly int $totalUnits,
    ) {}

    /**
     * Lucro potencial se todo o estoque físico disponível fosse vendido ao
     * preço cadastrado (RN-02) — não é lucro realizado, é uma projeção.
     */
    public function potentialProfit(): float
    {
        return $this->totalPotentialRevenue - $this->totalCost;
    }

    public function toArray(): array
    {
        return [
            'totalCost' => round($this->totalCost, 2),
            'totalPotentialRevenue' => round($this->totalPotentialRevenue, 2),
            'potentialProfit' => round($this->potentialProfit(), 2),
            'totalUnits' => $this->totalUnits,
        ];
    }
}
