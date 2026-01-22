<?php
/**
 * Authentication Utility
 */

class Auth {
    private const SESSION_DURATION = 30 * 24 * 60 * 60; // 30 days
    private const COOKIE_NAME = 'tiredprod_session';
    
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function user(): ?array {
        self::init();
        
        if (!isset($_SESSION['user_id'])) {
            return self::checkRememberToken();
        }
        
        return self::getUserById($_SESSION['user_id']);
    }
    
    public static function check(): bool {
        return self::user() !== null;
    }
    
    public static function isAdmin(): bool {
        $user = self::user();
        return $user && in_array($user['role'], ['admin', 'manager']);
    }
    
    public static function login(int $userId, bool $remember = false): void {
        self::init();
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_time'] = time();
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if ($remember) {
            self::createRememberToken($userId);
        }
        
        // Update last login
        Database::query(
            "UPDATE users SET last_login = NOW(), updated_at = NOW() WHERE id = ?",
            [$userId]
        );
    }
    
    public static function logout(): void {
        self::init();
        
        // Clear remember token
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            $token = $_COOKIE[self::COOKIE_NAME];
            Database::query("DELETE FROM sessions WHERE token_hash = ?", [hash('sha256', $token)]);
            setcookie(self::COOKIE_NAME, '', time() - 3600, '/', '', true, true);
        }
        
        session_destroy();
    }
    
    public static function generateOTP(): string {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // Exclude ambiguous: 0, O, I, 1, L
        $otp = '';
        for ($i = 0; $i < 6; $i++) {
            $otp .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $otp;
    }
    
    public static function hashOTP(string $otp): string {
        return password_hash($otp, PASSWORD_BCRYPT, ['cost' => 10]);
    }
    
    public static function verifyOTP(string $otp, string $hash): bool {
        return password_verify($otp, $hash);
    }
    
    private static function createRememberToken(int $userId): void {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + self::SESSION_DURATION);
        
        Database::query(
            "INSERT INTO sessions (user_id, token_hash, ip_address, user_agent, expires_at, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $hash, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $expires]
        );
        
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + self::SESSION_DURATION,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    private static function checkRememberToken(): ?array {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }
        
        $token = $_COOKIE[self::COOKIE_NAME];
        $hash = hash('sha256', $token);
        
        $session = Database::fetch(
            "SELECT user_id FROM sessions WHERE token_hash = ? AND expires_at > NOW()",
            [$hash]
        );
        
        if ($session) {
            $_SESSION['user_id'] = $session['user_id'];
            return self::getUserById($session['user_id']);
        }
        
        return null;
    }
    
    private static function getUserById(int $id): ?array {
        return Database::fetch("SELECT * FROM users WHERE id = ? AND is_active = true", [$id]);
    }
}
