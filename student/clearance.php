<?php
/**
 * School Clearance — Student Portal
 * Shows student clearance status across all departments.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.html");
    exit();
}

$conn = get_db_connection();
$student_id = $_SESSION['student_id'];

function e(?string $v): string { return htmlspecialchars($v ?? '—', ENT_QUOTES, 'UTF-8'); }

// Fetch student
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { session_destroy(); header("Location: login.html"); exit(); }

// Auto-create clearances table
$conn->query("CREATE TABLE IF NOT EXISTS clearances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    adviser_status ENUM('pending','cleared') DEFAULT 'pending',
    library_status ENUM('pending','cleared') DEFAULT 'pending',
    cashier_status ENUM('pending','cleared') DEFAULT 'pending',
    property_status ENUM('pending','cleared') DEFAULT 'pending',
    clinic_status ENUM('pending','cleared') DEFAULT 'pending',
    guidance_status ENUM('pending','cleared') DEFAULT 'pending',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Insert row if not exists
$chk = $conn->prepare("SELECT id FROM clearances WHERE student_id = ?");
$chk->bind_param("i", $student_id);
$chk->execute();
if ($chk->get_result()->num_rows === 0) {
    $ins = $conn->prepare("INSERT INTO clearances (student_id) VALUES (?)");
    $ins->bind_param("i", $student_id);
    $ins->execute();
    $ins->close();
}
$chk->close();

// Fetch clearance
$cl = $conn->prepare("SELECT * FROM clearances WHERE student_id = ? LIMIT 1");
$cl->bind_param("i", $student_id);
$cl->execute();
$clearance = $cl->get_result()->fetch_assoc() ?: [];
$cl->close();

$conn->close();

$departments = [
    'adviser_status'  => ['name' => 'Class Adviser',      'icon' => 'fa-chalkboard-teacher', 'color' => '#6366f1'],
    'library_status'  => ['name' => 'School Library',     'icon' => 'fa-book',               'color' => '#3b82f6'],
    'cashier_status'  => ['name' => 'Cashier / Finance',  'icon' => 'fa-coins',              'color' => '#f59e0b'],
    'property_status' => ['name' => 'Property Custodian', 'icon' => 'fa-boxes',              'color' => '#8b5cf6'],
    'clinic_status'   => ['name' => 'School Clinic',      'icon' => 'fa-heartbeat',          'color' => '#ef4444'],
    'guidance_status' => ['name' => 'Guidance Office',    'icon' => 'fa-hands-helping',      'color' => '#06b6d4'],
];

$allCleared = true;
foreach ($departments as $key => $dept) {
    if (($clearance[$key] ?? 'pending') !== 'cleared') { $allCleared = false; break; }
}
$clearedCount = array_sum(array_map(fn($k) => ($clearance[$k] ?? 'pending') === 'cleared' ? 1 : 0, array_keys($departments)));
$totalDepts   = count($departments);
$progress     = round(($clearedCount / $totalDepts) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Clearance | SCNHS Student</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css">
<style>
    .page-hero {
        background: linear-gradient(135deg, <?php echo $allCleared ? 'rgba(6,182,212,0.18)' : 'rgba(245,158,11,0.12)'; ?> 0%, transparent 100%);
        border: 1px solid <?php echo $allCleared ? 'rgba(6,182,212,0.3)' : 'rgba(245,158,11,0.2)'; ?>;
        border-radius: 20px; padding: 32px; margin-bottom: 32px;
    }
    .hero-top { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; }
    .hero-top h1 { font-size: 1.5rem; font-weight: 700; }
    .hero-top p { color: var(--text-muted); margin-top: 4px; }
    .clearance-badge {
        padding: 12px 28px; border-radius: 30px; font-weight: 700; font-size: 1rem;
        display: inline-flex; align-items: center; gap: 10px;
        <?php echo $allCleared
            ? 'background: rgba(6,182,212,0.2); border: 2px solid #06b6d4; color: #06b6d4;'
            : 'background: rgba(245,158,11,0.15); border: 2px solid #f59e0b; color: #f59e0b;'; ?>
    }
    .progress-wrap { margin-top: 24px; }
    .progress-label { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; color: var(--text-muted); }
    .progress-bar-bg { background: rgba(255,255,255,0.06); border-radius: 100px; height: 12px; overflow: hidden; }
    .progress-bar-fill {
        height: 100%; border-radius: 100px; transition: width 1s ease;
    }

    .depts-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;
        margin-bottom: 32px;
    }
    .dept-card {
        background: var(--bg-panel); border: 1px solid var(--border); border-radius: 18px;
        padding: 24px; display: flex; align-items: center; gap: 18px;
        transition: transform 0.25s, box-shadow 0.25s;
        position: relative; overflow: hidden;
    }
    .dept-card::after {
        content: ''; position: absolute; top: 0; right: 0; bottom: 0;
        width: 4px; border-radius: 0 18px 18px 0;
    }
    .dept-card.cleared::after { background: #06b6d4; }
    .dept-card.pending::after { background: var(--border); }
    .dept-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
    .dept-icon {
        width: 54px; height: 54px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem; flex-shrink: 0;
    }
    .dept-name { font-weight: 600; color: #fff; margin-bottom: 4px; }
    .dept-status { font-size: 0.82rem; font-weight: 600; display: flex; align-items: center; gap: 6px; }
    .dept-status.cleared { color: #06b6d4; }
    .dept-status.pending { color: var(--text-muted); }

    .info-note {
        background: rgba(59,130,246,0.07); border: 1px solid rgba(59,130,246,0.15);
        border-radius: 14px; padding: 20px 24px; display: flex; gap: 16px; align-items: flex-start;
    }
    .info-note i { color: #3b82f6; font-size: 1.3rem; margin-top: 2px; flex-shrink: 0; }
    .info-note p { color: var(--text-muted); font-size: 0.92rem; line-height: 1.6; }
</style>
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../image/logo.png" alt="SCNHS Logo">
        <div class="sidebar-title"><h2>SCNHS</h2><span>Student Portal</span></div>
    </div>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
        <a href="dashboard.php#edit-section" class="nav-item"><i class="fas fa-user-circle"></i> Learner's Profile</a>
        <a href="dashboard.php#grades-section" class="nav-item"><i class="fas fa-chart-bar"></i> My Grades (SF9)</a>
        <a href="dashboard.php#attendance-section" class="nav-item"><i class="fas fa-calendar-alt"></i> Class Schedule</a>
        <?php if (isset($student['grade']) && $student['grade'] == '12'): ?>
        <a href="work_immersion.php" class="nav-item"><i class="fas fa-briefcase"></i> Work Immersion</a>
        <?php endif; ?>
        <a href="../admin/print.php?lrn=<?php echo urlencode($student['lrn']); ?>" target="_blank" class="nav-item"><i class="fas fa-file-signature"></i> Enrollment Form</a>
        <a href="clearance.php" class="nav-item active"><i class="fas fa-check-circle"></i> Clearance</a>
        <a href="document_requests.php" class="nav-item"><i class="fas fa-file-alt"></i> Document Requests</a>
        <a href="sslg_elections.php" class="nav-item"><i class="fas fa-vote-yea"></i> SSLG Elections</a>
        <a href="clinic.php" class="nav-item"><i class="fas fa-briefcase-medical"></i> School Clinic</a>
    </div>
    <div class="sidebar-footer">
        <a href="logout.php" class="nav-item nav-logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
</aside>

<main class="main-content">
    <header class="header">
        <div style="display:flex;align-items:center;gap:16px">
            <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
            <div>
                <h1>School Clearance</h1>
                <p>Track your end-of-year clearance status</p>
            </div>
        </div>
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo e($student['first_name'].' '.$student['last_name']); ?></span>
        </div>
    </header>

    <!-- Status Hero -->
    <div class="page-hero">
        <div class="hero-top">
            <div>
                <h1><?php echo e($student['first_name']); ?>'s Clearance Status</h1>
                <p>School Year <?php echo e($student['school_year'] ?? date('Y').'-'.((int)date('Y')+1)); ?> &nbsp;|&nbsp; Grade <?php echo e($student['grade']); ?> – <?php echo e($student['strand']); ?></p>
            </div>
            <span class="clearance-badge">
                <i class="fas <?php echo $allCleared ? 'fa-check-double' : 'fa-hourglass-half'; ?>"></i>
                <?php echo $allCleared ? 'FULLY CLEARED' : "In Progress ({$clearedCount}/{$totalDepts})"; ?>
            </span>
        </div>
        <div class="progress-wrap">
            <div class="progress-label">
                <span>Overall Progress</span>
                <span><?php echo $progress; ?>%</span>
            </div>
            <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?php echo $progress; ?>%; background: linear-gradient(90deg, <?php echo $allCleared ? '#06b6d4, #67e8f9' : '#f59e0b, #fde68a'; ?>);"></div></div>
        </div>
    </div>

    <!-- Department Cards -->
    <div class="depts-grid">
        <?php foreach ($departments as $key => $dept):
            $status = $clearance[$key] ?? 'pending';
            $isCleared = $status === 'cleared';
        ?>
        <div class="dept-card <?php echo $isCleared ? 'cleared' : 'pending'; ?>">
            <div class="dept-icon" style="background: <?php echo $isCleared ? 'rgba(6,182,212,0.12)' : 'rgba(255,255,255,0.04)'; ?>; color: <?php echo $isCleared ? '#06b6d4' : $dept['color']; ?>">
                <i class="fas <?php echo $dept['icon']; ?>"></i>
            </div>
            <div>
                <div class="dept-name"><?php echo $dept['name']; ?></div>
                <div class="dept-status <?php echo $isCleared ? 'cleared' : 'pending'; ?>">
                    <?php if ($isCleared): ?>
                        <i class="fas fa-check-circle"></i> Cleared
                    <?php else: ?>
                        <i class="fas fa-clock"></i> Pending Clearance
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isCleared): ?>
            <div style="margin-left:auto">
                <i class="fas fa-check-circle" style="color:#06b6d4;font-size:1.5rem;opacity:0.5"></i>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Info Note -->
    <div class="info-note">
        <i class="fas fa-info-circle"></i>
        <p>
            Clearance is signed off by each department head or designated officer. 
            If any department shows <strong>Pending</strong>, please report to that office to settle any obligations (e.g., library books not returned, unpaid fees). 
            Clearance is required before the official issuance of your <strong>Form 138 (Report Card)</strong> and <strong>Transfer Credentials</strong>.
        </p>
    </div>
</main>
<script>
</script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>