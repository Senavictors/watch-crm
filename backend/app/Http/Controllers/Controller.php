<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * TASK-025 / ADR-007 — recusa a exclusão de um registro que outros
     * dependem, em vez de deixar a cascata do banco apagar tudo em silêncio.
     * Devolve `null` quando não há dependente e a exclusão pode seguir.
     *
     * A mensagem diz o que impede ("2 modelos e 5 produtos"), porque
     * "não é possível excluir" sem motivo vira chamado de suporte.
     *
     * @param  array<string, int>  $dependents  rótulo no plural => contagem
     */
    protected function conflictIfInUse(
        array $dependents,
        string $subject,
        string $code,
        ?string $alternative = null,
    ): ?\Illuminate\Http\JsonResponse {
        $inUse = array_filter($dependents);

        if ($inUse === []) {
            return null;
        }

        $detail = collect($inUse)
            ->map(fn (int $count, string $label) => "{$count} {$label}")
            ->join(', ', ' e ');

        return response()->json([
            'message' => trim("{$subject} não pode ser excluído(a) porque {$detail} depende(m) dele(a). ".($alternative ?? '')),
            'code' => $code,
            'dependents' => $inUse,
        ], 409);
    }

    protected function audit(
        string $action,
        ?string $description = null,
        mixed $auditable = null,
        array $metadata = []
    ): void {
        AuditLogger::log($action, $description, $auditable, $metadata);
    }
}
