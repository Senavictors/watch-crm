<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-027 / ADR-008 (RN-04) — rastro da aprovação de reembolso.
 *
 * `returns.refund_amount` já existia, mas ninguém registrava QUEM aprovou,
 * QUANDO e POR QUÊ — e a transição para "Reembolso Efetuado" saía de graça
 * com `returns.update`.
 *
 * Devoluções já efetuadas antes desta migration ficam com as colunas nulas
 * de propósito: não há como inferir retroativamente o aprovador, e inventar
 * um seria pior do que declarar a ausência. O histórico de quem mudou o
 * status continua em `return_status_history` e em `audit_logs`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->timestamp('refund_approved_at')->nullable();
            $table->foreignId('refund_approved_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('refund_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_approved_by_user_id');
            $table->dropColumn(['refund_approved_at', 'refund_reason']);
        });
    }
};
