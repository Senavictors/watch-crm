<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * TASK-017 (RN-01) — janela de garantia de 18 meses contados da data de
 * venda (`orders.sale_date`), isolada aqui (não inline no controller) pelo
 * mesmo padrão de `ShippingScheduleCalculator`/`OrderPaymentTransition`.
 *
 * `null` é um terceiro estado válido de retorno (não "false"): significa
 * que não há como calcular — devolução sem `order_id` vinculado, ou pedido
 * vinculado sem `sale_date`. `ReturnController::toPayload()` decide se o
 * caso é "sem pedido" ou "sem data" antes de chamar esta função; aqui só
 * importa se recebeu uma data ou não.
 */
class ReturnWarrantyWindow
{
    public const WARRANTY_MONTHS = 18;

    public static function isWithinWindow(?Carbon $saleDate): ?bool
    {
        if ($saleDate === null) {
            return null;
        }

        $expiresAt = $saleDate->copy()->startOfDay()->addMonths(self::WARRANTY_MONTHS);

        return now()->startOfDay()->lessThanOrEqualTo($expiresAt);
    }
}
