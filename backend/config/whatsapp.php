<?php

return [
    'enabled' => env('WHATSAPP_ENABLED', false),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),
    'api_version' => env('WHATSAPP_API_VERSION', 'v20.0'),
    'base_url' => 'https://graph.facebook.com',

    // Manager phone for admin alerts (international format, no +)
    'manager_phone' => env('WHATSAPP_MANAGER_PHONE', ''),

    // Thresholds for auto-alerts
    'large_invoice_threshold' => env('WHATSAPP_LARGE_INVOICE_AMOUNT', 5000),

    // Template names registered in Meta Business Manager
    'templates' => [
        'invoice' => env('WA_TEMPLATE_INVOICE', 'invoice_notification'),
        'debt_reminder' => env('WA_TEMPLATE_DEBT_REMINDER', 'debt_reminder'),
        'daily_summary' => env('WA_TEMPLATE_DAILY_SUMMARY', 'daily_sales_summary'),
        'low_stock' => env('WA_TEMPLATE_LOW_STOCK', 'low_stock_alert'),
        'large_invoice' => env('WA_TEMPLATE_LARGE_INVOICE', 'large_invoice_alert'),
        'promotion' => env('WA_TEMPLATE_PROMOTION', 'vip_promotion'),
    ],

    'language' => env('WHATSAPP_LANGUAGE', 'ar'),
];
