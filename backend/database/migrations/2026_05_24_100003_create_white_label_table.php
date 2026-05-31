<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — White Label System
 * Each tenant can have its own branding, colors, logo, and domain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_labels', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();          // stancl/tenancy tenant ID

            // Branding
            $table->string('app_name')->default('POS System');
            $table->string('logo_path')->nullable();        // storage path
            $table->string('favicon_path')->nullable();
            $table->string('login_bg_path')->nullable();

            // Colors (CSS custom properties)
            $table->string('primary_color', 7)->default('#3b82f6');    // --color-primary
            $table->string('secondary_color', 7)->default('#1e293b');  // --color-secondary
            $table->string('accent_color', 7)->default('#10b981');     // --color-accent
            $table->string('text_color', 7)->default('#0f172a');
            $table->string('bg_color', 7)->default('#f8fafc');

            // Typography
            $table->string('font_family')->default('Inter, sans-serif');

            // Custom domain
            $table->string('custom_domain')->nullable()->unique();
            $table->boolean('domain_verified')->default(false);
            $table->timestamp('domain_verified_at')->nullable();

            // Hide / show vendor branding
            $table->boolean('hide_powered_by')->default(false);

            // Custom CSS override (injected into <head>)
            $table->text('custom_css')->nullable();

            // Custom footer text
            $table->string('footer_text')->nullable();

            // Contact info displayed on receipts / login
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('website_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_labels');
    }
};
