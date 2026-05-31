<?php

/**
 * Phase 10 — HR Module Configuration
 *
 * Set these in .env to override per deployment.
 */
return [
    'social_insurance' => [
        // Egypt: employee pays 11%, employer pays 18.75% on base salary
        'employee_rate' => (float) env('HR_SI_EMPLOYEE_RATE', 0.11),
        'employer_rate' => (float) env('HR_SI_EMPLOYER_RATE', 0.1875),
        'max_base' => (float) env('HR_SI_MAX_BASE', 10_000),   // cap on taxable base (EGP)
    ],

    'overtime' => [
        'default_multiplier' => (float) env('HR_OT_MULTIPLIER', 1.5),
        'max_daily_hours' => (int) env('HR_OT_MAX_DAILY', 12),
    ],

    'attendance' => [
        'grace_minutes' => (int) env('HR_ATTENDANCE_GRACE', 10),   // forgiveness window for late
        'deduct_method' => env('HR_LATE_DEDUCT_METHOD', 'per_minute'),  // per_minute | half_day | full_day
    ],

    'leave' => [
        'annual_days' => (int) env('HR_ANNUAL_LEAVE_DAYS', 21),
        'sick_days' => (int) env('HR_SICK_LEAVE_DAYS', 10),
        'carry_over_max' => (int) env('HR_LEAVE_CARRY_OVER', 5),
    ],

    'payroll' => [
        'currency' => env('HR_PAYROLL_CURRENCY', 'EGP'),
        'income_tax_country' => env('HR_TAX_COUNTRY', 'EG'),   // EG = Egypt, SA = Saudi Arabia
    ],
];
