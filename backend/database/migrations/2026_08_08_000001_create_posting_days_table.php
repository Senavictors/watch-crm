<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posting_days', function (Blueprint $table) {
            $table->id();
            // Convenção `0=domingo...6=sábado`, igual ao Carbon::dayOfWeek e
            // ao JS Date.getDay() — sem necessidade de mapear entre backend
            // e frontend (TASK-016).
            $table->tinyInteger('weekday')->unique();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        $now = now();

        // RN-01 (TASK-016): configuração inicial segunda (1) e quinta (4)
        // habilitadas, tabela já nasce populada e utilizável — sem seeder
        // separado, mesmo padrão de `2026_08_05_000001_create_categories_table.php`.
        DB::table('posting_days')->insert([
            ['weekday' => 0, 'enabled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['weekday' => 1, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['weekday' => 2, 'enabled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['weekday' => 3, 'enabled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['weekday' => 4, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now],
            ['weekday' => 5, 'enabled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['weekday' => 6, 'enabled' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_days');
    }
};
