<?php
// Session security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.cookie_samesite', 'Lax');

session_start();

// Application settings
define('APP_NAME', 'StudyBud');
define('APP_URL', 'http://localhost/StudyBud/studybud_copy/firsecopy');
define('UPLOAD_DIR', __DIR__ . '/../uploads');

// Security settings
define('CSRF_TOKEN_SECRET', bin2hex(random_bytes(32)));
define('PASSWORD_PEPPER', 'studybud_static_pepper_value_for_password_hashing');
define('SESSION_LIFETIME', 3600); // 1 hour

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to sanitize output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
