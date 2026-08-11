<?php

namespace App\Support;

use App\Models\Goal;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TASK-009 — orquestrador do `GET /dashboard/summary`
 * (docs/api/dashboard.md, docs/regras-de-negocio-dashboard.md).
 *
 * Calcula TUDO internamente, sem gate de permissão — quem decide o que
 * entra no payload final é `DashboardController::toPayload()` (CA-04),
 * mesmo padrão já usado por `OrderController`/`ProductController` (TASK-013:
 * o controller decide o que expor por papel, o calculador não sabe de
 * permissão). Isolar o gate no controller evita duplicar a regra de acesso
 * em cada método deste calculador.
 */
class DashboardSummaryCalculator
{
    private const WATCHES_CATEGORY = 'Relógios';

    public static function build(User $user, DashboardPeriod $period): array
    {
        $kpis = self::kpis($user, $period);
        $goal = self::goal($user);
        $conversion = OrderConversionCalculator::calculate($user, $period->from, $period->to);
        $previousConversion = OrderConversionCalculator::calculate($user, $period->comparisonFrom, $period->comparisonTo);
        $payments = PendingPaymentInsightsCalculator::calculate($user);

        return [
            'kpis' => [
                ...$kpis,
                'conversionRate' => [
                    'value' => $conversion['rate'],
                    'previousValue' => $previousConversion['rate'],
                    'percentagePointChange' => self::percentagePointChange($conversion['rate'], $previousConversion['rate']),
                ],
            ],
            'commission' => self::commission($user, $period),
            'stock' => InventoryValuationCalculator::calculate()->toArray(),
            'evolution' => self::evolution($user, $period),
            'categories' => self::categories($user, $period),
            'channels' => self::channels($user, $period),
            'goal' => $goal,
            'conversion' => [
                'current' => $conversion,
                'previous' => $previousConversion,
                'percentagePointChange' => self::percentagePointChange($conversion['rate'], $previousConversion['rate']),
            ],
            'pendingPayments' => $payments,
            'operationalAlerts' => OperationalAlertCalculator::calculate(
                $user,
                $conversion,
                $previousConversion,
                $payments,
                $goal['company']
            ),
            'nextShipments' => self::nextShipments($user),
        ];
    }

    /**
     * KPIs com comparação (CA-02) para as métricas ligadas a período;
     * `activeOrders`/`pendingAmount` são "estado atual" (mesmo padrão já
     * usado por `ActiveOrdersCalculator`/`PendingAmountCalculator` desde a
     * TASK-001) e não recebem comparação.
     */
    private static function kpis(User $user, DashboardPeriod $period): array
    {
        $revenue = RevenueCalculator::calculate($user, $period->from, $period->to);
        $previousRevenue = RevenueCalculator::calculate($user, $period->comparisonFrom, $period->comparisonTo);

        $salesProfit = SalesProfitCalculator::calculate($user, $period->from, $period->to, $revenue);
        $previousSalesProfit = SalesProfitCalculator::calculate($user, $period->comparisonFrom, $period->comparisonTo, $previousRevenue);

        $generalExpenses = GeneralExpenseCalculator::total($period->from, $period->to);
        $previousGeneralExpenses = GeneralExpenseCalculator::total($period->comparisonFrom, $period->comparisonTo);

        $netResult = NetResultCalculator::calculate($salesProfit, $generalExpenses);
        $previousNetResult = NetResultCalculator::calculate($previousSalesProfit, $previousGeneralExpenses);

        $watchesSold = WatchesSoldCalculator::calculate($user, $period->from, $period->to);
        $previousWatchesSold = WatchesSoldCalculator::calculate($user, $period->comparisonFrom, $period->comparisonTo);

        $ordersCount = self::paidOrdersCount($user, $period->from, $period->to);
        $previousOrdersCount = self::paidOrdersCount($user, $period->comparisonFrom, $period->comparisonTo);

        return [
            'revenue' => self::withComparison($revenue, $previousRevenue),
            'salesProfit' => self::withComparison($salesProfit, $previousSalesProfit),
            'netResult' => self::withComparison($netResult, $previousNetResult),
            'generalExpenses' => self::withComparison($generalExpenses, $previousGeneralExpenses),
            'watchesSold' => self::withComparison($watchesSold, $previousWatchesSold),
            'ordersCount' => self::withComparison($ordersCount, $previousOrdersCount),
            'activeOrders' => ['value' => ActiveOrdersCalculator::calculate($user)],
            'pendingAmount' => ['value' => PendingAmountCalculator::calculate($user)],
        ];
    }

    /**
     * RN-02 (docs/regras-de-negocio-dashboard.md §4.7): vendedor vê só a
     * própria comissão/projeção — `CommissionCalculator::report()` já
     * escopa isso (RN-02 da TASK-005), então basta chamar sem `sellerUserId`
     * explícito.
     */
    private static function commission(User $user, DashboardPeriod $period): array
    {
        $summary = CommissionCalculator::report($user, $period->from, $period->to)['summary'];

        return $summary;
    }

