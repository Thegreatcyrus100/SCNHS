<?php
/**
 * auth_guard.php — Centralized Authentication & Security Checks
 * Include this at the very top of protected pages (after config.php and session_start).
 */

function enforce_auth($required_role = null) {
    // 1. Check if user is logged in at all
    $is_superadmin = isset($_SESSION['superadmin']);
    $is_admin = isset($_SESSION['admin']);
    $is_teacher = isset($_SESSION['teacher_id']);
    $is_student = isset($_SESSION['student_id']);

    if (!$is_superadmin && !$is_admin && !$is_teacher && !$is_student) {
        header("Location: /Final/student/login.html?error=" . urlencode("Please log in to continue."));
        exit();
    }

    // 2. Enforce 2-hour session timeout for all users
    $timeout_duration = 7200; // 2 hours in seconds
    if (isset($_SESSION['login_time'])) {
        if ((time() - $_SESSION['login_time']) > $timeout_duration) {
            session_unset();
            session_destroy();
            header("Location: /Final/student/login.html?error=" . urlencode("Session expired. Please log in again."));
            exit();
        }
        // Update login_time to extend session on activity (optional, commenting out to force strict 2-hour absolute timeout)
        // $_SESSION['login_time'] = time(); 
    } else {
        // If login_time is missing but they are authenticated, set it now
        $_SESSION['login_time'] = time();
    }

    // 3. Role-Based Access Control (RBAC)
    if ($required_role !== null) {
        $has_access = false;
        
        switch ($required_role) {
            case 'superadmin':
                $has_access = $is_superadmin;
                break;
            case 'admin':
                // Superadmins can often do admin tasks, but let's keep it strict or allow both
                $has_access = $is_admin || $is_superadmin;
                break;
            case 'teacher':
                $has_access = $is_teacher;
                break;
            case 'student':
                $has_access = $is_student;
                break;
        }

        if (!$has_access) {
            http_response_code(403);
            die("403 Forbidden: You do not have permission to access this page.");
        }
    }
}

/**
 * Enforce CSRF token check on POST requests
 */
function enforce_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            http_response_code(403);
            die("403 Forbidden: Invalid CSRF token. Your session may have expired or this request was forged.");
        }
    }
}

// Automatically enforce CSRF protection on all POST requests for files including this guard
enforce_csrf();
?>
