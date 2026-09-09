<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $role = $_POST['role'] ?? 'student';
    $password = $_POST['password'] ?? '';
    
    // Determine the username field based on role (lrn for student, employee_id for teacher)
    if ($role === 'student') {
        $username = trim(htmlspecialchars($_POST['lrn'] ?? '', ENT_QUOTES, 'UTF-8'));
    } else {
        $username = trim(htmlspecialchars($_POST['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'));
    }

    if (empty($username) || empty($password)) {
        header("Location: login.html?error=" . urlencode("Please fill in all fields.") . "&role=" . urlencode($role));
        exit;
    }

    // Rate limiting: max 5 login attempts per 15 minutes (session-based)
    if (!isset($_SESSION['st_login_attempts'])) { $_SESSION['st_login_attempts'] = 0; $_SESSION['st_first_attempt'] = time(); }
    if ($_SESSION['st_login_attempts'] >= 5 && (time() - $_SESSION['st_first_attempt']) < 900) {
        header("Location: login.html?error=" . urlencode("Too many failed attempts. Try again in 15 minutes.") . "&role=" . urlencode($role));
        exit;
    }
    if ((time() - $_SESSION['st_first_attempt']) >= 900) { $_SESSION['st_login_attempts'] = 0; $_SESSION['st_first_attempt'] = time(); }

    if ($role === 'student') {
        $stmt = $conn->prepare("SELECT id, lrn, first_name, last_name, password, status FROM students WHERE lrn = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($student = $result->fetch_assoc()) {
            if (password_verify($password, $student['password'])) {
                if ($student['status'] === 'pending') {
                    header("Location: login.html?error=" . urlencode("Your enrollment is currently under review by an administrator.") . "&role=" . urlencode($role));
                    exit;
                } elseif ($student['status'] === 'rejected') {
                    header("Location: login.html?error=" . urlencode("Your enrollment was rejected. Please contact the administrator.") . "&role=" . urlencode($role));
                    exit;
                }

                $_SESSION['st_login_attempts'] = 0;
                session_regenerate_id(true);
                $_SESSION['student_id']   = $student['id'];
                $_SESSION['student_lrn']  = $student['lrn'];
                $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                $_SESSION['login_time']   = time();
                
                log_audit($conn, $student['lrn'], 'student', 'Successful login');
                $stmt->close();
                $conn->close();
                header("Location: dashboard.php");
                exit;
            }
        }
    } elseif ($role === 'teacher') {
        $stmt = $conn->prepare("SELECT id, employee_id, first_name, last_name, department, password FROM teachers WHERE employee_id = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($teacher = $result->fetch_assoc()) {
            if (password_verify($password, $teacher['password'])) {
                $_SESSION['st_login_attempts'] = 0;
                session_regenerate_id(true);
                $_SESSION['teacher_id']   = $teacher['id'];
                $_SESSION['employee_id']  = $teacher['employee_id'];
                $_SESSION['teacher_name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
                $_SESSION['department']   = $teacher['department'];
                $_SESSION['login_time']   = time();
                
                log_audit($conn, $teacher['employee_id'], 'teacher', 'Successful login');
                $stmt->close();
                $conn->close();
                header("Location: teacher_dashboard.php");
                exit;
            }
        }
    }

    // Generic error message — don't reveal if username exists
    $_SESSION['st_login_attempts']++;
    log_audit($conn, $username, $role, 'Failed login attempt');
    header("Location: login.html?error=" . urlencode("Invalid credentials.") . "&role=" . urlencode($role));
    exit;
}

$conn->close();
header("Location: login.html");
exit();
?>
