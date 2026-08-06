<?php

namespace App\Support;

/**
 * Resultado líquido (docs/domain/financial-rules.md):
 *
 *   Resultado líquido = lucro das vendas - despesas gerais do período
 *
 * Permanece uma função pura (não busca dados sozinha) — quem decide o total
 * de despesas gerais é `FinancialSummaryCalculator` (via
 * `GeneralExpenseCalculator`, TASK-006). `$generalExpenses` continua com
 * default `0.0` aqui só para quem chama esta classe isoladamente (ex.:
 * testes, ou um consumidor futuro que já tenha o total em mãos).
 */
class NetResultCalculator
{
    public static function calculate(float $salesProfit, float $generalExpenses = 0.0): float
    {
        return $salesProfit - $generalExpenses;
    }
}
