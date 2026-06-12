<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:4173',
        'http://localhost:8080',
        'tauri://localhost',        // macOS / Linux Tauri v2 (WebKit)
        'http://tauri.localhost',   // Windows Tauri v2 (WebView2) — the actual origin sent by the runtime
        'https://tauri.localhost',  // kept for compatibility
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
