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
        $previousWatches = $dashboard['kpis']['watchesSold']['previousValue'];
        $watchesDifference = $watches - $previousWatches;
        $orders = $dashboard['kpis']['ordersCount']['value'];
        $facts['sales.volume'] = self::fact(
            'sales.volume',
            sprintf(
                'Foram vendidos %d relógios em %d pedidos pagos no período, %s em relação ao período anterior.',
                $watches,
                $orders,
                $watchesDifference === 0 ? 'sem variação de volume' : sprintf('%d %s', abs($watchesDifference), $watchesDifference > 0 ? 'peças a mais' : 'peças a menos')
            ),
            [
                self::source('Relógios vendidos', (string) $watches),
                self::source('Relógios no período anterior', (string) $previousWatches),
                self::source('Diferença de volume', (string) $watchesDifference),
                self::source('Pedidos pagos', (string) $orders),
            ],
            'sales_volume',
            'quantity',
            ['currentValue' => $watches, 'previousValue' => $previousWatches, 'difference' => $watchesDifference, 'paidOrders' => $orders]
        );

        $conversion = $dashboard['conversion'];
        $currentConversion = $conversion['current'];
        $conversionText = $currentConversion['rate'] === null
            ? 'Não houve pedidos registrados no período para calcular a conversão de pagamento.'
            : sprintf(
                'Dos %d pedidos registrados, %d foram pagos: conversão de %s, %s.',
                $currentConversion['ordersCreated'],
                $currentConversion['paidOrders'],
                self::percent($currentConversion['rate']),
                $conversion['percentagePointChange'] === null
                    ? 'sem comparação disponível com o período anterior'
                    : sprintf('%s de %s pontos percentuais frente ao período anterior', $conversion['percentagePointChange'] >= 0 ? 'alta' : 'queda', self::decimal(abs($conversion['percentagePointChange'])))
            );
        $facts['sales.conversion'] = self::fact(
            'sales.conversion',
            $conversionText,
            [
                self::source('Pedidos registrados', (string) $currentConversion['ordersCreated']),
                self::source('Pedidos pagos', (string) $currentConversion['paidOrders']),
                self::source('Taxa de conversão', $currentConversion['rate'] === null ? 'Não calculável' : self::percent($currentConversion['rate'])),
                self::source('Variação', $conversion['percentagePointChange'] === null ? 'Não comparável' : self::signedDecimal($conversion['percentagePointChange']).' p.p.'),
            ],
            'conversion_rate',
            'percentage',
            [
                'currentValue' => $currentConversion['rate'],
                'previousValue' => $conversion['previous']['rate'],
                'percentagePointChange' => $conversion['percentagePointChange'],
                'ordersCreated' => $currentConversion['ordersCreated'],
                'paidOrders' => $currentConversion['paidOrders'],
            ]
        );

        $channelConversions = collect($currentConversion['channels'])->take(3);
        if ($channelConversions->isNotEmpty()) {
            $channelText = $channelConversions
                ->map(fn (array $channel) => sprintf('%s: %s (%d de %d)', $channel['channel'], $channel['rate'] === null ? 'não calculável' : self::percent($channel['rate']), $channel['paidOrders'], $channel['ordersCreated']))
                ->implode('; ');
            $facts['sales.conversion_channels'] = self::fact(
                'sales.conversion_channels',
                'Conversão de pagamento nos principais canais: '.$channelText.'.',
                $channelConversions->map(fn (array $channel) => self::source($channel['channel'], $channel['rate'] === null ? 'Não calculável' : self::percent($channel['rate'])))->all(),
                'conversion_by_channel',
                'percentage',
                ['channels' => $channelConversions->values()->all()]
            );
        }

        $activeOrders = $dashboard['kpis']['activeOrders']['value'];
        $facts['operation.active_orders'] = self::fact(
            'operation.active_orders',
            sprintf('A operação possui %d pedidos ativos neste momento.', $activeOrders),
            [self::source('Pedidos ativos', (string) $activeOrders)]
        );

        $pendingPayments = $dashboard['pendingPayments'];
        $pendingAmount = $pendingPayments['amount'];
        $facts['operation.pending_payment'] = self::fact(
            'operation.pending_payment',
            sprintf(
                'Há %d pedidos aguardando pagamento, somando %s; espera média de %s horas e maior espera de %s horas.',
                $pendingPayments['count'],
                self::money($pendingAmount),
                self::decimal($pendingPayments['averageWaitHours']),
                self::decimal($pendingPayments['oldestWaitHours'])
            ),
            [
                self::source('Pedidos aguardando pagamento', (string) $pendingPayments['count']),
                self::source('Valor aguardando pagamento', self::money($pendingAmount)),
                self::source('Espera média', self::decimal($pendingPayments['averageWaitHours']).' h'),
                self::source('Maior espera', self::decimal($pendingPayments['oldestWaitHours']).' h'),
            ],
            'payment_recovery',
            'currency',
            [
                'count' => $pendingPayments['count'],
                'currentValue' => $pendingAmount,
                'averageWaitHours' => $pendingPayments['averageWaitHours'],
                'oldestWaitHours' => $pendingPayments['oldestWaitHours'],
            ]
        );

        $companyGoal = $dashboard['goal']['company'];
        $goalIsQuantity = ($companyGoal['calculationType'] ?? null) === 'quantity';
        $goalText = $companyGoal
            ? sprintf(
                'A meta geral está em %s, com %s de %s realizados.',
                self::percent($companyGoal['totalPercentage']),
                $goalIsQuantity ? self::quantity($companyGoal['totalCurrent']) : self::money($companyGoal['totalCurrent']),
                $goalIsQuantity ? self::quantity($companyGoal['totalTarget']) : self::money($companyGoal['totalTarget'])
            )
            : 'Não há meta geral ativa neste momento.';
        $goalSources = $companyGoal
            ? [
                self::source('Progresso da meta', self::percent($companyGoal['totalPercentage'])),
                self::source('Realizado', $goalIsQuantity ? self::quantity($companyGoal['totalCurrent']) : self::money($companyGoal['totalCurrent'])),
                self::source('Meta', $goalIsQuantity ? self::quantity($companyGoal['totalTarget']) : self::money($companyGoal['totalTarget'])),
            ]
            : [self::source('Meta geral ativa', 'Não')];
        $facts['goal.company'] = self::fact(
            'goal.company',
            $goalText,
            $goalSources,
            'goal_achievement',
            $goalIsQuantity ? 'quantity' : ($companyGoal ? 'currency' : 'count'),
            $companyGoal ? [
                'currentValue' => $companyGoal['totalCurrent'],
                'targetValue' => $companyGoal['totalTarget'],
                'percentage' => $companyGoal['totalPercentage'],
            ] : ['active' => false]
        );

        $shipments = collect($dashboard['nextShipments']);
        $lateShipments = $shipments->where('isLate', true)->count();
        $facts['shipping.queue'] = self::fact(
            'shipping.queue',
            sprintf('A fila destacada possui %d próximos envios, dos quais %d estão atrasados.', $shipments->count(), $lateShipments),
            [self::source('Próximos envios', (string) $shipments->count()), self::source('Envios atrasados', (string) $lateShipments)]
        );

        $terminalReturnStatuses = ['Concluído', 'Recusado', 'Cancelado', 'Reembolso Efetuado'];
        // TASK-025 (ADR-007): devolução estornada não é mais um pós-venda
        // "em andamento" — o registro ficou, o processo não.
        // TASK-026 (RN-03): o contexto de IA usa o MESMO escopo da listagem
        // em vez de repetir a condição — antes daqui faltava
        // `assigned_user_id`, então a contagem divergia do que o usuário via
        // na tela.
        $returnsQuery = ProductReturn::query()
            ->visibleTo($user)
            ->effective()
            ->whereNotIn('status', $terminalReturnStatuses);
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
            ],
            $id,
            'currency',
            ['currentValue' => $kpi['value'], 'previousValue' => $kpi['previousValue'], 'percentageChange' => $change]
        );
    }

    private static function fact(
        string $id,
        string $text,
        array $sources,
        ?string $context = null,
        string $type = 'count',
        array $values = []
    ): array {
        return [
            'id' => $id,
            'context' => $context ?? $id,
            'type' => $type,
            'values' => $values,
            'text' => $text,
            'sources' => $sources,
        ];
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

    private static function decimal(float|int $value): string
    {
        return number_format((float) $value, 1, ',', '.');
    }

    private static function signedDecimal(float|int $value): string
    {
        return ($value > 0 ? '+' : '').self::decimal($value);
    }

    private static function quantity(float|int $value): string
    {
        $decimals = fmod((float) $value, 1.0) === 0.0 ? 0 : 1;

        return number_format((float) $value, $decimals, ',', '.').' unidades';
    }

    private static function signedPercent(float|int $value): string
    {
        $prefix = $value > 0 ? '+' : '';

        return $prefix.self::percent($value);
    }
}
