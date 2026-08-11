<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-025 / ADR-007 — preservação de histórico financeiro.
 *
 * Duas coisas acontecem aqui:
 *
 * 1. Colunas de arquivamento (cliente) e de estorno (devolução e despesa).
 *    Nenhum dado existente é reescrito: tudo nasce `null`, ou seja, nada
 *    fica arquivado nem estornado retroativamente.
 *
 * 2. Troca das cascatas destrutivas do catálogo e do cliente por RESTRICT.
 *    Isso só é aplicado no MySQL: no SQLite trocar uma FK exige recriar a
 *    tabela inteira, o que traria mais risco do que benefício num banco
 *    in-memory recriado a cada execução de teste. A garantia de aplicação
 *    (409 nos controllers) é a mesma nos dois bancos; a garantia de banco é
 *    verificada por `tests/Feature/RestrictedDeletesMySqlTest.php` contra
 *    MySQL real.
 */
return new class extends Migration
{
    /**
     * FKs que deixam de destruir histórico. Formato:
     * [tabela, coluna, tabela referenciada].
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const RESTRICTED = [
        // Apagar cliente não pode levar junto pedidos, devoluções e atritos.
        ['orders', 'customer_id', 'customers'],
        ['returns', 'customer_id', 'customers'],
        ['customer_friction_notes', 'customer_id', 'customers'],
        // Apagar marca/categoria/qualidade não pode varrer o catálogo.
        ['models', 'brand_id', 'brands'],
        ['models', 'category_id', 'categories'],
        ['models', 'quality_id', 'qualities'],
        ['products', 'brand_id', 'brands'],
        ['products', 'model_id', 'models'],
    ];

    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('owner_user_id');
            $table->foreignId('archived_by_user_id')->nullable()->after('archived_at')
                ->constrained('users')->nullOnDelete();
        });

        foreach (['returns', 'expenses'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->timestamp('voided_at')->nullable();
                $blueprint->foreignId('voided_by_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $blueprint->string('void_reason', 500)->nullable();
            });
        }

        $this->rewriteForeignKeys('restrict');
    }

    public function down(): void
    {
        $this->rewriteForeignKeys('cascade');

        foreach (['returns', 'expenses'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('voided_by_user_id');
                $blueprint->dropColumn(['voided_at', 'void_reason']);
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by_user_id');
            $table->dropColumn('archived_at');
        });
    }

    private function rewriteForeignKeys(string $rule): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::RESTRICTED as [$table, $column, $references]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $rule) {
                $foreign = $blueprint->foreign($column)->references('id')->on($references);

                if ($rule === 'restrict') {
                    $foreign->restrictOnDelete();
                } else {
                    $foreign->cascadeOnDelete();
                }
            });
        }
    }
};
