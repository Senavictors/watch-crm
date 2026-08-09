<?php

namespace App\Support;

use App\Models\Order;
use App\Models\PostingDay;
use Illuminate\Support\Carbon;

/**
 * TASK-016 — cálculo determinístico da agenda de postagem, hoje ausente do
 * backend (antes desta task o dia de postagem era hardcoded no frontend,
 * `ShippingQueue.tsx`/`helpers.ts::nextShippingDay()`). Convenção de
 * `weekday`: `0=domingo...6=sábado`, igual a `Carbon::dayOfWeek` e a
 * `Date.getDay()` do JS — sem necessidade de mapear entre backend e
 * frontend.
 */
class ShippingScheduleCalculator
{
    /**
     * @return list<int> weekdays habilitados, ordenados.
     */
    public static function enabledWeekdays(): array
    {
        return PostingDay::query()
            ->where('enabled', true)
            ->orderBy('weekday')
            ->pluck('weekday')
            ->map(fn ($weekday) => (int) $weekday)
            ->all();
    }

    /**
     * Próxima data (a partir de `$from`, inclusive) cujo `weekday` esteja
     * habilitado. `$from` é incluído se já cair num dia habilitado (RN da
     * task: pedido pago numa quinta com quinta habilitada -> a data esperada
     * É aquela quinta, não a próxima). `null` se `$from` for `null` ou se
     * nenhum dia estiver habilitado (RN-02: só dias configurados geram
     * postagem). Determinístico (CA-02): não depende de `now()`.
     */
    public static function nextPostingDate(?Carbon $from): ?Carbon
    {
        return self::nextPostingDateForWeekdays($from, self::enabledWeekdays());
    }

    /**
     * Variante para calculos em lote. O chamador carrega os dias habilitados
     * uma unica vez e evita uma consulta por pedido.
     *
     * @param  list<int>  $enabled
     */
    public static function nextPostingDateForWeekdays(?Carbon $from, array $enabled): ?Carbon
    {
        if ($from === null) {
            return null;
        }

        if ($enabled === []) {
            return null;
        }

        $cursor = $from->copy()->startOfDay();

        for ($i = 0; $i < 7; $i++) {
            if (in_array($cursor->dayOfWeek, $enabled, true)) {
                return $cursor;
            }

            $cursor = $cursor->copy()->addDay();
        }

        // Inalcançável: com `$enabled` não-vazio, algum dia dentro de uma
        // semana corrida sempre bate um weekday habilitado.
        return null;
    }

    /**
     * `true` se `$nextPostingDate` não for `null` e `$today` (início do dia)
     * for estritamente posterior a ela.
     */
    public static function isLate(?Carbon $nextPostingDate, Carbon $today): bool
    {
        if ($nextPostingDate === null) {
            return false;
        }

        return $today->copy()->startOfDay()->greaterThan($nextPostingDate->copy()->startOfDay());
    }

    /**
     * RN-03: um pedido só entra na fila de envio se foi pago, ainda não foi
     * enviado, e não está Cancelado nem Entregue — cancelado/entregue nunca
     * aparece, mesmo com `paid_at` preenchido.
     */
    public static function isEligibleForQueue(Order $order): bool
    {
        return $order->paid_at !== null
            && $order->shipped_date === null
            && ! in_array($order->status, ['Cancelado', 'Entregue'], true);
    }
}
