<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('has_quality')->default(false);
            $table->timestamps();
        });

        $now = now();

        DB::table('categories')->insert([
            ['id' => 1, 'name' => 'Relógios', 'has_quality' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Caixas', 'has_quality' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
