<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code')->unique();
            $table->string('account_name');
            $table->enum('account_type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->onDelete('cascade');
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('accounts');
    }
};
