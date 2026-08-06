<?php

namespace App\Support;

/**
 * Resultado líquido (docs/domain/financial-rules.md):
 *
 *   Resultado líquido = lucro das vendas - despesas gerais do período
 *
 * `$generalExpenses` é 0.0 por padrão porque o módulo de despesas gerais
 * ainda não existe (TASK-006, que depende desta task). Quando TASK-006
 * estiver pronta, o consumidor passa o total real do período aqui — a
 * fórmula em si já está centralizada e não deve ser duplicada em outro lugar.
 */
class NetResultCalculator
{
    public static function calculate(float $salesProfit, float $generalExpenses = 0.0): float
    {
        return $salesProfit - $generalExpenses;
    }
}
