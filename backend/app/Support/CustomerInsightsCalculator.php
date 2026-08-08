<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * TASK-019 (RN-01, CA-01, CA-03) — insights determinísticos de cliente:
 * gasto total, ticket médio, data da última compra e um sinal de possível
 * recompra.
 *
 * RN-01/CA-01: "compra" usa exatamente a mesma semântica já implementada em
 * `RevenueCalculator` (pedido pago — `paid_at` preenchido, `status !=
 * Cancelado` — líquido de devolução com `status = 'Reembolso Efetuado'`).
 * Este calculador não duplica essa lógica: reaproveita
 * `OrderFinancialScope`/`RevenueCalculator` parametrizados por
 * `customer_id`.
 */
class CustomerInsightsCalculator
{
    public static function build(User $user, Customer $customer): array
    {
        $paidOrders = OrderFinancialScope::ordersQuery($user, null, null, 'paid_at', $customer->id)
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->orderBy('paid_at')
            ->get(['id', 'paid_at']);

        $orderCount = $paidOrders->count();
        $totalSpent = RevenueCalculator::calculate($user, null, null, $customer->id);
        $averageTicket = $orderCount > 0 ? $totalSpent / $orderCount : null;
        $lastOrderAt = $orderCount > 0 ? $paidOrders->last()->paid_at->toIso8601String() : null;

        return [
            'totalSpent' => $totalSpent,
            'orderCount' => $orderCount,
            'averageTicket' => $averageTicket,
            'lastOrderAt' => $lastOrderAt,
            'possibleRepurchase' => self::possibleRepurchase($paidOrders),
        ];
    }

    /**
     * TASK-021 — contagem agregada, sem carregar ou expor qualquer dado
     * pessoal. Reaproveita exatamente a mesma heurística de recompra usada
     * no detalhe do cliente.
     */
    public static function repurchaseAggregate(User $user): array
    {
        $ordersByCustomer = OrderFinancialScope::ordersQuery($user, null, null, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->orderBy('paid_at')
            ->get(['customer_id', 'paid_at'])
            ->groupBy('customer_id');

        $evaluated = 0;
        $possible = 0;

        foreach ($ordersByCustomer as $orders) {
            $signal = self::possibleRepurchase($orders->values());
            if ($signal === null) {
                continue;
            }

            $evaluated++;
            if ($signal) {
                $possible++;
            }
        }

        return ['evaluated' => $evaluated, 'possible' => $possible];
    }

    /**
     * Heurística determinística e transparente (CA-03) — NÃO é um modelo
     * preditivo/IA (fora de escopo desta task por decisão explícita): é uma
     * média aritmética simples do intervalo (em dias) entre compras pagas
     * consecutivas, comparada ao tempo já decorrido desde a última compra.
     * O nome do campo (`possibleRepurchase`) e o tipo do valor (`bool|null`,
     * nunca um score/percentual) reforçam que é um sinal, não uma certeza.
     *
     * `null` (nunca `false` por padrão) com menos de 2 pedidos pagos: dados
     * insuficientes para qualquer inferência de intervalo — distinto de
     * "não é candidato a recompra" (`false`).
     *
     * @param  Collection<int, \App\Models\Order>  $paidOrders  ordenados por `paid_at` ascendente
     */
    private static function possibleRepurchase(Collection $paidOrders): ?bool
    {
        if ($paidOrders->count() < 2) {
            return null;
        }

        $dates = $paidOrders->pluck('paid_at')->values();
        $intervalsInDays = [];

        for ($i = 1; $i < $dates->count(); $i++) {
            $intervalsInDays[] = $dates[$i - 1]->diffInDays($dates[$i]);
        }

        $averageIntervalDays = array_sum($intervalsInDays) / count($intervalsInDays);
        $daysSinceLastOrder = $dates->last()->diffInDays(Carbon::now());

        return $daysSinceLastOrder >= $averageIntervalDays;
    }
}
