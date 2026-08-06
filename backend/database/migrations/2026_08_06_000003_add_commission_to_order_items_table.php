<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-005 — comissões por produto e venda.
 *
 * `unit_commission` é o SNAPSHOT da comissão vigente do produto no momento
 * da venda (mesmo padrão de `unit_cost`/`unit_price`, RN-01/CA-01: alterar o
 * produto depois não muda pedidos antigos). Não há dado legado a migrar —
 * a comissão não existia antes desta task, então todo `order_item` existente
 * simplesmente recebe o default `0` (nenhuma comissão retroativa).
 *
 * `commission_paid_at`/`commission_paid_by_user_id` registram a confirmação
 * de pagamento da comissão DESTE item — mesmo padrão de
 * `orders.paid_at`/`orders.paid_by_user_id` (TASK-003), mas no nível do item
 * porque a comissão é apurada e paga por linha vendida, não pelo pedido
 * inteiro (CA-03: pagamento auditável por item).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_commission', 10, 2)->default(0)->after('unit_cost');
            $table->timestamp('commission_paid_at')->nullable()->after('unit_discount');
            $table->foreignId('commission_paid_by_user_id')->nullable()->after('commission_paid_at')->constrained('users')->nullOnDelete();

            $table->index(['commission_paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['commission_paid_at']);
            $table->dropConstrainedForeignId('commission_paid_by_user_id');
            $table->dropColumn(['commission_paid_at', 'unit_commission']);
        });
    }
};
