<?php
/**
 * Gate Middleware - "67" password protection
 */

class GateMiddleware {
    private const COOKIE_NAME = 'tiredprod_gate';
    private const COOKIE_DURATION = 30 * 24 * 60 * 60; // 30 days
    
    public static function check(): bool {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }
        
        $secret = env('GATE_SECRET', '');
        $expected = hash_hmac('sha256', env('GATE_PASSWORD', '67'), $secret);
        
        return hash_equals($expected, $_COOKIE[self::COOKIE_NAME]);
    }
    
    public static function verify(string $password): bool {
        $correct = env('GATE_PASSWORD', '67');
        
        if (!hash_equals($correct, $password)) {
            return false;
        }
        
        // Set signed cookie
        $secret = env('GATE_SECRET', '');
        $signature = hash_hmac('sha256', $password, $secret);
        
        setcookie(self::COOKIE_NAME, $signature, [
            'expires' => time() + self::COOKIE_DURATION,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        return true;
    }
    
    public static function clear(): void {
        setcookie(self::COOKIE_NAME, '', time() - 3600, '/');
    }
}
