<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_name');
            $table->string('channel');
            $table->string('seller');
            $table->string('status');
            $table->decimal('sale_price', 10, 2);
            $table->decimal('cost', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('freight', 10, 2)->default(0);
            $table->decimal('channel_fee', 10, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('shipping_method');
            $table->string('tracking_code')->nullable();
            $table->date('sale_date');
            $table->date('shipped_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
