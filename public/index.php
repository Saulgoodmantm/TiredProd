<?php
/**
 * TiredProd - Main Entry Point
 */

// Load config
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Utils/Database.php';
require_once __DIR__ . '/../app/Utils/Auth.php';
require_once __DIR__ . '/../app/Middleware/GateMiddleware.php';

// Start session
session_start();

// Get request path
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestUri = rtrim($requestUri, '/') ?: '/';

// Handle API requests
if (strpos($requestUri, '/api/') === 0) {
    header('Content-Type: application/json');
    
    // Gate verification API
    if ($requestUri === '/api/gate/verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $password = $input['password'] ?? '';
        
        if (GateMiddleware::verify($password)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid password']);
        }
        exit;
    }
    
    // Auth - Request OTP
    if ($requestUri === '/api/auth/request-otp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        
        if (!$email) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid email']);
            exit;
        }
        
        // Check if user exists
        $user = Database::fetch("SELECT id, username FROM users WHERE email = ?", [$email]);
        $isNewUser = !$user;
        
        // Generate OTP
        $otp = Auth::generateOTP();
        $hash = Auth::hashOTP($otp);
        $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes
        
        // Delete old OTPs for this email
        Database::query("DELETE FROM otp_codes WHERE email = ?", [$email]);
        
        // Store new OTP
        Database::query(
            "INSERT INTO otp_codes (email, code_hash, ip_address, expires_at) VALUES (?, ?, ?, ?)",
            [$email, $hash, $_SERVER['REMOTE_ADDR'] ?? '', $expires]
        );
        
        // In production, send email here. For now, log it.
        error_log("OTP for $email: $otp");
        
        // TODO: Send email via Gmail API
        // For development, we'll show it (remove in production!)
        echo json_encode([
            'success' => true,
            'isNewUser' => $isNewUser,
            'message' => 'OTP sent to your email',
            // REMOVE IN PRODUCTION:
            'debug_otp' => env('APP_DEBUG') === 'true' ? $otp : null
        ]);
        exit;
    }
    
    // Auth - Verify OTP
    if ($requestUri === '/api/auth/verify-otp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $otp = strtoupper(trim($input['otp'] ?? ''));
        $username = $input['username'] ?? null;
        $remember = $input['remember'] ?? false;
        
        if (!$email || !$otp) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing email or OTP']);
            exit;
        }
        
        // Get OTP record
        $otpRecord = Database::fetch(
            "SELECT * FROM otp_codes WHERE email = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
            [$email]
        );
        
        if (!$otpRecord) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'OTP expired or not found']);
            exit;
        }
        
        if ($otpRecord['attempts'] >= 5) {
            http_response_code(429);
            echo json_encode(['success' => false, 'error' => 'Too many attempts']);
            exit;
        }
        
        // Increment attempts
        Database::query("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?", [$otpRecord['id']]);
        
        // Verify OTP
        if (!Auth::verifyOTP($otp, $otpRecord['code_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid OTP']);
            exit;
        }
        
        // Delete used OTP
        Database::query("DELETE FROM otp_codes WHERE id = ?", [$otpRecord['id']]);
        
        // Find or create user
        $user = Database::fetch("SELECT * FROM users WHERE email = ?", [$email]);
        
        if (!$user) {
            // New user - create account
            $adminEmails = array_map('trim', explode(',', env('ADMIN_EMAILS', '')));
            $role = in_array($email, $adminEmails) ? 'admin' : 'registered';
            
            $userId = Database::insert('users', [
                'email' => $email,
                'username' => $username ?: 'user_' . substr(uniqid(), -6),
                'role' => $role,
                'email_verified' => true
            ]);
        } else {
            $userId = $user['id'];
            // Mark email as verified if not already
            if (!$user['email_verified']) {
                Database::query("UPDATE users SET email_verified = true WHERE id = ?", [$userId]);
            }
        }
        
        // Login
        Auth::login($userId, $remember);
        
        echo json_encode(['success' => true, 'redirect' => '/']);
        exit;
    }
    
    // Auth - Logout
    if ($requestUri === '/api/auth/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        Auth::logout();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Auth - Check session
    if ($requestUri === '/api/auth/session') {
        $user = Auth::user();
        if ($user) {
            unset($user['google_id'], $user['stripe_customer_id']);
            echo json_encode(['authenticated' => true, 'user' => $user]);
        } else {
            echo json_encode(['authenticated' => false]);
        }
        exit;
    }
    
    // 404 for unknown API routes
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// Check gate for non-API requests
$gateRequired = true; // Set to false to disable gate
$gateBypass = GateMiddleware::check();

// Get current user
$user = Auth::user();
$isAdmin = Auth::isAdmin();

// Page routing
$page = match($requestUri) {
    '/' => 'home',
    '/gallery' => 'gallery',
    '/gallery/personal' => 'gallery',
    '/gallery/product' => 'gallery',
    '/gallery/group' => 'gallery',
    '/gallery/event' => 'gallery',
    '/gallery/misc' => 'gallery',
    '/rates' => 'rates',
    '/booking' => 'booking',
    '/contact' => 'contact',
    '/calendar' => 'calendar',
    '/login' => 'login',
    '/profile' => 'profile',
    '/dashboard' => 'dashboard',
    default => '404'
};

// Require auth for certain pages
$authRequired = ['profile', 'dashboard'];
if (in_array($page, $authRequired) && !$user) {
    header('Location: /login');
    exit;
}

// Require admin for dashboard
if ($page === 'dashboard' && !$isAdmin) {
    header('Location: /');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(env('APP_NAME', 'TiredProd')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <?php if ($gateRequired && !$gateBypass): ?>
        <!-- Gate Overlay -->
        <div id="gate-overlay" class="gate-overlay">
            <div class="gate-blur"></div>
            <div class="gate-panel">
                <div class="gate-logo">
                    <span class="brand-name">TIREDPROD</span>
                </div>
                <form id="gate-form" class="gate-form">
                    <input type="password" id="gate-input" class="gate-input" placeholder="Enter code" autocomplete="off" maxlength="10">
                    <button type="submit" class="gate-submit">Enter</button>
                </form>
                <p class="gate-hint">Private access only</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="nav">
        <a href="/" class="nav-brand">
            <span class="brand-name">TIREDPROD</span>
        </a>
        <button class="nav-toggle" id="nav-toggle" aria-label="Toggle menu">
            <span class="hamburger"></span>
        </button>
    </nav>

    <!-- Side Menu -->
    <div class="menu-overlay" id="menu-overlay">
        <div class="menu-content">
            <div class="menu-header">
                <?php if ($user): ?>
                    <div class="menu-user">
                        <div class="menu-avatar">
                            <?php if ($user['avatar_url']): ?>
                                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Avatar">
                            <?php else: ?>
                                <span><?= strtoupper(substr($user['username'] ?? $user['email'], 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="menu-user-info">
                            <span class="menu-username"><?= htmlspecialchars($user['username'] ?? 'User') ?></span>
                            <span class="menu-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <nav class="menu-nav">
                <div class="menu-section">
                    <button class="menu-item menu-expandable" data-expand="gallery">
                        <span>Gallery</span>
                        <svg class="menu-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                    <div class="menu-submenu" id="submenu-gallery">
                        <a href="/gallery/personal" class="menu-subitem">Personal</a>
                        <a href="/gallery/product" class="menu-subitem">Product</a>
                        <a href="/gallery/group" class="menu-subitem">Group</a>
                        <a href="/gallery/event" class="menu-subitem">Event</a>
                        <a href="/gallery/misc" class="menu-subitem">Misc</a>
                    </div>
                </div>
                
                <div class="menu-section">
                    <button class="menu-item menu-expandable" data-expand="socials">
                        <span>Socials</span>
                        <svg class="menu-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                    <div class="menu-submenu" id="submenu-socials">
                        <a href="https://www.instagram.com/tiredofdointm/" target="_blank" class="menu-subitem">
                            Instagram @tiredofdointm
                        </a>
                        <a href="https://www.instagram.com/tiredflics/" target="_blank" class="menu-subitem">
                            Instagram @tiredflics
                        </a>
                        <a href="https://www.tiktok.com/@tiredofdointm" target="_blank" class="menu-subitem">
                            TikTok @tiredofdointm
                        </a>
                    </div>
                </div>
                
                <a href="/rates" class="menu-item">Rates</a>
                <a href="/booking" class="menu-item menu-highlight">Book Now</a>
                <a href="/contact" class="menu-item">Contact</a>
                <a href="/calendar" class="menu-item">Calendar</a>
                
                <?php if ($user): ?>
                    <div class="menu-divider"></div>
                    <a href="/profile" class="menu-item">Profile</a>
                    <?php if ($isAdmin): ?>
                        <a href="/dashboard" class="menu-item">Dashboard</a>
                    <?php endif; ?>
                    <button class="menu-item menu-logout" id="logout-btn">Sign Out</button>
                <?php else: ?>
                    <div class="menu-divider"></div>
                    <a href="/login" class="menu-item">Sign In</a>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <main class="main">
        <?php if ($page === 'home'): ?>
            <!-- Homepage -->
            <section class="hero">
                <div class="hero-profile">
                    <div class="profile-avatar">
                        <img src="https://instagram.fcps3-1.fna.fbcdn.net/v/t51.2885-19/612483021_17897242272370559_4688585427133632962_n.jpg" alt="TiredOfDoinTM" loading="lazy">
                    </div>
                    <h1 class="profile-name">TIREDOFDOINTM</h1>
                    <p class="profile-subtitle">PHOTOGRAPHER</p>
                    <a href="https://www.instagram.com/tiredofdointm/" target="_blank" class="profile-instagram">
                        @tiredofdointm
                    </a>
                </div>
                
                <div class="hero-buttons">
                    <a href="/calendar" class="btn btn-secondary">View Schedule</a>
                    <a href="/gallery" class="btn btn-primary">View Portfolio</a>
                    <a href="/booking" class="btn btn-secondary">Book Now</a>
                </div>
                
                <div class="hero-slideshow" id="slideshow">
                    <div class="slideshow-track">
                        <!-- Images loaded via JS -->
                    </div>
                    <button class="slideshow-arrow slideshow-prev" aria-label="Previous">
                        <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <button class="slideshow-arrow slideshow-next" aria-label="Next">
                        <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                    <div class="slideshow-dots" id="slideshow-dots"></div>
                </div>
            </section>
            
        <?php elseif ($page === 'login'): ?>
            <!-- Login Page -->
            <section class="auth-page">
                <div class="auth-container">
                    <h1 class="auth-title">Sign In</h1>
                    <p class="auth-subtitle">Enter your email to receive a verification code</p>
                    
                    <form id="auth-form" class="auth-form">
                        <div class="form-step" id="step-email">
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required placeholder="you@example.com">
                            </div>
                            <button type="submit" class="btn btn-primary btn-full">Continue</button>
                        </div>
                        
                        <div class="form-step hidden" id="step-otp">
                            <div class="form-group">
                                <label for="otp">Verification Code</label>
                                <input type="text" id="otp" name="otp" required placeholder="ABC123" maxlength="6" class="otp-input">
                                <p class="form-hint">Check your email for the 6-character code</p>
                            </div>
                            <div class="form-group hidden" id="username-group">
                                <label for="username">Choose a Username</label>
                                <input type="text" id="username" name="username" placeholder="username">
                            </div>
                            <div class="form-group form-checkbox">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Keep me signed in</label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-full">Sign In</button>
                        </div>
                    </form>
                    
                    <div class="auth-divider">
                        <span>or</span>
                    </div>
                    
                    <button class="btn btn-google" id="google-signin">
                        <svg viewBox="0 0 24 24" class="google-icon">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Sign in with Google
                    </button>
                </div>
            </section>
            
        <?php elseif ($page === 'gallery'): ?>
            <!-- Gallery Page -->
            <section class="gallery-page">
                <h1 class="page-title">Portfolio</h1>
                <div class="gallery-filters">
                    <a href="/gallery" class="filter-btn <?= $requestUri === '/gallery' ? 'active' : '' ?>">All</a>
                    <a href="/gallery/personal" class="filter-btn <?= $requestUri === '/gallery/personal' ? 'active' : '' ?>">Personal</a>
                    <a href="/gallery/product" class="filter-btn <?= $requestUri === '/gallery/product' ? 'active' : '' ?>">Product</a>
                    <a href="/gallery/group" class="filter-btn <?= $requestUri === '/gallery/group' ? 'active' : '' ?>">Group</a>
                    <a href="/gallery/event" class="filter-btn <?= $requestUri === '/gallery/event' ? 'active' : '' ?>">Event</a>
                    <a href="/gallery/misc" class="filter-btn <?= $requestUri === '/gallery/misc' ? 'active' : '' ?>">Misc</a>
                </div>
                <div class="gallery-grid" id="gallery-grid">
                    <p class="gallery-empty">No images yet. Check back soon!</p>
                </div>
            </section>
            
        <?php elseif ($page === 'rates'): ?>
            <!-- Rates Page -->
            <section class="rates-page">
                <h1 class="page-title">Rates</h1>
                <div class="rates-grid">
                    <div class="rate-card">
                        <h3>2 Hours</h3>
                        <div class="rate-price">$200</div>
                        <ul class="rate-features">
                            <li>50+ Edited Photos</li>
                            <li>Single Location</li>
                            <li>Full Retouching</li>
                            <li>Online Gallery</li>
                        </ul>
                        <a href="/booking?duration=2" class="btn btn-primary">Book Now</a>
                    </div>
                    <div class="rate-card featured">
                        <div class="rate-badge">Most Popular</div>
                        <h3>4 Hours</h3>
                        <div class="rate-price">$375</div>
                        <ul class="rate-features">
                            <li>125+ Edited Photos</li>
                            <li>1-2 Locations</li>
                            <li>Full Retouching</li>
                            <li>Online Gallery</li>
                        </ul>
                        <a href="/booking?duration=4" class="btn btn-primary">Book Now</a>
                    </div>
                    <div class="rate-card">
                        <h3>6 Hours</h3>
                        <div class="rate-price">$525</div>
                        <ul class="rate-features">
                            <li>275+ Edited Photos</li>
                            <li>Unlimited Locations</li>
                            <li>Full Retouching</li>
                            <li>Consultation</li>
                            <li>Online Gallery</li>
                        </ul>
                        <a href="/booking?duration=6" class="btn btn-primary">Book Now</a>
                    </div>
                </div>
            </section>
            
        <?php elseif ($page === 'booking'): ?>
            <!-- Booking Page -->
            <section class="booking-page">
                <h1 class="page-title">Book a Session</h1>
                <div class="booking-container">
                    <div class="booking-calendar" id="booking-calendar">
                        <!-- Calendar rendered via JS -->
                    </div>
                    <div class="booking-form hidden" id="booking-form">
                        <h2>Session Details</h2>
                        <form id="booking-details-form">
                            <!-- Form steps populated via JS -->
                        </form>
                    </div>
                </div>
            </section>
            
        <?php elseif ($page === 'contact'): ?>
            <!-- Contact Page -->
            <section class="contact-page">
                <h1 class="page-title">Get in Touch</h1>
                <div class="contact-grid">
                    <div class="contact-info">
                        <div class="contact-item">
                            <h3>Email</h3>
                            <a href="mailto:contact@tiredofdointm.com">contact@tiredofdointm.com</a>
                        </div>
                        <div class="contact-item">
                            <h3>Instagram</h3>
                            <a href="https://www.instagram.com/tiredofdointm/" target="_blank">@tiredofdointm</a>
                            <a href="https://www.instagram.com/tiredflics/" target="_blank">@tiredflics</a>
                        </div>
                        <div class="contact-item">
                            <h3>TikTok</h3>
                            <a href="https://www.tiktok.com/@tiredofdointm" target="_blank">@tiredofdointm</a>
                        </div>
                    </div>
                </div>
            </section>
            
        <?php elseif ($page === 'calendar'): ?>
            <!-- Calendar Page -->
            <section class="calendar-page">
                <h1 class="page-title">Availability</h1>
                <div class="calendar-container" id="availability-calendar">
                    <!-- Calendar rendered via JS -->
                </div>
            </section>
            
        <?php elseif ($page === 'dashboard'): ?>
            <!-- Admin Dashboard -->
            <section class="dashboard">
                <aside class="dashboard-sidebar" id="dashboard-sidebar">
                    <nav class="dashboard-nav">
                        <a href="#overview" class="dash-nav-item active" data-section="overview">Overview</a>
                        <a href="#calendar" class="dash-nav-item" data-section="calendar">Calendar</a>
                        <a href="#bookings" class="dash-nav-item" data-section="bookings">Bookings</a>
                        <a href="#clients" class="dash-nav-item" data-section="clients">Clients</a>
                        <a href="#messages" class="dash-nav-item" data-section="messages">Messages</a>
                        <a href="#contracts" class="dash-nav-item" data-section="contracts">Contracts</a>
                        <a href="#gallery" class="dash-nav-item" data-section="gallery">Gallery</a>
                        <a href="#billing" class="dash-nav-item" data-section="billing">Billing</a>
                        <a href="#pricing" class="dash-nav-item" data-section="pricing">Manage Prices</a>
                        <a href="#analytics" class="dash-nav-item" data-section="analytics">Analytics</a>
                        <a href="#settings" class="dash-nav-item" data-section="settings">Settings</a>
                    </nav>
                </aside>
                <main class="dashboard-main">
                    <div class="dash-section active" id="section-overview">
                        <h2>Dashboard Overview</h2>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <span class="stat-value" data-count="0">0</span>
                                <span class="stat-label">Bookings This Month</span>
                            </div>
                            <div class="stat-card">
                                <span class="stat-value" data-count="0">$0</span>
                                <span class="stat-label">Revenue This Month</span>
                            </div>
                            <div class="stat-card">
                                <span class="stat-value" data-count="0">0</span>
                                <span class="stat-label">Website Views</span>
                            </div>
                            <div class="stat-card">
                                <span class="stat-value" data-count="0">0</span>
                                <span class="stat-label">Available Days</span>
                            </div>
                        </div>
                    </div>
                    <!-- Other sections loaded via JS -->
                </main>
            </section>
            
        <?php else: ?>
            <!-- 404 Page -->
            <section class="error-page">
                <h1>404</h1>
                <p>Page not found</p>
                <a href="/" class="btn btn-primary">Go Home</a>
            </section>
        <?php endif; ?>
    </main>

    <script src="/assets/js/app.js"></script>
</body>
</html>
