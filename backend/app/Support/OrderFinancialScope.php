<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Escopo compartilhado pelos calculadores financeiros (RevenueCalculator,
 * SalesProfitCalculator, CommissionCalculator, PendingAmountCalculator,
 * ActiveOrdersCalculator): aplica o mesmo critério de ownership por
 * vendedor já usado em OrderController::index().
 *
 * TASK-009 (RN-01, docs/domain/financial-rules.md "Data de competência
 * operacional" e docs/regras-de-negocio-dashboard.md §2): indicadores
 * financeiros usam a data de CONFIRMAÇÃO do pagamento (`paid_at`) como
 * competência de período, não `sale_date`. Essa era a regra documentada
 * desde a TASK-001, mas o filtro de período ficou em `sale_date` por
 * engano até esta task — corrigido aqui, com `$dateColumn` explícito
 * porque nem todo consumidor pode usar `paid_at`: `PendingAmountCalculator`
 * e `ActiveOrdersCalculator` agregam pedidos que ainda NÃO têm `paid_at`
 * (pendentes/ativos incluem status não pagos), então continuam filtrando
 * por `sale_date` (default do parâmetro, para não quebrar esses dois).
 */
class OrderFinancialScope
{
    public static function apply(
        Builder $query,
        User $user,
        ?string $startDate = null,
        ?string $endDate = null,
        string $dateColumn = 'sale_date'
    ): Builder {
        if (! $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $user->id);
        }

        if ($startDate && $endDate) {
            // `whereDate` (não `whereBetween` cru) porque `paid_at` é
            // timestamp (tem hora) — um `whereBetween` literal excluiria
            // qualquer confirmação depois da meia-noite do último dia do
            // período. `sale_date` é `date` puro, então `whereDate` também
            // funciona sem diferença de comportamento nesse caso.
            $query->whereDate($dateColumn, '>=', $startDate)
                ->whereDate($dateColumn, '<=', $endDate);
        }

        return $query;
    }

    /**
     * Fábrica de query nova (evita reaproveitar uma instância de Builder já
     * mutada por selectRaw/orderBy entre chamadas sucessivas de um mesmo
     * calculador).
     */
    public static function ordersQuery(
        User $user,
        ?string $startDate = null,
        ?string $endDate = null,
        string $dateColumn = 'sale_date'
    ): Builder {
        return self::apply(Order::query(), $user, $startDate, $endDate, $dateColumn);
    }
}
