<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->string('invoice_number');
            $table->string('customer_name')->nullable();
            $table->decimal('total_amount', 15, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['completed'])->default('completed');
            $table->timestamp('return_date');
            $table->foreignId('processed_by')->constrained('users');
            $table->string('processed_by_name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('returns');
    }
};
