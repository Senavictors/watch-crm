<?php

namespace App\Support;

use App\Models\User;

/**
 * Valores pendentes (docs/domain/financial-rules.md): soma o valor integral
 * dos pedidos ainda não pagos (`Novo` e `Aguardando Pagamento`). Pedidos
 * cancelados não entram.
 */
class PendingAmountCalculator
{
    public static function calculate(User $user, ?string $startDate = null, ?string $endDate = null): float
    {
        return (float) OrderFinancialScope::ordersQuery($user, $startDate, $endDate)
            ->whereIn('status', OrderMetadata::PENDING_PAYMENT_STATUSES)
            ->selectRaw('COALESCE(SUM(sale_price - discount + freight), 0) as total')
            ->value('total');
    }
}
