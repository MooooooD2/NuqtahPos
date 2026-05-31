<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100);
            $table->string('model', 150)->nullable();
            $table->string('record_id', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 250)->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // No updated_at — audit logs are insert-only
            $table->index(['action', 'created_at']);
            $table->index(['model', 'record_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
