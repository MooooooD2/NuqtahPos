<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();          // UUID, set by stancl/tenancy
            $table->string('name');                   // Display name  e.g. "Acme Store"
            $table->string('code')->unique();         // Login code    e.g. "acme"
            $table->string('plan')->default('basic'); // basic | pro | enterprise
            $table->boolean('is_active')->default(true);
            $table->json('data')->nullable();         // stancl/tenancy extra storage
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
