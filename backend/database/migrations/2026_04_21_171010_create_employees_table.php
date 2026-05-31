<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->enum('role', ['admin', 'cashier', 'warehouse']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('employees');
    }
};
