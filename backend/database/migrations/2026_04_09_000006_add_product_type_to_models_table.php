<?php

use App\Models\WatchModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('models', 'product_type')) {
            Schema::table('models', function (Blueprint $table) {
                $table->string('product_type')->default(WatchModel::TYPE_WATCH)->after('name');
            });
        }

        if (! Schema::hasColumn('models', 'quality_key')) {
            Schema::table('models', function (Blueprint $table) {
                $table->unsignedBigInteger('quality_key')->default(0)->after('quality_id');
            });
        }

        DB::table('models')
            ->update([
                'product_type' => WatchModel::TYPE_WATCH,
                'quality_key' => DB::raw('COALESCE(quality_id, 0)'),
            ]);

        Schema::table('models', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['quality_id']);
            $table->dropUnique(['brand_id', 'name', 'quality_id']);
            $table->unsignedBigInteger('quality_id')->nullable()->change();
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
            $table->foreign('quality_id')->references('id')->on('qualities')->cascadeOnDelete();
            $table->unique(['brand_id', 'name', 'product_type', 'quality_key'], 'models_catalog_unique');
        });
    }

    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropUnique('models_catalog_unique');
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['quality_id']);
            $table->unsignedBigInteger('quality_id')->nullable(false)->change();
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
            $table->foreign('quality_id')->references('id')->on('qualities')->cascadeOnDelete();
            $table->unique(['brand_id', 'name', 'quality_id']);
            $table->dropColumn(['product_type', 'quality_key']);
        });
    }
};
