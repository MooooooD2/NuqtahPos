<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['reporting', 'clearance'])->default('reporting');
            $table->enum('status', ['pending', 'submitted', 'accepted', 'rejected', 'error'])->default('pending');
            $table->string('invoice_hash', 64)->nullable();
            $table->string('uuid', 36)->nullable();
            $table->text('qr_code')->nullable();           // Base64 TLV QR
            $table->text('signed_xml')->nullable();        // UBL XML
            $table->text('clearance_status')->nullable();  // ZATCA clearance status
            $table->json('zatca_response')->nullable();    // Full ZATCA API response
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_submissions');
    }
};
