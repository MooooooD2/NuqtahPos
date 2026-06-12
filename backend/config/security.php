<?php

return [
    'invoice' => [
        'max_discount_percent' => (float) env('MAX_DISCOUNT_PERCENT', 20),
    ],

    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_seconds' => (int) env('LOGIN_LOCKOUT_SECONDS', 300),
    ],

    'anomaly' => [
        // Alert when a single invoice exceeds this amount
        'invoice_amount_threshold' => env('ANOMALY_INVOICE_THRESHOLD', 50000),

        // Alert when a user exceeds this many requests per minute
        'requests_per_minute' => env('ANOMALY_REQUESTS_PER_MINUTE', 100),

        // Alert after this many consecutive failed login attempts
        'failed_logins_threshold' => env('ANOMALY_FAILED_LOGINS', 10),

        // Invoices created between off_hours_start and off_hours_end (24h format) are flagged
        'off_hours_start' => (int) env('ANOMALY_OFF_HOURS_START', 22),
        'off_hours_end' => (int) env('ANOMALY_OFF_HOURS_END', 6),

        // Flag a cashier who processes more than this many returns in 24 hours
        'excessive_returns_threshold' => (int) env('ANOMALY_EXCESSIVE_RETURNS', 5),

        // Lookback window (hours) for the fraud signals API endpoint
        'signals_lookback_hours' => (int) env('ANOMALY_SIGNALS_HOURS', 24),
    ],
];
