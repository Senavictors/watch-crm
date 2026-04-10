<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreignId('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_type');
            $table->string('brand_name')->nullable();
            $table->string('model_name')->nullable();
            $table->string('quality_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('unit_discount', 10, 2)->default(0);
            $table->timestamps();
        });

        $legacyOrders = DB::table('orders')
            ->leftJoin('products', 'orders.product_id', '=', 'products.id')
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->leftJoin('models', 'products.model_id', '=', 'models.id')
            ->leftJoin('qualities', 'models.quality_id', '=', 'qualities.id')
            ->select([
                'orders.id as order_id',
                'orders.product_id',
                'orders.product_name',
                'orders.sale_price',
                'orders.cost',
                'orders.discount',
                'orders.created_at',
                'orders.updated_at',
                'brands.name as brand_name',
                'models.name as model_name',
                'models.product_type',
                'qualities.name as quality_name',
            ])
            ->get()
            ->map(fn ($order) => [
                'order_id' => $order->order_id,
                'product_id' => $order->product_id,
                'product_name' => $order->product_name,
                'product_type' => $order->product_type ?? 'WATCH',
                'brand_name' => $order->brand_name,
                'model_name' => $order->model_name,
                'quality_name' => $order->quality_name,
                'quantity' => 1,
                'unit_price' => $order->sale_price,
                'unit_cost' => $order->cost,
                'unit_discount' => $order->discount,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ])
            ->all();

        if ($legacyOrders !== []) {
            DB::table('order_items')->insert($legacyOrders);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreignId('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
