<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'subscription_status')) {
                $table->string('subscription_status', 20)->default('trial')->after('plan');
            }
            if (! Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            }
            if (! Schema::hasColumn('tenants', 'subscription_ends_at')) {
                $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['subscription_status', 'trial_ends_at', 'subscription_ends_at']);
        });
    }
};
