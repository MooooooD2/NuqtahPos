<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * FIX-XSS: إضافة Security Headers لمنع XSS وهجمات أخرى
     * FIX-7: إزالة unsafe-inline من script-src وإضافة nonce بديلاً عنها
     *        (للـ inline scripts الضرورية فقط)
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate nonce BEFORE rendering the view so Blade templates can read it
        $nonce = base64_encode(random_bytes(16));
        app()->instance('csp-nonce', $nonce);

        $response = $next($request);

        // X-Content-Type-Options and HSTS are useful on every response type
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // The remaining headers are only meaningful on HTML documents.
        if (! str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // X-XSS-Protection: 0 is the modern recommendation; CSP replaces it
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // camera=(self) — allow camera on the same origin for barcode scanning (warehouse page)
        // microphone/geolocation remain fully blocked; payment=(self) for Paymob/PayPal
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=(self), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        // Remove fingerprinting headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
            "script-src-elem 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
            "style-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.bunny.net; " .
            "style-src-elem 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.bunny.net; " .
            "style-src-attr 'nonce-{$nonce}'; " .
            "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com https://fonts.bunny.net data:; " .
            "img-src 'self' data: blob: https://api.qrserver.com; " .
            "connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://sentry.io https://*.sentry.io wss: ws:; " .
            "object-src 'none'; " .
            "base-uri 'self'; " .
            "form-action 'self';",
        );

        return $response;
    }
}
