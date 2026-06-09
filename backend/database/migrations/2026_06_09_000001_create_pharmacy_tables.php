<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('generic_name')->nullable();
            $table->string('category')->default('other');       // antibiotic|analgesic|antihypertensive|diabetes|vitamin|other
            $table->string('dosage_form')->default('tablet');   // tablet|capsule|syrup|injection|cream|drops
            $table->string('strength')->nullable();             // e.g. "500mg", "10ml"
            $table->string('unit')->default('tablet');
            $table->string('barcode')->nullable()->unique();
            $table->string('manufacturer')->nullable();
            $table->boolean('requires_prescription')->default(false);
            $table->boolean('controlled_drug')->default(false);
            $table->unsignedInteger('reorder_level')->default(10);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();
            $table->string('lot_number')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->date('expiry_date');
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->date('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number')->unique();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('patient_name');
            $table->string('patient_phone')->nullable();
            $table->string('doctor_name');
            $table->string('doctor_phone')->nullable();
            $table->string('clinic_name')->nullable();
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending|partially_dispensed|fully_dispensed
            $table->unsignedBigInteger('dispensed_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->unsignedInteger('quantity_prescribed');
            $table->unsignedInteger('quantity_dispensed')->default(0);
            $table->string('dosage_instructions')->nullable();
            $table->string('status')->default('pending'); // pending|dispensed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('medicines');
    }
};
