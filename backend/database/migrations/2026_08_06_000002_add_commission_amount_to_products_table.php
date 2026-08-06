<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-005 — comissões por produto e venda (docs/domain/financial-rules.md
 * "Comissão"). `commission_amount` é opcional, igual ao padrão de
 * `price_pix`/`price_card` (TASK-004): quando nulo, o produto simplesmente
 * não gera comissão (RN-01 não obriga configurar todo produto — ex.: caixas
 * hoje não têm comissão definida pelo negócio real, ver
 * docs/domain/financial-rules.md e a memória de perfil do negócio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('commission_amount', 10, 2)->nullable()->after('price_card');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('commission_amount');
        });
    }
};
