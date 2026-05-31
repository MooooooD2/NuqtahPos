<?php

declare(strict_types=1);

namespace Tests\Feature\Phase0;

use Tests\TestCase;

/**
 * Phase 0 — Security Baseline Tests
 */
class SecurityHeadersTest extends TestCase
{
    /** @test */
    public function it_sets_x_content_type_options_header(): void
    {
        $response = $this->get(route('login'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** @test */
    public function it_sets_x_frame_options_header(): void
    {
        $response = $this->get(route('login'));
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /** @test */
    public function it_sets_referrer_policy_header(): void
    {
        $response = $this->get(route('login'));
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /** @test */
    public function it_sets_content_security_policy_header(): void
    {
        $response = $this->get(route('login'));
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
    }

    /** @test */
    public function it_sets_permissions_policy_header(): void
    {
        $response = $this->get(route('login'));
        $this->assertNotEmpty($response->headers->get('Permissions-Policy'));
    }
}
