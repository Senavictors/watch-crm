<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('street', 255)->nullable()->after('instagram');
            $table->string('number', 20)->nullable()->after('street');
            $table->string('complement', 255)->nullable()->after('number');
            $table->string('zip_code', 20)->nullable()->after('complement');
            $table->string('city', 100)->nullable()->after('zip_code');
            $table->string('state', 2)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['street', 'number', 'complement', 'zip_code', 'city', 'state']);
        });
    }
};
