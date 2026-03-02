<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropUnique(['brand_id', 'name']);
            $table->string('quality')->default('BASE_ETA')->after('name');
            $table->unique(['brand_id', 'name', 'quality']);
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropUnique(['brand_id', 'name', 'quality']);
            $table->dropColumn('quality');
            $table->unique(['brand_id', 'name']);
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
        });
    }
};
