<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-019 (RN-03) — marcação de atrito de cliente: histórico imutável (sem
 * `updated_at`), mesmo padrão de `return_status_history` (TASK-017). `note`
 * é `NOT NULL` porque RN-03 exige que toda marcação venha acompanhada de uma
 * observação — nunca é uma flag booleana solta.
 *
 * Não há dados legados a migrar: esta tabela não existia antes, então não
 * há backfill (diferente de `return_status_history`, que precisou
 * reconstruir o histórico dos registros já existentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_friction_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->text('note');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_friction_notes');
    }
};
