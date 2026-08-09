<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;

class OperationalAlertCalculator
{
    public static function calculate(
        User $user,
        array $conversion,
        array $previousConversion,
        array $payments,
        ?array $companyGoal,
        ?Carbon $snapshotAt = null
    ): array {
        $now = ($snapshotAt ?? Carbon::now())->copy();
        $alerts = [];

        if ($payments['expiredCount'] > 0) {
            $alerts[] = self::alert(
                'payments.expired',
                'critical',
                'Pagamentos vencidos',
                sprintf('%d %s com prazo de pagamento vencido.', $payments['expiredCount'], self::plural($payments['expiredCount'], 'pedido permanece', 'pedidos permanecem')),
                'Ver pagamentos pendentes',
                '/pedidos?paymentStatus=pending'
            );
        }

        if ($payments['expiringSoonCount'] > 0) {
            $alerts[] = self::alert(
                'payments.expiring_soon',
                'warning',
                'Prazo de pagamento próximo',
                sprintf('%d %s nas próximas %d horas.', $payments['expiringSoonCount'], self::plural($payments['expiringSoonCount'], 'pagamento vence', 'pagamentos vencem'), $payments['expirationWindowHours']),
                'Ver pagamentos pendentes',
                '/pedidos?paymentStatus=pending'
            );
        }

        $lateShipments = self::lateShipments($user, $now);
        if ($lateShipments > 0) {
            $alerts[] = self::alert(
                'shipping.late',
                'critical',
                'Envios atrasados',
                sprintf('%d %s fora da data prevista de postagem.', $lateShipments, self::plural($lateShipments, 'envio está', 'envios estão')),
                'Abrir fila de envios',
                '/envios'
            );
        }

        if ($conversion['rate'] !== null && $previousConversion['rate'] !== null) {
            $change = round($conversion['rate'] - $previousConversion['rate'], 1);
            if ($change <= -3) {
                $alerts[] = [
                    ...self::alert(
                        'conversion.drop',
                        $change <= -10 ? 'critical' : 'warning',
                        'Queda de conversão',
                        sprintf('A conversão de pagamento caiu %s pontos percentuais frente ao período anterior.', self::decimal(abs($change))),
                        'Ver pedidos do período',
                        '/pedidos'
                    ),
                    'value' => $change,
                    'unit' => 'percentage_point',
                ];
            }
        }

        if ($companyGoal && $companyGoal['totalPercentage'] >= 100) {
            $alerts[] = self::alert(
                'goal.achieved',
                'success',
                'Meta geral atingida',
                sprintf('A meta geral chegou a %s%%.', self::decimal($companyGoal['totalPercentage']))
            );
        }

        return $alerts;
    }

    private static function lateShipments(User $user, Carbon $now): int
    {
        $query = Order::query()
            ->whereNotNull('paid_at')
            ->whereNull('shipped_date')
            ->whereNotIn('status', ['Cancelado', 'Entregue']);

        if (! $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $user->id);
        }

        $enabledWeekdays = ShippingScheduleCalculator::enabledWeekdays();

        return $query->get(['paid_at'])
            ->filter(function (Order $order) use ($enabledWeekdays, $now) {
                $postingDate = ShippingScheduleCalculator::nextPostingDateForWeekdays($order->paid_at, $enabledWeekdays);

                return ShippingScheduleCalculator::isLate($postingDate, $now);
            })
            ->count();
    }

    private static function alert(
        string $type,
        string $severity,
        string $title,
        string $message,
        ?string $actionLabel = null,
        ?string $actionHref = null
    ): array {
        $alert = compact('type', 'severity', 'title', 'message');

        if ($actionLabel && $actionHref) {
            $alert['action'] = ['label' => $actionLabel, 'href' => $actionHref];
        }

        return $alert;
    }

    private static function plural(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? $singular : $plural;
    }

    private static function decimal(float|int $value): string
    {
        return number_format((float) $value, 1, ',', '.');
    }
}
