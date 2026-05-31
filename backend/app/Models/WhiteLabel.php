<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 4 — White Label Branding Model
 */
class WhiteLabel extends Model
{
    protected $fillable = [
        'tenant_id', 'app_name', 'logo_path', 'favicon_path', 'login_bg_path',
        'primary_color', 'secondary_color', 'accent_color', 'text_color', 'bg_color',
        'font_family', 'custom_domain', 'domain_verified', 'domain_verified_at',
        'hide_powered_by', 'custom_css', 'footer_text',
        'support_email', 'support_phone', 'website_url',
    ];

    protected $casts = [
        'hide_powered_by' => 'boolean',
        'domain_verified' => 'boolean',
        'domain_verified_at' => 'datetime',
    ];

    // ── Computed URLs ─────────────────────────────────────────────────────────

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->favicon_path ? Storage::url($this->favicon_path) : null;
    }

    public function getLoginBgUrlAttribute(): ?string
    {
        return $this->login_bg_path ? Storage::url($this->login_bg_path) : null;
    }

    // ── CSS Variables ─────────────────────────────────────────────────────────

    /**
     * Returns an inline <style> block with CSS custom properties.
     */
    public function toCssVars(): string
    {
        return sprintf(
            ':root{--color-primary:%s;--color-secondary:%s;--color-accent:%s;--color-text:%s;--color-bg:%s;--font-family:%s}',
            $this->primary_color,
            $this->secondary_color,
            $this->accent_color,
            $this->text_color,
            $this->bg_color,
            $this->font_family,
        );
    }
}
