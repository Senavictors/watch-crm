<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Escopo compartilhado pelos calculadores financeiros (RevenueCalculator,
 * SalesProfitCalculator, PendingAmountCalculator, ActiveOrdersCalculator):
 * aplica o mesmo critério de ownership por vendedor já usado em
 * OrderController::index() e o filtro de período por `sale_date`.
 *
 * Não substitui autorização (Policy/middleware) — apenas evita duplicar o
 * filtro de "quais pedidos este usuário pode agregar" em cada calculadora.
 */
class OrderFinancialScope
{
    public static function apply(Builder $query, User $user, ?string $startDate = null, ?string $endDate = null): Builder
    {
        if (! $user->canAccessAllRecords()) {
            $query->where('seller_user_id', $user->id);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('sale_date', [$startDate, $endDate]);
        }

        return $query;
    }

    /**
     * Fábrica de query nova (evita reaproveitar uma instância de Builder já
     * mutada por selectRaw/orderBy entre chamadas sucessivas de um mesmo
     * calculador).
     */
    public static function ordersQuery(User $user, ?string $startDate = null, ?string $endDate = null): Builder
    {
        return self::apply(Order::query(), $user, $startDate, $endDate);
    }
}
