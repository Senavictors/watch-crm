<?php

namespace App\Support;

use App\Models\ProductReturn;
use App\Models\User;

/**
 * Faturamento (docs/domain/financial-rules.md):
 *
 *   Faturamento = itens vendidos - descontos + frete cobrado
 *
 * RN-01: somente pedidos pagos entram; cancelamentos e reembolsos saem.
 * Reembolso parcial reduz apenas o valor efetivamente devolvido
 * (`returns.refund_amount`), não o pedido inteiro.
 */
class RevenueCalculator
{
    public static function calculate(User $user, ?string $startDate = null, ?string $endDate = null): float
    {
        $paidOrders = fn () => OrderFinancialScope::ordersQuery($user, $startDate, $endDate)
            ->whereIn('status', OrderMetadata::PAID_STATUSES);

        $gross = (float) $paidOrders()
            ->selectRaw('COALESCE(SUM(sale_price - discount + freight), 0) as total')
            ->value('total');

        $orderIds = $paidOrders()->pluck('id');

        if ($orderIds->isEmpty()) {
            return $gross;
        }

        $refunds = (float) ProductReturn::query()
            ->whereIn('order_id', $orderIds)
            ->whereNotNull('refund_amount')
            ->where('status', 'Reembolso Efetuado')
            ->sum('refund_amount');

        return $gross - $refunds;
    }
}
