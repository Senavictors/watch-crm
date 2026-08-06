<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-003 — confirmação de pagamento por transição de status
 * (docs/domain/financial-rules.md, docs/data/dashboard-foundation.md).
 *
 * `paid_at`/`paid_by_user_id` guardam a confirmação CORRENTE: são apagados ao
 * voltar para um status de pagamento pendente e recebem novo valor na
 * confirmação seguinte (RN-02) — histórico de transições fica na auditoria
 * (audit_logs), não nestas colunas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->foreignId('paid_by_user_id')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();

            $table->index(['paid_at', 'status']);
            $table->index(['seller_user_id', 'paid_at']);
        });

        $this->backfillLegacyOrders();
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['seller_user_id', 'paid_at']);
            $table->dropIndex(['paid_at', 'status']);
            $table->dropConstrainedForeignId('paid_by_user_id');
            $table->dropColumn('paid_at');
        });
    }

    /**
     * Pedidos legados já em status "pago" não têm confirmação registrada.
     * Decisão explícita (TASK-003, projeto ainda em desenvolvimento, sem dados
     * de produção): usar `sale_date` como aproximação de `paid_at` para não
     * fazer faturamento/metas históricos desaparecerem ao trocar o critério de
     * status para `paid_at`. `paid_by_user_id` fica nulo — o ator real dessas
     * confirmações não foi registrado historicamente.
     */
    private function backfillLegacyOrders(): void
    {
        $paidStatuses = ['Pago', 'Separação/Fornecedor', 'Pronto para Envio', 'Enviado', 'Entregue'];

        DB::table('orders')
            ->whereIn('status', $paidStatuses)
            ->whereNull('paid_at')
            ->orderBy('id')
            ->select(['id', 'sale_date'])
            ->get()
            ->each(function ($order) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['paid_at' => $order->sale_date]);
            });
    }
};
