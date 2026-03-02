<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('models', 'quality_id')) {
            Schema::table('models', function (Blueprint $table) {
                $table->unsignedBigInteger('quality_id')->nullable()->after('name');
            });
        }

        $now = now();
        DB::table('qualities')->insertOrIgnore([
            ['name' => 'Base ETA', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Clone', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $baseId = DB::table('qualities')->where('name', 'Base ETA')->value('id');
        $cloneId = DB::table('qualities')->where('name', 'Clone')->value('id');

        if ($baseId) {
            DB::table('models')->where('quality', 'BASE_ETA')->update(['quality_id' => $baseId]);
            DB::table('models')->whereNull('quality')->update(['quality_id' => $baseId]);
        }

        if ($cloneId) {
            DB::table('models')->where('quality', 'CLONE')->update(['quality_id' => $cloneId]);
        }

        Schema::table('models', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
        });

        Schema::table('models', function (Blueprint $table) {
            $table->dropUnique(['brand_id', 'name', 'quality']);
            $table->dropColumn('quality');
            $table->unique(['brand_id', 'name', 'quality_id']);
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();
            $table->foreign('quality_id')->references('id')->on('qualities')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE models MODIFY quality_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        Schema::table('models', function (Blueprint $table) {
            $table->dropForeign(['quality_id']);
            $table->dropUnique(['brand_id', 'name', 'quality_id']);
            $table->string('quality')->default('BASE_ETA')->after('name');
        });

        $baseId = DB::table('qualities')->where('name', 'Base ETA')->value('id');
        $cloneId = DB::table('qualities')->where('name', 'Clone')->value('id');

        if ($baseId) {
            DB::table('models')->where('quality_id', $baseId)->update(['quality' => 'BASE_ETA']);
        }

        if ($cloneId) {
            DB::table('models')->where('quality_id', $cloneId)->update(['quality' => 'CLONE']);
        }

        Schema::table('models', function (Blueprint $table) {
            $table->dropColumn('quality_id');
            $table->unique(['brand_id', 'name', 'quality']);
        });
    }
};
