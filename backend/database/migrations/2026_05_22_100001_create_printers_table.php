<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('connection_type', ['usb', 'network', 'windows'])->default('network');
            $table->string('ip_address')->nullable();
            $table->integer('port')->default(9100);
            $table->string('usb_device')->nullable();
            $table->string('windows_printer_name')->nullable();
            $table->enum('paper_width', ['58', '80'])->default('80');
            $table->integer('characters_per_line')->default(48);
            $table->enum('character_set', ['CP437', 'CP720', 'UTF-8'])->default('CP720');
            $table->boolean('auto_cut')->default(true);
            $table->boolean('auto_open_drawer')->default(false);
            $table->integer('copies')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('capabilities')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
