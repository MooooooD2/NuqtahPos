<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printer_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->unsignedBigInteger('document_id');
            $table->string('document_number')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])
                ->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->longText('raw_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
