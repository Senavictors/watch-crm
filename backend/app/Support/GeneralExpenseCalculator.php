<?php

namespace App\Support;

use App\Models\Expense;

/**
 * Despesas gerais (docs/domain/financial-rules.md "Despesas" e "Resultado
 * líquido"). RN-03: o total do período usa `expense_date`, não `created_at`
 * (a despesa pode ser lançada com atraso, mas pertence à competência da
 * data em que de fato ocorreu). Não recebe `User` — diferente das métricas
 * de pedido, despesa geral não é escopada por vendedor (é um custo da
 * empresa como um todo); quem pode ver o total já é decidido pela
 * permissão da rota/relatório que chama esta classe.
 */
class GeneralExpenseCalculator
{
    public static function total(?string $startDate = null, ?string $endDate = null): float
    {
        $query = Expense::query();

        if ($startDate && $endDate) {
            $query->whereBetween('expense_date', [$startDate, $endDate]);
        }

        return (float) $query->sum('amount');
    }
}
