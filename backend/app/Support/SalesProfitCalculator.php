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
 * (efeito líquido zero até existir um campo próprio). Embalagem e outros
 * custos do pedido ainda não têm coluna (TASK-006) e entram como 0 nesta
 * versão — pendência registrada na task. Comissão (TASK-005) já vem de
 * `CommissionCalculator`, que aplica o mesmo critério de "pago"/cancelado
 * abaixo e desconta devoluções com reembolso efetuado.
 *
 * "Pago" segue o mesmo critério de `RevenueCalculator` (TASK-003): `paid_at`
 * preenchido e status diferente de `Cancelado`. O período também filtra por
 * `paid_at` (TASK-009, RN-01) — ver `OrderFinancialScope`.
 */
class SalesProfitCalculator
{
    public static function calculate(User $user, ?string $startDate = null, ?string $endDate = null, ?float $revenue = null): float
    {
        $revenue ??= RevenueCalculator::calculate($user, $startDate, $endDate);

        $directCosts = (float) OrderFinancialScope::ordersQuery($user, $startDate, $endDate, 'paid_at')
            ->whereNotNull('paid_at')
            ->where('status', '!=', 'Cancelado')
            ->selectRaw('COALESCE(SUM(cost + channel_fee + freight), 0) as total')
            ->value('total');

        $commission = CommissionCalculator::accrued($user, $startDate, $endDate);

        return $revenue - $directCosts - $commission;
    }
}
