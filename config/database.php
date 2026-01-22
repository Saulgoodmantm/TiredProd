<?php
/**
 * Database Configuration
 */

return [
    'connection' => env('DB_CONNECTION', 'pgsql'),
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT', '25060'),
    'database' => env('DB_NAME', 'defaultdb'),
    'username' => env('DB_USER'),
    'password' => env('DB_PASS'),
    'ssl' => env('DB_SSL', 'require'),
];
