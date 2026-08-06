<?php

namespace App\Support;

use App\Models\User;

/**
 * Lucro das vendas (docs/domain/financial-rules.md):
 *
 *   Lucro das vendas = faturamento - custo dos itens
 *                       - taxas de plataforma/canal/cartão - frete pago pela empresa
 *                       - embalagem - comissão - outros custos do pedido
 *
 * O desconto não é subtraído de novo (já reduziu o faturamento).
 *
 * Estado atual do schema (ver TASK-001): `orders` só tem `cost`, `channel_fee`
 * e um único campo `freight` — não distingue "frete cobrado" de "frete pago
 * pela empresa", então o mesmo valor é somado no faturamento e subtraído aqui
 * (efeito líquido zero até existir um campo próprio). Embalagem, comissão e
 * outros custos do pedido ainda não têm coluna (TASK-005/TASK-006) e entram
 * como 0 nesta versão — pendência registrada na task.
 */
class SalesProfitCalculator
{
    public static function calculate(User $user, ?string $startDate = null, ?string $endDate = null, ?float $revenue = null): float
    {
        $revenue ??= RevenueCalculator::calculate($user, $startDate, $endDate);

        $directCosts = (float) OrderFinancialScope::ordersQuery($user, $startDate, $endDate)
            ->whereIn('status', OrderMetadata::PAID_STATUSES)
            ->selectRaw('COALESCE(SUM(cost + channel_fee + freight), 0) as total')
            ->value('total');

        return $revenue - $directCosts;
    }
}
