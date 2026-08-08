<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-017 (RN-03/CA-01) — formaliza o vocabulário de status de
 * `returns.status` com uma máquina de estados (`App\Support\
 * ReturnStatusTransition`) e um histórico imutável de transições
 * (`return_status_history`).
 *
 * Renomeações de dados retroativas (strings antigas → novas, ver
 * `App\Support\ReturnMetadata::STATUSES`):
 * - "Enviado p/ Reparo"  → "Enviado ao Relojoeiro"
 * - "Reparado"           → "Retornado"
 * - "Pronto p/ Reenvio"  → "Pronto para Reenvio"
 * - "Enviado"            → "Reenviado"
 * - "Entregue"           → "Concluído" (fusão: "Entregue" deixa de existir
 *   como etapa própria)
 *
 * `'Reembolso Pendente'` e `'Reembolso Efetuado'` NÃO são tocados — são
 * strings financeiramente críticas, lidas literalmente por
 * `CommissionCalculator`, `RevenueCalculator`, `DashboardSummaryCalculator`
 * e `GoalProgressCalculator`.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const RENAMES = [
        'Enviado p/ Reparo' => 'Enviado ao Relojoeiro',
        'Reparado' => 'Retornado',
        'Pronto p/ Reenvio' => 'Pronto para Reenvio',
        'Enviado' => 'Reenviado',
        'Entregue' => 'Concluído',
    ];

    public function up(): void
    {
        Schema::create('return_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        foreach (self::RENAMES as $old => $new) {
            DB::table('returns')->where('status', $old)->update(['status' => $new]);
        }

        // Backfill: uma linha por devolução já existente, com from_status=null
        // (marca "estado inicial conhecido", não uma transição real — ver
        // `ReturnStatusTransition`, que só considera transição quando
        // from!==to) e actor_user_id = quem criou a devolução, já que não
        // existe outro autor plausível para o estado corrente pré-migração.
        $existingReturns = DB::table('returns')
            ->select(['id', 'status', 'created_by_user_id', 'created_at'])
            ->get();

        $backfill = $existingReturns
            ->map(fn ($r) => [
                'return_id' => $r->id,
                'from_status' => null,
                'to_status' => $r->status,
                'actor_user_id' => $r->created_by_user_id,
                'notes' => null,
                'created_at' => $r->created_at,
            ])
            ->all();

        if ($backfill !== []) {
            DB::table('return_status_history')->insert($backfill);
        }
    }

    /**
     * Reverte as 4 renomeações limpas 1:1. A fusão "Entregue" → "Concluído"
     * NÃO tem volta exata: depois do `up()`, devoluções que eram "Entregue"
     * e devoluções que já eram "Concluído" ficam indistinguíveis na coluna
     * `status`, então o `down()` deixa todas como "Concluído" — perda de
     * granularidade aceita conscientemente (risco já assumido para rollback
     * de migration, não para uso normal do sistema).
     */
    public function down(): void
    {
        Schema::dropIfExists('return_status_history');

        foreach (self::RENAMES as $old => $new) {
            if ($old === 'Entregue') {
                continue;
            }

            DB::table('returns')->where('status', $new)->update(['status' => $old]);
        }
    }
};
