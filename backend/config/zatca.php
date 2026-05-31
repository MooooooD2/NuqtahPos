<?php

/**
 * Phase 5 — ZATCA e-Invoicing Configuration (Saudi Arabia)
 * Fatoorah Phase 1 & Phase 2 compliance.
 *
 * Environment variables to set in .env:
 *   ZATCA_VAT_NUMBER=310122393500003
 *   ZATCA_CERT_TOKEN=<base64 cert from ZATCA portal>
 *   ZATCA_ENV=sandbox  # or: production
 */
return [
    'enabled' => env('ZATCA_ENABLED', false),
    'vat_number' => env('ZATCA_VAT_NUMBER', ''),
    'cert_token' => env('ZATCA_CERT_TOKEN', ''),

    'api_url' => env('ZATCA_ENV', 'sandbox') === 'production'
        ? 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core'
        : 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',

    // Phase 2 clearance threshold: invoices above this amount require clearance
    'clearance_threshold' => env('ZATCA_CLEARANCE_THRESHOLD', 0),

    // Seller details (for XML generation)
    'seller' => [
        'name' => env('ZATCA_SELLER_NAME', env('APP_NAME', 'My Store')),
        'city' => env('ZATCA_SELLER_CITY', 'Riyadh'),
        'postal_code' => env('ZATCA_SELLER_POSTAL', '12345'),
        'building_no' => env('ZATCA_SELLER_BUILDING', '1234'),
        'street' => env('ZATCA_SELLER_STREET', 'King Fahd Road'),
        'crn' => env('ZATCA_SELLER_CRN', ''),        // Commercial Registration Number
    ],
];
