<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-006 — módulo de despesas gerais (docs/domain/financial-rules.md
 * "Despesas"). RN-01: compras de estoque não são despesas gerais — não há
 * FK pra `products`/`orders` aqui de propósito, o módulo é isolado do
 * catálogo/estoque. RN-03: o total do período usa `expense_date`, não
 * `created_at` (a despesa pode ser lançada com atraso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            // Nullable + nullOnDelete (mesmo padrão de paid_by_user_id/
            // commission_paid_by_user_id): a despesa é um registro
            // financeiro auditável e não deve desaparecer se o usuário que a
            // lançou for removido — hoje não há exclusão de usuário na API
            // (só `toggleActive`), mas o schema não deve depender disso.
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['expense_date', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
