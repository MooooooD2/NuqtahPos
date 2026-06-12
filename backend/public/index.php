<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// CORS for Tauri desktop app — runs before Laravel so it works regardless of config cache
(static function () {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = ['http://tauri.localhost', 'https://tauri.localhost', 'tauri://localhost'];
    if (!in_array($origin, $allowed, true)) {
        return;
    }
    header("Access-Control-Allow-Origin: {$origin}", true);
    header('Access-Control-Allow-Credentials: true', true);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS', true);
    header('Access-Control-Allow-Headers: Accept, Authorization, Content-Type, X-Requested-With, X-Tenant-Code', true);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
})();

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__ . '/../bootstrap/app.php')
    ->handleRequest(Request::capture());
