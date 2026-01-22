<?php
/**
 * Database Configuration
 */

// Ensure env() function is available
if (!function_exists('env')) {
    require_once __DIR__ . '/app.php';
}

return [
    'connection' => env('DB_CONNECTION', 'pgsql'),
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT', '25060'),
    'database' => env('DB_NAME', 'defaultdb'),
    'username' => env('DB_USER'),
    'password' => env('DB_PASS'),
    'ssl' => env('DB_SSL', 'require'),
];
