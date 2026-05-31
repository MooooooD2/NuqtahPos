<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('value')->default(0);
            $table->string('prefix', 10)->nullable();
        });

        // Insert initial sequences - إدراج التسلسلات الأولية
        DB::table('sequences')->insert([
            ['name' => 'invoice',  'prefix' => 'INV'],
            ['name' => 'purchase', 'prefix' => 'PO'],
            ['name' => 'return',   'prefix' => 'RET'],
            ['name' => 'journal',  'prefix' => 'JE'],
            ['name' => 'payment',  'prefix' => 'PAY'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
