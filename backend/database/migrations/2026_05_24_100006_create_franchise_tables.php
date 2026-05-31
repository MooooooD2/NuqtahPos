<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 — Franchise Royalties Engine
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchise_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('franchisee_tenant_id');           // stancl tenant ID of the franchisee
            $table->string('franchisor_tenant_id');           // stancl tenant ID of the franchisor (HQ)
            $table->string('agreement_number')->unique();
            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Royalty structure
            $table->enum('royalty_type', ['percentage', 'fixed', 'tiered'])->default('percentage');
            $table->decimal('royalty_rate', 8, 4)->default(0);    // percentage (e.g. 5.00 = 5%)
            $table->decimal('fixed_amount', 14, 2)->default(0);   // fixed monthly fee
            $table->json('tiers')->nullable();                     // [{min_sales, max_sales, rate}]

            // Marketing fund contribution
            $table->decimal('marketing_fee_rate', 8, 4)->default(0);

            // Payment settings
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'weekly'])->default('monthly');
            $table->unsignedTinyInteger('billing_day')->default(1);  // day of month to bill
            $table->string('currency_code', 3)->default('EGP');

            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->text('terms')->nullable();
            $table->timestamps();

            $table->index(['franchisee_tenant_id', 'status']);
            $table->index('franchisor_tenant_id');
        });

        Schema::create('royalty_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_agreement_id')->constrained()->cascadeOnDelete();
            $table->string('period');                      // e.g. "2026-05"
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_sales', 14, 2)->default(0);
            $table->decimal('royalty_amount', 14, 2)->default(0);
            $table->decimal('marketing_fee', 14, 2)->default(0);
            $table->decimal('total_due', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);
            $table->enum('status', ['draft', 'invoiced', 'paid', 'overdue', 'disputed'])->default('draft');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('breakdown')->nullable();           // daily/weekly sales detail
            $table->timestamps();

            $table->index(['franchise_agreement_id', 'period']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('royalty_statements');
        Schema::dropIfExists('franchise_agreements');
    }
};
