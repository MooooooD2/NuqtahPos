<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('code', 20)->unique();
            $table->decimal('rate', 5, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tax_category_id')->nullable()->constrained('tax_categories')->nullOnDelete()->after('unit_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tax_category_id']);
            $table->dropColumn('tax_category_id');
        });

        Schema::dropIfExists('tax_categories');
    }
};
