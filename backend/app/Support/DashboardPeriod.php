<?php

namespace App\Support;

/**
 * Value object de período do dashboard (TASK-009).
 */
class DashboardPeriod
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $comparisonFrom,
        public readonly string $comparisonTo,
        public readonly string $grouping,
    ) {}

    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'grouping' => $this->grouping,
        ];
    }

    public function comparisonToArray(): array
    {
        return [
            'from' => $this->comparisonFrom,
            'to' => $this->comparisonTo,
        ];
    }
}
