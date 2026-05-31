<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['outbound', 'inbound'])->default('outbound');
            $table->string('to_number', 20)->nullable();
            $table->string('from_number', 20)->nullable();
            $table->enum('message_type', ['invoice', 'reminder', 'alert', 'promotion', 'inbound', 'order'])->default('invoice');
            $table->string('template_name', 100)->nullable();
            $table->text('message_body')->nullable();
            $table->string('related_type', 50)->nullable(); // invoice, customer, etc.
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('wa_message_id', 100)->nullable(); // Meta's message ID
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed', 'read'])->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->index(['to_number', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
