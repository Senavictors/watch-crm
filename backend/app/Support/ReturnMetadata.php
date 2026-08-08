<?php

namespace App\Support;

class ReturnMetadata
{
    public const TYPES = ['garantia', 'troca', 'devolucao'];

    public const TYPE_LABELS = [
        'garantia' => 'Garantia',
        'troca' => 'Troca',
        'devolucao' => 'Devolução',
    ];

    /**
     * TASK-017 (RN-03/CA-01) — vocabulário formalizado com máquina de estados
     * em `ReturnStatusTransition`. Renomeações em relação à versão anterior
     * (ver migration `2026_08_09_000001_create_return_status_history_table`
     * para a migração de dados retroativa):
     * - "Enviado p/ Reparo" → "Enviado ao Relojoeiro"
     * - "Reparado" → "Retornado"
     * - "Pronto p/ Reenvio" → "Pronto para Reenvio"
     * - "Enviado" → "Reenviado" (evita ambiguidade com "Enviado ao
     *   Relojoeiro", que é uma etapa diferente)
     * - "Entregue" foi removido, fundido em "Concluído"
     * Novos: "Em Manutenção", "Recusado" (RN-02: garantia pode ser recusada
     * por mau uso).
     *
     * `'Reembolso Pendente'` e `'Reembolso Efetuado'` são strings
     * financeiramente críticas (ver `CommissionCalculator`,
     * `RevenueCalculator`, `DashboardSummaryCalculator`,
     * `GoalProgressCalculator`) — NUNCA renomear.
     */
    public const STATUSES = [
        'Aguardando Recebimento',
        'Recebido',
        'Em Análise',
        'Enviado ao Relojoeiro',
        'Em Manutenção',
        'Retornado',
        'Em Troca',
        'Troca Aprovada',
        'Reembolso Pendente',
        'Reembolso Efetuado',
        'Pronto para Reenvio',
        'Reenviado',
        'Concluído',
        'Recusado',
        'Cancelado',
    ];
}
