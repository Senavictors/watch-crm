<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-024 / ADR-006 — reserva e movimentação transacional de estoque.
 *
 * `products.reserved_qty` é a parcela de `qty` prometida a pedidos ainda não
 * pagos. Disponível para venda = `qty - reserved_qty`.
 *
 * Nenhum dado histórico é convertido aqui de propósito (ADR-006, item 7): o
 * `qty` atual já reflete os ajustes manuais feitos até hoje, e criar baixas
 * retroativas para pedidos antigos descontaria o mesmo estoque duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reserved_qty')->default(0)->after('qty');
        });

        // Estado por (pedido, produto). É a fonte que garante a RN-04:
        // cancelar libera exatamente o que aquele pedido segurava.
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('status', 20)->default('reserved'); // reserved | committed | released
            $table->timestamps();

            $table->unique(['order_id', 'product_id']);
            $table->index(['product_id', 'status']);
        });

        // Ledger append-only. Sem foreign key de propósito: o histórico
        // precisa sobreviver à exclusão do pedido ou do produto (requisito de
        // rollback da TASK-024).
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->string('type', 32); // reserve | release | commit | uncommit | manual_entry | manual_adjust
            $table->unsignedInteger('quantity');
            $table->integer('qty_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->unsignedInteger('qty_after');
            $table->unsignedInteger('reserved_after');
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('idempotency_key')->unique();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_reservations');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('reserved_qty');
        });
    }
};