    /**
     * Evolução (docs/modules/dashboard.md "Evolução"): faturamento, lucro
     * (aproximado — ver nota abaixo), relógios vendidos e quantidade de
     * pedidos, agrupados por dia/semana/mês (CA-03).
     *
     * Bucketing feito em PHP (não em SQL) para não depender de funções de
     * data específicas de um SGBD — os testes rodam em SQLite
     * (`phpunit.xml`), produção em MySQL, e `DATE_FORMAT`/`WEEKDAY` não são
     * portáveis entre os dois. Mesmo padrão de agregação em PHP já usado por
     * `CommissionCalculator`/`GoalProgressCalculator`.
     *
     * `salesProfit` aqui é uma aproximação (receita - custo direto -
     * comissão do bucket, SEM o desconto de devolução por item que
     * `CommissionCalculator`/`RevenueCalculator` aplicam) — precisão exata
     * por bucket exigiria repetir a lógica de devolução em cada balde;
     * decisão deliberada de simplificar um dado de gráfico, não um total
     * oficial (o total oficial do período vem do KPI `salesProfit`, exato).
     */
    private static function evolution(User $user, DashboardPeriod $period): array
    {
        $orders = OrderFinancialScope::ordersQuery($user, $period->from, $period->to, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->with('items')
            ->get();

        $buckets = collect(self::bucketKeys($period))->mapWithKeys(fn (string $key) => [$key => [
            'bucket' => $key,
            'revenue' => 0.0,
            'salesProfit' => 0.0,
            'watchesSold' => 0,
            'ordersCount' => 0,
        ]]);

        foreach ($orders as $order) {
            /** @var Order $order */
            $key = self::bucketKeyFor(Carbon::parse($order->paid_at), $period->grouping);

            if (! $buckets->has($key)) {
                continue;
            }

            $revenue = (float) $order->sale_price - (float) $order->discount + (float) $order->freight;
            $directCost = (float) $order->cost + (float) $order->channel_fee + (float) $order->freight;
            $commission = $order->items->sum(fn ($item) => (float) $item->unit_commission * $item->quantity);
            $watchesSold = $order->items->where('product_type', self::WATCHES_CATEGORY)->sum('quantity');

            $bucket = $buckets[$key];
            $bucket['revenue'] += $revenue;
            $bucket['salesProfit'] += $revenue - $directCost - $commission;
            $bucket['watchesSold'] += $watchesSold;
            $bucket['ordersCount'] += 1;
            $buckets[$key] = $bucket;
        }

        return $buckets->values()->map(fn (array $b) => [
            'bucket' => $b['bucket'],
            'revenue' => round($b['revenue'], 2),
            'salesProfit' => round($b['salesProfit'], 2),
            'watchesSold' => $b['watchesSold'],
            'ordersCount' => $b['ordersCount'],
        ])->all();
    }

    /**
     * Rosca de categorias (RN, docs/regras-de-negocio-dashboard.md §7):
     * itens de pedidos pagos no período, líquidos de devolução com
     * reembolso efetuado — mesma granularidade por item já usada por
     * `CommissionCalculator`/`GoalProgressCalculator`.
     */
    private static function categories(User $user, DashboardPeriod $period): array
    {
        $orderIds = OrderFinancialScope::ordersQuery($user, $period->from, $period->to, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return [];
        }

        $returnedByOrderItemId = DB::table('return_items')
            ->join('returns', 'returns.id', '=', 'return_items.return_id')
            ->where('returns.status', 'Reembolso Efetuado')
            // TASK-025 (ADR-007): devolucao estornada nao reduz comissao,
            // meta nem dashboard.
            ->whereNull('returns.voided_at')
            ->whereNotNull('return_items.order_item_id')
            ->selectRaw('return_items.order_item_id as order_item_id, SUM(return_items.quantity) as returned_qty')
            ->groupBy('return_items.order_item_id')
            ->pluck('returned_qty', 'order_item_id');

        $items = OrderItem::query()->whereIn('order_id', $orderIds)->get();

        $byCategory = $items->groupBy('product_type')->map(function (Collection $group, string $category) use ($returnedByOrderItemId) {
            $revenue = 0.0;
            $units = 0;

            foreach ($group as $item) {
                $returnedQty = (int) ($returnedByOrderItemId[$item->id] ?? 0);
                $netQty = max($item->quantity - $returnedQty, 0);
                $revenue += ((float) $item->unit_price - (float) $item->unit_discount) * $netQty;
                $units += $netQty;
            }

            return ['category' => $category, 'revenue' => round($revenue, 2), 'units' => $units];
        })->values();

        return $byCategory->all();
    }

    /**
     * Rosca por canal — soma bruta (sem desconto por devolução, diferente de
     * categorias): a task não especifica essa regra pra canal, e o dado
     * aqui é auxiliar/gráfico, não um total oficial gateado por CA.
     */
    private static function channels(User $user, DashboardPeriod $period): array
    {
        $orders = OrderFinancialScope::ordersQuery($user, $period->from, $period->to, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->get(['channel', 'sale_price', 'discount', 'freight']);

        return $orders->groupBy('channel')->map(function (Collection $group, string $channel) {
            return [
                'channel' => $channel,
                'revenue' => round((float) $group->sum(fn (Order $o) => $o->sale_price - $o->discount + $o->freight), 2),
                'ordersCount' => $group->count(),
            ];
        })->values()->all();
    }

    /**
     * RN (docs/regras-de-negocio-dashboard.md §9): vendedor vê a meta
     * individual E a da empresa; administração não tem meta individual
     * própria a menos que também seja vendável (owner). Pega a meta ativa
     * mais recente de cada escopo — não filtra pelo período do dashboard, a
     * meta tem seu próprio ciclo (mesma independência do indicador de
     * estoque).
     */
    private static function goal(User $user): array
    {
        $company = Goal::query()
            ->where('scope', 'company')
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->with('intervals')
            ->first();

        $individual = Goal::query()
            ->where('scope', 'user')
            ->where('target_user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->with('intervals')
            ->first();

        return [
            'company' => $company ? self::goalSummary($company) : null,
            'individual' => $individual ? self::goalSummary($individual) : null,
        ];
    }

    private static function goalSummary(Goal $goal): array
    {
        $intervals = GoalProgressCalculator::calculate($goal);
        $totalTarget = (float) $intervals->sum('targetValue');
        $totalCurrent = (float) $intervals->sum('currentValue');

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'calculationType' => $goal->calculation_type,
            'productTypeFilter' => $goal->product_type_filter,
            'totalTarget' => round($totalTarget, 2),
            'totalCurrent' => round($totalCurrent, 2),
            'totalPercentage' => $totalTarget > 0 ? round(($totalCurrent / $totalTarget) * 100, 1) : 0.0,
        ];
    }

    /**
     * "Próximos envios" (docs/regras-de-negocio-dashboard.md §9): pedidos
     * pagos ainda não enviados, escopados por ownership (vendedor só vê os
     * próprios — mesmo `canAccessAllRecords()` de sempre). Não filtra pelo
     * período do dashboard — é sempre "o que vem a seguir", não histórico.
     *
     * TASK-016 (CA-03): "dashboard pode consumir próximos envios" — usa o
     * mesmo `ShippingScheduleCalculator` da fila de envios
     * (`ShippingController::queue()`) para não duplicar a regra de cálculo
     * de data de postagem/atraso.
     */
    private static function nextShipments(User $user): array
    {
        $query = Order::query()
            ->with('customer')
            ->whereIn('status', ['Pago', 'Separação/Fornecedor', 'Pronto para Envio'])
            ->orderBy('sale_date');

        if (! $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $user->id);
        }

        $today = Carbon::today();

        return $query->limit(5)->get()->map(function (Order $order) use ($today) {
            $nextPostingDate = ShippingScheduleCalculator::nextPostingDate($order->paid_at);

            return [
                'orderId' => $order->id,
                'customerName' => $order->customer?->name,
                'status' => $order->status,
                'shippingMethod' => $order->shipping_method,
                'saleDate' => $order->sale_date,
                'nextPostingDate' => $nextPostingDate?->toDateString(),
                'isLate' => ShippingScheduleCalculator::isLate($nextPostingDate, $today),
            ];
        })->all();
    }

    private static function paidOrdersCount(User $user, ?string $startDate, ?string $endDate): int
    {
        return OrderFinancialScope::ordersQuery($user, $startDate, $endDate, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->count();
    }

    private static function withComparison(float|int $value, float|int $previousValue): array
    {
        return [
            'value' => $value,
            'previousValue' => $previousValue,
            // null = comparação indefinida (período anterior zerado e atual
            // não-zero) — não confundir com "0% de variação" (TASK-013:
            // mesmo cuidado null-vs-zero já aplicado a `calcProfit`/`calcMargin`).
            'percentageChange' => self::percentageChange($value, $previousValue),
        ];
    }

    private static function percentageChange(float|int $value, float|int $previousValue): ?float
    {
        if ($previousValue == 0) {
            return $value == 0 ? 0.0 : null;
        }

        return round((($value - $previousValue) / abs($previousValue)) * 100, 1);
    }

    private static function percentagePointChange(float|int|null $value, float|int|null $previousValue): ?float
    {
        if ($value === null || $previousValue === null) {
            return null;
        }

        return round((float) $value - (float) $previousValue, 1);
    }

    /**
     * @return list<string> chaves de bucket em ordem cronológica, cobrindo
     *                      todo o período mesmo em dias/semanas/meses sem
     *                      movimento (bom pra gráfico contínuo).
     */
    private static function bucketKeys(DashboardPeriod $period): array
    {
        $cursor = Carbon::parse($period->from);
        $end = Carbon::parse($period->to);
        $keys = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = self::bucketKeyFor($cursor, $period->grouping);
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
            }

            $cursor = match ($period->grouping) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                default => $cursor->addMonth(),
            };
        }

        return $keys;
    }

    private static function bucketKeyFor(Carbon $date, string $grouping): string
    {
        return match ($grouping) {
            'day' => $date->toDateString(),
            'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            default => $date->copy()->startOfMonth()->toDateString(),
        };
    }
}
