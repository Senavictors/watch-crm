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
 * "Pago" é decidido por `orders.paid_at` (TASK-003), não pelo status
 * diretamente — um pedido cancelado depois de pago preserva `paid_at` como
 * registro histórico, então o status `Cancelado` continua excluído
 * explicitamente aqui. Reembolso parcial reduz apenas o valor efetivamente
 * devolvido (`returns.refund_amount`), não o pedido inteiro.
 *
 * TASK-009 (RN-01): o período filtra por `paid_at` (data de competência),
 * não por `sale_date` — ver `OrderFinancialScope`.
 */
class RevenueCalculator
{
    public static function calculate(User $user, ?string $startDate = null, ?string $endDate = null): float
    {
        $paidOrders = fn () => OrderFinancialScope::ordersQuery($user, $startDate, $endDate, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado');

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
