<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-018 — lista de espera por produto. Tabela nova (sem dados legados a
 * migrar), snapshot de nome/marca/modelo/qualidade no momento do registro
 * (mesmo padrão de `return_items`/`order_items`, ver
 * `2026_04_09_000007_create_order_items_table.php`) para sobreviver a
 * produto excluído/renomeado depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('brand_name')->nullable();
            $table->string('model_name')->nullable();
            $table->string('quality_name')->nullable();
            $table->foreignId('seller_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->date('notified_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
