<?php

namespace App\Support;

use App\Models\User;

/**
 * Pedidos ativos (RN-02, glossary.md): todo pedido cujo status não seja
 * `Entregue` nem `Cancelado`. É uma contagem "ao vivo" — o filtro de período
 * é opcional e só se aplica se o consumidor passar `$startDate`/`$endDate`.
 */
class ActiveOrdersCalculator
{
    public static function calculate(User $user, ?string $startDate = null, ?string $endDate = null): int
    {
        return OrderFinancialScope::ordersQuery($user, $startDate, $endDate)
            ->whereNotIn('status', OrderMetadata::ACTIVE_EXCLUDED_STATUSES)
            ->count();
    }
}
