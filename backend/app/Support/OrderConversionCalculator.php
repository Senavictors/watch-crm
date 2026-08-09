<?php

namespace App\Support;

use App\Models\User;

class OrderConversionCalculator
{
    /**
     * Conversao de pagamento por coorte: pedidos criados no periodo que
     * permanecem pagos e nao cancelados / todos os pedidos criados no periodo.
     */
    public static function calculate(User $user, string $from, string $to): array
    {
        $base = OrderFinancialScope::ordersQuery($user, $from, $to, 'created_at');
        $total = (clone $base)->count();
        $paid = (clone $base)
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->count();

        $channels = (clone $base)
            ->selectRaw("channel, COUNT(*) as total, SUM(CASE WHEN paid_at IS NOT NULL AND status != 'Cancelado' THEN 1 ELSE 0 END) as paid")
            ->groupBy('channel')
            ->get()
            ->map(fn ($row) => self::result((int) $row->total, (int) $row->paid, (string) $row->channel))
            ->sortByDesc('total')
            ->values()
            ->all();

        return [...self::result($total, $paid), 'channels' => $channels];
    }

    private static function result(int $total, int $paid, ?string $channel = null): array
    {
        $result = [
            'ordersCreated' => $total,
            'paidOrders' => $paid,
            'rate' => $total > 0 ? round(($paid / $total) * 100, 1) : null,
        ];

        return $channel === null ? $result : ['channel' => $channel, ...$result];
    }
}
