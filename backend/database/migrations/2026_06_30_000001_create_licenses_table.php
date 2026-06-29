<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('key_prefix', 8)->index();
            $table->string('license_key');             // hashed, never stored plain
            $table->string('device_id')->nullable();
            $table->string('device_name')->nullable();
            $table->enum('status', ['pending', 'active', 'revoked', 'expired'])->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
