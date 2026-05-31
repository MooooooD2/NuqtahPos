<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['individual', 'business'])->default('individual');
            $table->string('name');
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->nullable();

            // للأفراد
            $table->string('national_id', 14)->nullable();

            // للشركات (ETA B2B)
            $table->string('tax_number', 20)->nullable();
            $table->string('commercial_register', 30)->nullable();

            // العنوان (مطلوب لـ ETA)
            $table->string('governate', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();

            // مالي
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);

            // Loyalty
            $table->integer('loyalty_points')->default(0);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('customer_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->enum('type', ['invoice', 'payment', 'return', 'adjustment']);
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('cashier_id')
                ->constrained('customers');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('customer_accounts');
        Schema::dropIfExists('customers');
    }
};
