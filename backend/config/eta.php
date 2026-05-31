<?php

return [
    'enabled' => env('ETA_ENABLED', false),
    'base_url' => env('ETA_BASE_URL', 'https://api.invoicing.eta.gov.eg'),
    'preprod_url' => 'https://api.preprod.invoicing.eta.gov.eg',
    'identity_url' => env('ETA_IDENTITY_URL', 'https://id.eta.gov.eg'),

    'client_id' => env('ETA_CLIENT_ID'),
    'client_secret' => env('ETA_CLIENT_SECRET'),

    'issuer' => [
        'tax_number' => env('ETA_TAX_NUMBER'),
        'name' => env('ETA_ISSUER_NAME'),
        'governate' => env('ETA_GOVERNATE', 'Cairo'),
        'city' => env('ETA_CITY'),
        'street' => env('ETA_STREET'),
        'building' => env('ETA_BUILDING'),
    ],

    'activity_code' => env('ETA_ACTIVITY_CODE'),
    'vat_rate' => env('ETA_VAT_RATE', 14),

    'signing' => [
        'enabled' => env('ETA_SIGNING_ENABLED', true),
        'pkcs11_module' => env('ETA_PKCS11_MODULE'),
        'token_pin' => env('ETA_TOKEN_PIN'),
        'certificate_alias' => env('ETA_CERT_ALIAS'),
    ],
];
