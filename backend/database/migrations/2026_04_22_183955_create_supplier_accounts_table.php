<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->decimal('debit', 10, 2)->default(0);
            $table->decimal('credit', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_accounts');
    }
};
