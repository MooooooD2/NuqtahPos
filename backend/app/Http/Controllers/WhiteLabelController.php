<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WhiteLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 4 — White Label Management Controller
 */
class WhiteLabelController extends Controller
{
    public function index()
    {
        $tenantId = tenant('id');
        $wl = WhiteLabel::firstOrCreate(['tenant_id' => $tenantId]);

        return view('white-label.index', compact('wl'));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_name' => 'nullable|string|max:100',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'accent_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'bg_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'font_family' => 'nullable|string|max:100',
            'hide_powered_by' => 'nullable|boolean',
            'custom_css' => 'nullable|string|max:10000',
            'footer_text' => 'nullable|string|max:500',
            'support_email' => 'nullable|email',
            'support_phone' => 'nullable|string|max:30',
            'website_url' => 'nullable|url',
            'support_website' => 'nullable|url',   // form field alias for website_url
        ]);

        $tenantId = tenant('id');
        $wl = WhiteLabel::firstOrCreate(['tenant_id' => $tenantId]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $request->validate(['logo' => 'image|max:2048']);
            if ($wl->logo_path) {
                Storage::delete($wl->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store("tenants/{$tenantId}/branding", 'public');
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            $request->validate(['favicon' => 'file|mimes:ico,png|max:512']);
            if ($wl->favicon_path) {
                Storage::delete($wl->favicon_path);
            }
            $data['favicon_path'] = $request->file('favicon')->store("tenants/{$tenantId}/branding", 'public');
        }

        // Also accept 'support_website' as alias for 'website_url' (form sends support_website)
        if (isset($data['support_website'])) {
            $data['website_url'] = $data['support_website'];
            unset($data['support_website']);
        }

        $wl->update($data);

        // Bust the branding cache so the layout picks up new settings immediately
        Cache::forget("wl_branding:{$tenantId}");

        return response()->json(['success' => true, 'white_label' => $wl]);
    }

    public function setCustomDomain(Request $request): JsonResponse
    {
        $request->validate(['domain' => 'required|string|max:253']);

        $tenantId = tenant('id');
        $domain = strtolower(trim($request->domain));

        // Check domain not taken by another tenant
        $taken = WhiteLabel::where('custom_domain', $domain)
            ->where('tenant_id', '!=', $tenantId)
            ->exists();

        if ($taken) {
            return response()->json(['success' => false, 'message' => 'Domain already in use.'], 422);
        }

        $wl = WhiteLabel::firstOrCreate(['tenant_id' => $tenantId]);
        $wl->update(['custom_domain' => $domain, 'domain_verified' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Domain saved. Add a CNAME record pointing to ' . config('app.url') . ' then verify.',
            'verify_txt' => "pos-verify={$tenantId}",
        ]);
    }

    public function verifyDomain(): JsonResponse
    {
        $tenantId = tenant('id');
        $wl = WhiteLabel::where('tenant_id', $tenantId)->firstOrFail();

        if (! $wl->custom_domain) {
            return response()->json(['success' => false, 'message' => 'No custom domain set.'], 422);
        }

        // Check DNS TXT record
        $records = @dns_get_record($wl->custom_domain, DNS_TXT) ?: [];
        $expected = "pos-verify={$tenantId}";
        $verified = collect($records)->contains(fn ($r) => isset($r['txt']) && str_contains($r['txt'], $expected));

        if ($verified) {
            $wl->update(['domain_verified' => true, 'domain_verified_at' => now()]);
        }

        return response()->json(['success' => $verified, 'verified' => $verified]);
    }

    /**
     * Public endpoint: returns CSS vars for a tenant (used by custom domain middleware).
     */
    public function cssVars(): \Illuminate\Http\Response
    {
        $tenantId = tenant('id');
        $wl = WhiteLabel::where('tenant_id', $tenantId)->first();

        if (! $wl) {
            return response('', 204);
        }

        $css = $wl->toCssVars();
        if ($wl->custom_css) {
            $css .= "\n" . $wl->custom_css;
        }

        return response($css, 200, ['Content-Type' => 'text/css']);
    }
}
