<?php

namespace App\Support;

/**
 * TASK-017 (RN-03/CA-01) — máquina de estados do fluxo de garantia/troca/
 * devolução (mesmo espírito de `OrderPaymentTransition`, mas aqui a decisão
 * em si — pode ou não ir de X para Y — é o produto principal, não um efeito
 * colateral em outro campo). `ReturnController::update()` chama `isValid()`
 * antes de persistir uma mudança de status; `metadata()` expõe
 * `validNextStatuses()` para cada status via `transitions`, para o frontend
 * só oferecer opções válidas.
 *
 * `$from === $to` é sempre uma transição válida (no-op — permite salvar
 * outros campos do formulário sem tocar no status) mas não é contada como
 * transição para fins de `return_status_history` (só grava quando o status
 * de fato muda — ver `ReturnController::update()`).
 *
 * Grafo (destinos válidos por origem):
 * - "Aguardando Recebimento" → "Recebido", "Cancelado"
 * - "Recebido" → "Em Análise", "Cancelado"
 * - "Em Análise" → "Enviado ao Relojoeiro", "Em Troca", "Reembolso
 *   Pendente", "Recusado", "Cancelado"
 * - "Enviado ao Relojoeiro" → "Em Manutenção", "Cancelado"
 * - "Em Manutenção" → "Retornado", "Recusado", "Cancelado"
 * - "Retornado" → "Pronto para Reenvio", "Recusado", "Cancelado"
 * - "Em Troca" → "Troca Aprovada", "Recusado", "Cancelado"
 * - "Troca Aprovada" → "Pronto para Reenvio", "Cancelado"
 * - "Reembolso Pendente" → "Reembolso Efetuado", "Recusado", "Cancelado"
 * - "Reembolso Efetuado" → "Concluído" (terminal financeiro: uma vez
 *   efetuado, o reembolso não recua — condiz com `CommissionCalculator`/
 *   `RevenueCalculator`, que já tratam esse status como irreversível)
 * - "Pronto para Reenvio" → "Reenviado", "Cancelado"
 * - "Reenviado" → "Concluído", "Cancelado"
 * - "Concluído", "Recusado", "Cancelado" → (terminais, nenhuma saída)
 */
class ReturnStatusTransition
{
    /**
     * @var array<string, list<string>>
     */
    private const GRAPH = [
        'Aguardando Recebimento' => ['Recebido', 'Cancelado'],
        'Recebido' => ['Em Análise', 'Cancelado'],
        'Em Análise' => ['Enviado ao Relojoeiro', 'Em Troca', 'Reembolso Pendente', 'Recusado', 'Cancelado'],
        'Enviado ao Relojoeiro' => ['Em Manutenção', 'Cancelado'],
        'Em Manutenção' => ['Retornado', 'Recusado', 'Cancelado'],
        'Retornado' => ['Pronto para Reenvio', 'Recusado', 'Cancelado'],
        'Em Troca' => ['Troca Aprovada', 'Recusado', 'Cancelado'],
        'Troca Aprovada' => ['Pronto para Reenvio', 'Cancelado'],
        'Reembolso Pendente' => ['Reembolso Efetuado', 'Recusado', 'Cancelado'],
        'Reembolso Efetuado' => ['Concluído'],
        'Pronto para Reenvio' => ['Reenviado', 'Cancelado'],
        'Reenviado' => ['Concluído', 'Cancelado'],
        'Concluído' => [],
        'Recusado' => [],
        'Cancelado' => [],
    ];

    public static function isValid(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::GRAPH[$from] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public static function validNextStatuses(string $from): array
    {
        return self::GRAPH[$from] ?? [];
    }
}
