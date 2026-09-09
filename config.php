<?php
/**
 * config.php — Central Configuration
 * ─────────────────────────────────────
 * All database credentials and external API URLs are defined here.
 * On InfinityFree: replace LOCAL_* values with your InfinityFree panel credentials.
 * On XAMPP: the defaults below will work as-is.
 *
 * INCLUDE THIS FILE at the top of every PHP file that needs DB or API access:
 *   require_once __DIR__ . '/config.php';   (from root)
 *   require_once dirname(__DIR__) . '/config.php';  (from admin/ subfolder)
 */

// ─────────────────────────────────────────
// Environment Detection
// ─────────────────────────────────────────
$is_production = isset($_ENV['INFINITYFREE']) || (
    isset($_SERVER['HTTP_HOST']) &&
    !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) &&
    !preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|localhost|127\.)/', $_SERVER['HTTP_HOST'])
);

// ─────────────────────────────────────────
// Database Configuration
// ─────────────────────────────────────────
if ($is_production) {
    // ── InfinityFree Production Settings ──
    // Replace these with values from your InfinityFree control panel:
    // cPanel → MySQL Databases → your DB details
    define('DB_HOST',     'sql###.epizy.com');     // e.g. sql200.epizy.com
    define('DB_USER',     'epiz_########');         // e.g. epiz_12345678
    define('DB_PASS',     'YOUR_DB_PASSWORD');
    define('DB_NAME',     'epiz_########_enrollment_db');
} else {
    // ── Local XAMPP Development Settings ──
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'enrollment_db');
}

// ─────────────────────────────────────────
// AI API URL (Flask on Render.com)
// ─────────────────────────────────────────
// Replace this with your actual Render URL after deploying ai_api.py
// Example: https://scnhs-dropout-api.onrender.com
define('AI_API_URL', 'https://YOUR-APP-NAME.onrender.com');

// ─────────────────────────────────────────
// Database Connection Helper
// ─────────────────────────────────────────
function get_db_connection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ─────────────────────────────────────────
// Secure Session Settings (Must run before session_start)
// ─────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    // ini_set('session.cookie_secure', 1); // Uncomment when HTTPS is active
}

// ─────────────────────────────────────────
// Site Settings
// ─────────────────────────────────────────
define('SITE_NAME',   'Santa Cruz National High School SHS');
define('SITE_EMAIL',  'scnhs@example.com');
define('MAX_SECTION_STUDENTS', 30);

// ─────────────────────────────────────────
// Security & Audit Helpers
// ─────────────────────────────────────────

/**
 * Generate a CSRF token for the current session.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token from a POST request.
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log an action to the audit trail.
 */
function log_audit($conn, $user_id, $role, $action) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, role, action, ip_address) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $user_id, $role, $action, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
}
