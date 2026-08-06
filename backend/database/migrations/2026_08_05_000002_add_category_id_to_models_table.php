<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')->constrained('categories')->cascadeOnDelete();
        });

        DB::table('models')->where('product_type', 'WATCH')->update(['category_id' => 1]);
        DB::table('models')->where('product_type', 'BOX')->update(['category_id' => 2]);
        DB::table('models')->whereNull('category_id')->update(['category_id' => 1]);

        Schema::table('models', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
            $table->unique(['brand_id', 'name', 'category_id', 'quality_key'], 'models_category_unique');
        });

        Schema::table('models', function (Blueprint $table) {
            $table->dropUnique('models_catalog_unique');
            $table->dropColumn('product_type');
        });
    }

    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->string('product_type')->default('WATCH')->after('name');
        });

        DB::table('models')->where('category_id', 1)->update(['product_type' => 'WATCH']);
        DB::table('models')->where('category_id', 2)->update(['product_type' => 'BOX']);

        Schema::table('models', function (Blueprint $table) {
            $table->unique(['brand_id', 'name', 'product_type', 'quality_key'], 'models_catalog_unique');
        });

        Schema::table('models', function (Blueprint $table) {
            $table->dropUnique('models_category_unique');
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
