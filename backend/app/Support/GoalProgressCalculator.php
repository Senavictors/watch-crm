<?php

namespace App\Support;

use App\Models\Goal;
use App\Models\GoalInterval;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class GoalProgressCalculator
{
    public static function calculate(Goal $goal): Collection
    {
        $goal->loadMissing(['intervals', 'brand', 'watchModel']);

        return $goal->intervals->map(function (GoalInterval $interval) use ($goal) {
            $query = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->whereBetween('orders.sale_date', [
                    $interval->start_date->format('Y-m-d'),
                    $interval->end_date->format('Y-m-d'),
                ])
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

            if ($goal->calculation_type === 'total_value') {
                $currentValue = (float) $query->selectRaw(
                    'COALESCE(SUM(order_items.unit_price * order_items.quantity - order_items.unit_discount * order_items.quantity), 0) as total'
                )->value('total');
            } else {
                $currentValue = (float) $query->sum('order_items.quantity');
            }

            return [
                'id' => $interval->id,
                'startDate' => $interval->start_date->format('Y-m-d'),
                'endDate' => $interval->end_date->format('Y-m-d'),
                'targetValue' => (float) $interval->target_value,
                'currentValue' => $currentValue,
                'percentage' => $interval->target_value > 0
                    ? round(($currentValue / (float) $interval->target_value) * 100, 1)
                    : 0,
            ];
        });
    }
}
