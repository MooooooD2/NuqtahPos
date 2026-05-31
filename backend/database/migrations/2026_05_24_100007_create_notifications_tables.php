<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 — Advanced Notifications (DB + FCM push)
 * Extends Laravel's built-in notifications with FCM push token storage.
 */
return new class extends Migration
{
    public function up(): void
    {
        // FCM device tokens per user
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fcm_token', 500);
            $table->string('device_type')->default('web');   // web, android, ios
            $table->string('device_id')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fcm_token'], 'push_sub_unique');
            $table->index('user_id');
        });

        // Notification templates (multi-language)
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                 // e.g. 'low_stock_alert'
            $table->string('channel');                       // db, fcm, mail, sms
            $table->string('title_en');
            $table->string('title_ar');
            $table->text('body_en');
            $table->text('body_ar');
            $table->json('default_data')->nullable();        // extra payload
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Notification preference per user (opt-in/out)
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                          // notification class name or key
            $table->boolean('db')->default(true);
            $table->boolean('push')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('sms')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('push_subscriptions');
    }
};
