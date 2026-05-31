<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('item_code_type', 10)->default('EGS')->after('barcode');
            $table->string('item_code', 50)->nullable()->after('item_code_type');
            $table->string('unit_type', 10)->default('EA')->after('item_code');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('eta_uuid', 100)->nullable()->after('invoice_number');
            $table->string('eta_long_id', 100)->nullable()->after('eta_uuid');
            $table->string('eta_submission_id', 100)->nullable()->after('eta_long_id');
            $table->enum('eta_status', ['pending', 'submitted', 'valid', 'invalid', 'cancelled', 'rejected'])
                ->default('pending')->after('eta_submission_id');
            $table->text('eta_response')->nullable()->after('eta_status');
            $table->timestamp('eta_submitted_at')->nullable()->after('eta_response');
            $table->string('eta_hash', 64)->nullable()->after('eta_submitted_at');
        });

        Schema::create('eta_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_id')->unique();
            $table->integer('document_count');
            $table->json('accepted_documents')->nullable();
            $table->json('rejected_documents')->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('eta_tokens', function (Blueprint $table) {
            $table->id();
            $table->text('access_token');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eta_tokens');
        Schema::dropIfExists('eta_submissions');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'eta_uuid', 'eta_long_id', 'eta_submission_id',
                'eta_status', 'eta_response', 'eta_submitted_at', 'eta_hash',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['item_code_type', 'item_code', 'unit_type']);
        });
    }
};
