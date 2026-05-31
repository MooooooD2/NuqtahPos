<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('document_number');
            $table->integer('copies')->default(1);
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('print_method')->default('thermal');
            $table->boolean('success')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_type', 'document_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_logs');
    }
};
