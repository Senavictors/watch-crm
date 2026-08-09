<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DashboardPeriod;
use App\Support\DashboardPeriodResolver;
use App\Support\DashboardSummaryCalculator;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * TASK-009 — `GET /dashboard/summary` (docs/api/dashboard.md).
 *
 * CA-04 (payload não vaza campos restritos): todo o cálculo é feito por
 * `DashboardSummaryCalculator` sem saber de permissão; este controller
 * decide o que entra na resposta por papel — mesmo padrão de
 * `OrderController::toPayload()`/`ProductController::toPayload()`
 * (TASK-013).
 */
class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        try {
            $period = DashboardPeriodResolver::resolve($request->input('from'), $request->input('to'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $user = $request->user();
        $data = DashboardSummaryCalculator::build($user, $period);

        return response()->json($this->toPayload($user, $period, $data));
    }

    private function toPayload(User $user, DashboardPeriod $period, array $data): array
    {
        $canViewFinancials = $user->canViewFinancialReports();
        // RN (docs/regras-de-negocio-dashboard.md §9): vendedor vê o
        // faturamento PRÓPRIO (já escopado por ownership no calculador) —
        // não é o mesmo que ver o relatório financeiro da empresa. Gerente
        // (canAccessAllRecords sem dashboard.financial.view) não entra
        // nesta exceção: veria faturamento da empresa toda, que é
        // justamente o dado restrito pela RN-02 do ADR-003.
        $canViewRevenue = $canViewFinancials || ! $user->canAccessAllRecords();
        $canViewCommission = $user->hasPermission('commissions.view');

        $kpis = [
            'watchesSold' => $data['kpis']['watchesSold'],
            'ordersCount' => $data['kpis']['ordersCount'],
            'conversionRate' => $data['kpis']['conversionRate'],
            'activeOrders' => $data['kpis']['activeOrders'],
            'pendingAmount' => $data['kpis']['pendingAmount'],
        ];

        if ($canViewRevenue) {
            $kpis['revenue'] = $data['kpis']['revenue'];
        }

        if ($canViewFinancials) {
            $kpis['salesProfit'] = $data['kpis']['salesProfit'];
            $kpis['netResult'] = $data['kpis']['netResult'];
            $kpis['generalExpenses'] = $data['kpis']['generalExpenses'];
        }

        $payload = [
            'period' => $period->toArray(),
            'comparison' => $period->comparisonToArray(),
            'kpis' => $kpis,
            'evolution' => array_map(function (array $bucket) use ($canViewFinancials, $canViewRevenue) {
                if (! $canViewFinancials) {
                    unset($bucket['salesProfit']);
                }

                if (! $canViewRevenue) {
                    unset($bucket['revenue']);
                }

                return $bucket;
            }, $data['evolution']),
            'goal' => $data['goal'],
            'conversion' => $data['conversion'],
            'pendingPayments' => $data['pendingPayments'],
            'operationalAlerts' => $data['operationalAlerts'],
            'nextShipments' => $data['nextShipments'],
        ];

        if ($canViewRevenue) {
            $payload['categories'] = $data['categories'];
            $payload['channels'] = $data['channels'];
        }

        if ($canViewCommission) {
            $payload['commission'] = $data['commission'];
        }

        if ($canViewFinancials) {
            $payload['stock'] = $data['stock'];
        }

        return $payload;
    }
}
