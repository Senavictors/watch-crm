<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\User;

/**
 * "Relógios vendidos" (docs/regras-de-negocio-dashboard.md §4.4): soma as
 * unidades de itens vendidos pertencentes à categoria `Relógios` — caixas e
 * outras categorias não entram. A regra da seção não lista devolução como
 * redutor deste indicador especificamente (diferente de faturamento, lucro,
 * meta e comissão — §10), então esta contagem é bruta por desenho.
 */
class WatchesSoldCalculator
{
    private const WATCHES_CATEGORY = 'Relógios';

    public static function calculate(User $user, ?string $startDate = null, ?string $endDate = null): int
    {
        $orderIds = OrderFinancialScope::ordersQuery($user, $startDate, $endDate, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return 0;
        }

        return (int) OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->where('product_type', self::WATCHES_CATEGORY)
            ->sum('quantity');
    }
}
