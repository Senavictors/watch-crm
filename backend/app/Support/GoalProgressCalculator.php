<?php

namespace App\Support;

use App\Models\Goal;
use App\Models\GoalInterval;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GoalProgressCalculator
{
    public static function calculate(Goal $goal): Collection
    {
        $goal->loadMissing(['intervals', 'brand', 'watchModel']);

        return $goal->intervals->map(function (GoalInterval $interval) use ($goal) {
            $query = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                // TASK-009 (RN-01): o intervalo da meta usa `paid_at` (data de
                // competência), não `sale_date` — mesma correção aplicada a
                // `OrderFinancialScope`. Um pedido só cai no intervalo em que
                // foi de fato confirmado como pago. `whereDate` (não
                // `whereBetween` cru) porque `paid_at` tem hora — evita
                // excluir confirmações depois da meia-noite do último dia.
                ->whereDate('orders.paid_at', '>=', $interval->start_date->format('Y-m-d'))
                ->whereDate('orders.paid_at', '<=', $interval->end_date->format('Y-m-d'))
                // RN-01/CA-02 (TASK-003, docs/domain/financial-rules.md): só
                // pedidos pagos entram na meta. "Pago" = `paid_at` preenchido e
                // status diferente de `Cancelado` (mesmo critério dos
                // calculadores financeiros).
                ->whereNotNull('orders.paid_at')
                ->where('orders.status', '!=', 'Cancelado');

            if ($goal->scope === 'user' && $goal->target_user_id) {
                $query->where('orders.seller_user_id', $goal->target_user_id);
            }

            if ($goal->product_type_filter) {
                $query->where('order_items.product_type', $goal->product_type_filter);
            }

            if ($goal->brand_id && $goal->brand) {
                $query->where('order_items.brand_name', $goal->brand->name);
            }

            if ($goal->model_id && $goal->watchModel) {
                $query->where('order_items.model_name', $goal->watchModel->name);
            }

            // TASK-008 (RN-02, docs/regras-de-negocio-dashboard.md #10):
            // reembolso efetivado reduz proporcionalmente o progresso da
            // meta, só pelos itens/quantidades efetivamente devolvidos —
            // mesmo gatilho (`returns.status = 'Reembolso Efetuado'`) e
            // mesma granularidade por item (`return_items.quantity`) já
            // usados por `CommissionCalculator` (TASK-005) para comissão.
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

            $items = $query->get(['order_items.id', 'order_items.unit_price', 'order_items.unit_discount', 'order_items.quantity']);

            $currentValue = $items->sum(function (OrderItem $item) use ($goal, $returnedByOrderItemId) {
                $returnedQty = (int) ($returnedByOrderItemId[$item->id] ?? 0);
                $netQty = max($item->quantity - $returnedQty, 0);

                if ($goal->calculation_type === 'total_value') {
                    return ((float) $item->unit_price - (float) $item->unit_discount) * $netQty;
                }

                return $netQty;
            });

            return [
                'id' => $interval->id,
                'startDate' => $interval->start_date->format('Y-m-d'),
                'endDate' => $interval->end_date->format('Y-m-d'),
                'targetValue' => (float) $interval->target_value,
                'currentValue' => (float) $currentValue,
                'percentage' => $interval->target_value > 0
                    ? round(($currentValue / (float) $interval->target_value) * 100, 1)
                    : 0,
            ];
        });
    }
}
