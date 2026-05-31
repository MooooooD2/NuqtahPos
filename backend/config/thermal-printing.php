<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Thermal Printing Defaults
    |--------------------------------------------------------------------------
    */

    'enabled' => env('THERMAL_PRINTING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Paper Widths → Characters Per Line
    |--------------------------------------------------------------------------
    */

    'paper_widths' => [
        '58' => 32,
        '80' => 48,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Printer Settings
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'paper_width' => env('PRINTER_PAPER_WIDTH', '80'),
        'character_set' => env('PRINTER_CHARACTER_SET', 'CP720'),
        'copies' => env('PRINTER_COPIES', 1),
        'auto_cut' => env('PRINTER_AUTO_CUT', true),
        'auto_open_drawer' => env('PRINTER_AUTO_OPEN_DRAWER', false),
        'port' => env('PRINTER_PORT', 9100),
        'timeout' => env('PRINTER_TIMEOUT', 5),   // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Print Queue
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'max_attempts' => env('PRINT_MAX_ATTEMPTS', 3),
        'batch_size' => env('PRINT_BATCH_SIZE', 10),
        'stuck_threshold_min' => env('PRINT_STUCK_THRESHOLD', 5), // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt Options
    |--------------------------------------------------------------------------
    */

    'receipt' => [
        'show_qr' => env('RECEIPT_SHOW_QR', true),
        'show_barcode' => env('RECEIPT_SHOW_BARCODE', false),
        'template' => env('RECEIPT_TEMPLATE', 'default'),
        'footer' => env('RECEIPT_FOOTER', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Character Sets
    |--------------------------------------------------------------------------
    */

    'character_sets' => [
        'CP437' => ['code_page' => 0,  'label' => 'CP437 (US English)'],
        'CP720' => ['code_page' => 12, 'label' => 'CP720 (Arabic)'],
        'UTF-8' => ['code_page' => 255, 'label' => 'UTF-8'],
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code ETA Format
    | Format: seller|vat|date|total|tax
    |--------------------------------------------------------------------------
    */

    'eta_qr' => [
        'enabled' => env('ETA_QR_ENABLED', true),
    ],

];
