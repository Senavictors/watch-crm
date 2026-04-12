<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // garantia | troca | devolucao
            $table->string('status');
            $table->text('reason')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->date('received_date')->nullable();
            $table->date('resolved_date')->nullable();
            $table->decimal('freight_cost_in', 10, 2)->default(0);
            $table->decimal('watchmaker_cost', 10, 2)->default(0);
            $table->decimal('freight_cost_out', 10, 2)->default(0);
            $table->decimal('other_costs', 10, 2)->default(0);
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->string('return_tracking_code')->nullable();
            $table->date('shipped_back_date')->nullable();
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_type');
            $table->string('brand_name')->nullable();
            $table->string('model_name')->nullable();
            $table->string('quality_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
