<?php

namespace App\Support;

class WaitlistMetadata
{
    public const STATUSES = ['Pendente', 'Avisado', 'Convertido', 'Encerrado'];

    /**
     * TASK-018 — a task explicitamente pede para não formalizar uma máquina
     * de estados completa como `ReturnStatusTransition` (fora de escopo: CRM
     * de oportunidades/funil). Regra leve mantida: `Convertido` e
     * `Encerrado` são terminais — uma entrada que já chegou lá não volta a
     * `Pendente`/`Avisado`. `WaitlistController::update()` bloqueia essa
     * reabertura com 422 (decisão deliberada, não um bug: evita que uma
     * conversão/encerramento seja desfeito por engano, sem o custo de um
     * grafo de transições completo).
     */
    public const TERMINAL_STATUSES = ['Convertido', 'Encerrado'];
}
