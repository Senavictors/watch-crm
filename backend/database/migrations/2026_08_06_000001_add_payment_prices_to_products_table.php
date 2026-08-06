<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-004 — preços por forma de pagamento (docs/... RN-01/RN-02).
 *
 * `price_pix`/`price_card` são opcionais: quando nulos, o preço padrão
 * (`price`) continua sendo a sugestão para qualquer forma de pagamento —
 * não há necessidade de migrar dados legados (RN-02: catálogo antigo
 * continua funcionando, e o preço efetivo da venda já é congelado em
 * `order_items.unit_price`, nunca recalculado a partir do catálogo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_pix', 10, 2)->nullable()->after('price');
            $table->decimal('price_card', 10, 2)->nullable()->after('price_pix');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_pix', 'price_card']);
        });
    }
};
