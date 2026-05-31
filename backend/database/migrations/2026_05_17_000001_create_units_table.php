<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->after('category')
                    ->constrained('units')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'unit_id')) {
                $table->dropForeign(['unit_id']);
                $table->dropColumn('unit_id');
            }
        });

        Schema::dropIfExists('units');
    }
};
