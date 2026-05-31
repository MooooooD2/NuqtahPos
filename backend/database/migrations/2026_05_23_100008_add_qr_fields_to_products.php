<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'image')) {
                $table->string('image')->nullable()->after('category');
            }
            if (! Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable()->after('image');
            }
            if (! Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('products', 'is_active')   ? 'is_active'   : null,
                Schema::hasColumn('products', 'description') ? 'description' : null,
                Schema::hasColumn('products', 'image')       ? 'image'       : null,
            ]));
        });
    }
};
