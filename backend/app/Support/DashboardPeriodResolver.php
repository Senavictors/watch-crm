<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * TASK-009 — resolve o período do dashboard a partir de `from`/`to`
 * (docs/api/dashboard.md) e deriva:
 *
 * - período de comparação (CA-02): o período imediatamente anterior, de
 *   mesma duração em dias (docs/modules/dashboard.md "comparação anterior").
 * - agrupamento do gráfico de evolução (CA-03,
 *   docs/regras-de-negocio-dashboard.md §2): até 31 dias → dia; até 6 meses
 *   (≈186 dias) → semana; acima disso → mês.
 *
 * Sem `from`/`to`: mês atual (RN do comportamento esperado, "Padrão: mês
 * atual" em docs/modules/dashboard.md).
 */
class DashboardPeriodResolver
{
    private const DAY_GROUPING_LIMIT = 31;

    private const WEEK_GROUPING_LIMIT_DAYS = 186; // ~6 meses

    public static function resolve(?string $from, ?string $to): DashboardPeriod
    {
        $start = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $to ? Carbon::parse($to)->startOfDay() : Carbon::now()->endOfMonth()->startOfDay();

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('A data inicial não pode ser depois da data final.');
        }

        $days = $start->diffInDays($end) + 1;

        $comparisonEnd = $start->copy()->subDay();
        $comparisonStart = $comparisonEnd->copy()->subDays($days - 1);

        $grouping = match (true) {
            $days <= self::DAY_GROUPING_LIMIT => 'day',
            $days <= self::WEEK_GROUPING_LIMIT_DAYS => 'week',
            default => 'month',
        };

        return new DashboardPeriod(
            $start->toDateString(),
            $end->toDateString(),
            $comparisonStart->toDateString(),
            $comparisonEnd->toDateString(),
            $grouping,
        );
    }
}
