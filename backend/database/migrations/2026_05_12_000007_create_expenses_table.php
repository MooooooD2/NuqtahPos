<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('expense_categories')->insert([
            ['name' => 'Rent',        'description' => 'Office / store rent',             'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Utilities',   'description' => 'Electricity, water, internet',     'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Salaries',    'description' => 'Employee wages and salaries',       'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Transport',   'description' => 'Delivery and transport costs',      'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Maintenance', 'description' => 'Equipment and premises maintenance', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Marketing',   'description' => 'Advertising and promotions',        'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other',       'description' => 'Miscellaneous expenses',            'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'wallet'])->default('cash');
            $table->string('reference')->nullable();
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->string('created_by_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
