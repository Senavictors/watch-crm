<?php

namespace App\Support;

use App\Models\ProductReturn;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Carbon;

/**
 * Converte somente métricas determinísticas em fatos candidatos sem PII.
 * A IA recebe estes fatos e escolhe IDs; ela nunca recebe entidades ou
 * escreve o texto/número apresentado ao usuário.
 */
class AiOperationContextBuilder
{
    public static function build(User $user, DashboardPeriod $period): array
    {
        $dashboard = DashboardSummaryCalculator::build($user, $period);
        $now = Carbon::now((string) config('services.openai.summary_timezone', 'America/Sao_Paulo'));
        $facts = [];

        $canViewFinancials = $user->canViewFinancialReports();
        $canViewRevenue = $canViewFinancials || ! $user->canAccessAllRecords();

        if ($canViewRevenue) {
            self::addComparedMoneyFact($facts, 'financial.revenue', 'Faturamento', $dashboard['kpis']['revenue']);
        }

        if ($canViewFinancials) {
            self::addComparedMoneyFact($facts, 'financial.sales_profit', 'Lucro das vendas', $dashboard['kpis']['salesProfit']);
            self::addComparedMoneyFact($facts, 'financial.net_result', 'Resultado líquido', $dashboard['kpis']['netResult']);
            self::addComparedMoneyFact($facts, 'financial.expenses', 'Despesas gerais', $dashboard['kpis']['generalExpenses']);
        }

        $watches = $dashboard['kpis']['watchesSold']['value'];
        $orders = $dashboard['kpis']['ordersCount']['value'];
        $facts['sales.volume'] = self::fact(
            'sales.volume',
            sprintf('Foram vendidos %d relógios em %d pedidos pagos no período.', $watches, $orders),
            [self::source('Relógios vendidos', (string) $watches), self::source('Pedidos pagos', (string) $orders)]
        );

        $activeOrders = $dashboard['kpis']['activeOrders']['value'];
        $facts['operation.active_orders'] = self::fact(
            'operation.active_orders',
            sprintf('A operação possui %d pedidos ativos neste momento.', $activeOrders),
            [self::source('Pedidos ativos', (string) $activeOrders)]
        );

        $pendingAmount = $dashboard['kpis']['pendingAmount']['value'];
        $facts['operation.pending_payment'] = self::fact(
            'operation.pending_payment',
            sprintf('O valor atualmente aguardando pagamento é %s.', self::money($pendingAmount)),
            [self::source('Aguardando pagamento', self::money($pendingAmount))]
        );

        $companyGoal = $dashboard['goal']['company'];
        $goalText = $companyGoal
            ? sprintf('A meta geral está em %s, com %s de %s realizados.', self::percent($companyGoal['totalPercentage']), self::money($companyGoal['totalCurrent']), self::money($companyGoal['totalTarget']))
            : 'Não há meta geral ativa neste momento.';
        $goalSources = $companyGoal
            ? [
                self::source('Progresso da meta', self::percent($companyGoal['totalPercentage'])),
                self::source('Realizado', self::money($companyGoal['totalCurrent'])),
                self::source('Meta', self::money($companyGoal['totalTarget'])),
            ]
            : [self::source('Meta geral ativa', 'Não')];
        $facts['goal.company'] = self::fact('goal.company', $goalText, $goalSources);

        $shipments = collect($dashboard['nextShipments']);
        $lateShipments = $shipments->where('isLate', true)->count();
        $facts['shipping.queue'] = self::fact(
            'shipping.queue',
            sprintf('A fila destacada possui %d próximos envios, dos quais %d estão atrasados.', $shipments->count(), $lateShipments),
            [self::source('Próximos envios', (string) $shipments->count()), self::source('Envios atrasados', (string) $lateShipments)]
        );

        $terminalReturnStatuses = ['Concluído', 'Recusado', 'Cancelado', 'Reembolso Efetuado'];
        $returnsQuery = ProductReturn::query()->whereNotIn('status', $terminalReturnStatuses);
        if (! $user->canAccessAllRecords()) {
            $returnsQuery->where(function ($query) use ($user) {
                $query->where('created_by_user_id', $user->id)
                    ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('seller_user_id', $user->id));
            });
        }
        $openReturns = $returnsQuery->get(['status']);
        $topReturnStatuses = $openReturns->countBy('status')->sortDesc()->take(3);
        $statusText = $topReturnStatuses->isEmpty()
            ? 'nenhuma etapa ativa'
            : $topReturnStatuses->map(fn (int $count, string $status) => $status.': '.$count)->implode('; ');
        $facts['returns.open'] = self::fact(
            'returns.open',
            sprintf('Há %d garantias, trocas ou devoluções em andamento; distribuição principal: %s.', $openReturns->count(), $statusText),
            [self::source('Pós-vendas em andamento', (string) $openReturns->count()), self::source('Principais etapas', $statusText)]
        );

        $repurchase = CustomerInsightsCalculator::repurchaseAggregate($user);
        $facts['customers.repurchase'] = self::fact(
            'customers.repurchase',
            sprintf('%d clientes apresentam sinal determinístico de possível recompra entre %d com histórico suficiente.', $repurchase['possible'], $repurchase['evaluated']),
            [self::source('Possível recompra', (string) $repurchase['possible']), self::source('Clientes avaliáveis', (string) $repurchase['evaluated'])]
        );

        $waitlistQuery = WaitlistEntry::query()->whereIn('status', ['Pendente', 'Avisado']);
        if (! $user->canAccessAllRecords()) {
            $waitlistQuery->where('seller_user_id', $user->id);
        }
        $activeWaitlist = (clone $waitlistQuery)->count();
        $availableWaitlist = (clone $waitlistQuery)->whereHas('product', fn ($query) => $query->where('qty', '>', 0))->count();
        $facts['waitlist.active'] = self::fact(
            'waitlist.active',
            sprintf('A lista de espera possui %d interesses ativos, com %d produtos disponíveis agora.', $activeWaitlist, $availableWaitlist),
            [self::source('Interesses ativos', (string) $activeWaitlist), self::source('Disponíveis agora', (string) $availableWaitlist)]
        );

        return [
            'period' => ['from' => $period->from, 'to' => $period->to],
            'snapshotAt' => $now->toIso8601String(),
            'facts' => $facts,
        ];
    }

    private static function addComparedMoneyFact(array &$facts, string $id, string $label, array $kpi): void
    {
        $change = $kpi['percentageChange'];
        $comparison = $change === null
            ? 'sem percentual comparável porque o período anterior foi zero'
            : sprintf('variação de %s em relação ao período anterior', self::signedPercent($change));

        $facts[$id] = self::fact(
            $id,
            sprintf('%s no período: %s, com %s.', $label, self::money($kpi['value']), $comparison),
            [
                self::source($label, self::money($kpi['value'])),
                self::source($label.' anterior', self::money($kpi['previousValue'])),
                self::source('Variação', $change === null ? 'Não comparável' : self::signedPercent($change)),
            ]
        );
    }

    private static function fact(string $id, string $text, array $sources): array
    {
        return ['id' => $id, 'text' => $text, 'sources' => $sources];
    }

    private static function source(string $label, string $value): array
    {
        return ['label' => $label, 'value' => $value];
    }

    private static function money(float|int $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }

    private static function percent(float|int $value): string
    {
        return number_format((float) $value, 1, ',', '.').'%';
    }

    private static function signedPercent(float|int $value): string
    {
        $prefix = $value > 0 ? '+' : '';

        return $prefix.self::percent($value);
    }
}
