<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

class PendingPaymentInsightsCalculator
{
    public static function calculate(User $user, ?Carbon $snapshotAt = null): array
    {
        $now = ($snapshotAt ?? Carbon::now())->copy();
        $orders = OrderFinancialScope::ordersQuery($user)
            ->whereIn('status', OrderMetadata::PENDING_PAYMENT_STATUSES)
            ->get([
                'created_at',
                'sale_price',
                'discount',
                'freight',
                'payment_method',
                'payment_expires_at',
            ]);

        $waitHours = $orders
            ->map(fn ($order) => max($order->created_at?->diffInMinutes($now, false) ?? 0, 0) / 60)
            ->values();
        $withExpiration = $orders->filter(fn ($order) => $order->payment_expires_at !== null);
        $expired = $withExpiration->filter(fn ($order) => $order->payment_expires_at->lessThanOrEqualTo($now));
        $expiringSoon = $withExpiration->filter(fn ($order) => $order->payment_expires_at->greaterThan($now)
            && $order->payment_expires_at->lessThanOrEqualTo($now->copy()->addHours(2)));

        return [
            'count' => $orders->count(),
            'amount' => round((float) $orders->sum(fn ($order) => (float) $order->sale_price - (float) $order->discount + (float) $order->freight), 2),
            'averageWaitHours' => $waitHours->isEmpty() ? 0.0 : round((float) $waitHours->average(), 1),
            'oldestWaitHours' => $waitHours->isEmpty() ? 0.0 : round((float) $waitHours->max(), 1),
            'expiredCount' => $expired->count(),
            'expiringSoonCount' => $expiringSoon->count(),
            'expirationWindowHours' => 2,
        ];
    }
}
