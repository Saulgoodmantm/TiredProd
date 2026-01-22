<?php
/**
 * Application Configuration
 */

// Load environment variables
function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// Load .env file
loadEnv(__DIR__ . '/.env');

// Helper function
function env(string $key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Application config
return [
    'name' => env('APP_NAME', 'TiredProd'),
    'url' => env('APP_URL', 'https://tiredprod.com'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', 'false') === 'true',
    
    'gate' => [
        'password' => env('GATE_PASSWORD', '67'),
        'secret' => env('GATE_SECRET'),
    ],
    
    'admin_emails' => array_map('trim', explode(',', env('ADMIN_EMAILS', ''))),
];
