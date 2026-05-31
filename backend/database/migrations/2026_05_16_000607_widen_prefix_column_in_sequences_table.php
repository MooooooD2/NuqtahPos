<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->string('prefix', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sequences', function (Blueprint $table) {
            $table->string('prefix', 10)->nullable()->change();
        });
    }
};
