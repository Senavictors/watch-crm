<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('id');
            $table->foreignId('model_id')->nullable()->after('brand_id');
        });

        $now = now();
        $products = DB::table('products')->select('id', 'brand', 'model')->get();

        foreach ($products as $product) {
            if (! $product->brand) {
                continue;
            }

            DB::table('brands')->insertOrIgnore([
                'name' => $product->brand,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($products as $product) {
            if (! $product->brand || ! $product->model) {
                continue;
            }

            $brandId = DB::table('brands')->where('name', $product->brand)->value('id');

            if (! $brandId) {
                continue;
            }

            DB::table('models')->insertOrIgnore([
                'brand_id' => $brandId,
                'name' => $product->model,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($products as $product) {
            $brandId = DB::table('brands')->where('name', $product->brand)->value('id');
            $modelId = $brandId
                ? DB::table('models')
                    ->where('brand_id', $brandId)
                    ->where('name', $product->model)
                    ->value('id')
                : null;

            DB::table('products')->where('id', $product->id)->update([
                'brand_id' => $brandId,
                'model_id' => $modelId,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
            $table->foreign('model_id')->references('id')->on('models')->cascadeOnDelete();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE products MODIFY brand_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE products MODIFY model_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['brand', 'model']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('id');
            $table->string('model')->nullable()->after('brand');
        });

        $products = DB::table('products')->select('id', 'brand_id', 'model_id')->get();

        foreach ($products as $product) {
            $brandName = $product->brand_id
                ? DB::table('brands')->where('id', $product->brand_id)->value('name')
                : null;

            $modelName = $product->model_id
                ? DB::table('models')->where('id', $product->model_id)->value('name')
                : null;

            DB::table('products')->where('id', $product->id)->update([
                'brand' => $brandName,
                'model' => $modelName,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['model_id']);
            $table->dropColumn(['brand_id', 'model_id']);
        });
    }
};
