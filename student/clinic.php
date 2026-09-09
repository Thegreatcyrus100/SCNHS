<?php
/**
 * School Clinic / Health Records — Student Portal
 * Displays BMI, nutritional status, health records per DepEd SF8 format.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.html");
    exit();
}

$conn = get_db_connection();
$student_id = $_SESSION['student_id'];

function e($v) { return htmlspecialchars($v ?? '—', ENT_QUOTES, 'UTF-8'); }

$stmt = $conn->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { session_destroy(); header("Location: login.html"); exit(); }

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS health_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    height_cm DECIMAL(5,2), weight_kg DECIMAL(5,2), bmi DECIMAL(4,2),
    nutritional_status VARCHAR(50), vision_left VARCHAR(20), vision_right VARCHAR(20),
    blood_type VARCHAR(5), medical_history TEXT, recorded_by VARCHAR(100),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Fetch latest health record
$hr = $conn->prepare("SELECT * FROM health_records WHERE student_id = ? ORDER BY recorded_at DESC LIMIT 1");
$hr->bind_param("i", $student_id);
$hr->execute();
$health = $hr->get_result()->fetch_assoc();
$hr->close();

// Fetch all records
$allRecords = [];
$ar = $conn->prepare("SELECT * FROM health_records WHERE student_id = ? ORDER BY recorded_at DESC");
$ar->bind_param("i", $student_id);
$ar->execute();
$arr = $ar->get_result();
while ($row = $arr->fetch_assoc()) $allRecords[] = $row;
$ar->close();

$conn->close();

// BMI color mapping
function getBmiCategory($bmi) {
    if (!$bmi) return ['N/A', '#9ca3af'];
    if ($bmi < 18.5) return ['Underweight', '#3b82f6'];
    if ($bmi < 25)   return ['Normal', '#10b981'];
    if ($bmi < 30)   return ['Overweight', '#f59e0b'];
    return ['Obese', '#06b6d4'];
}
[$bmiLabel, $bmiColor] = getBmiCategory($health['bmi'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>School Clinic | SCNHS Student</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css">
<style>
    .health-hero {
        background: linear-gradient(135deg, rgba(6,182,212,0.1) 0%, rgba(245,158,11,0.05) 100%);
        border: 1px solid rgba(6,182,212,0.2);
        border-radius: 20px; padding: 32px; margin-bottom: 32px;
        display: flex; gap: 24px; align-items: center; flex-wrap: wrap;
    }
    .health-hero-icon {
        width: 80px; height: 80px; border-radius: 20px;
        background: rgba(6,182,212,0.15); color: #06b6d4;
        display: flex; align-items: center; justify-content: center; font-size: 2.2rem;
        flex-shrink: 0;
    }
    .health-hero h1 { font-size: 1.5rem; font-weight: 700; }
    .health-hero p { color: var(--text-muted); margin-top: 6px; }

    .vitals-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 18px; margin-bottom: 32px; }
    .vital-card {
        background: var(--bg-panel); border: 1px solid var(--border); border-radius: 16px;
        padding: 24px; text-align: center; transition: transform .25s;
    }
    .vital-card:hover { transform: translateY(-4px); }
    .vital-icon { font-size: 1.8rem; margin-bottom: 12px; }
    .vital-value { font-size: 2rem; font-weight: 700; line-height: 1; margin-bottom: 6px; }
    .vital-label { font-size: 0.82rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: .5px; }

    .bmi-meter {
        background: var(--bg-panel); border: 1px solid var(--border); border-radius: 20px; padding: 28px; margin-bottom: 32px;
    }
    .bmi-meter h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 20px; }
    .bmi-scale {
        display: flex; border-radius: 100px; overflow: hidden; height: 14px; gap: 2px; margin-bottom: 12px;
    }
    .bmi-seg { flex: 1; border-radius: 100px; }
    .bmi-pointer-row { display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-muted); }
    .bmi-result {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 20px; border-radius: 20px; font-weight: 700; font-size: 1.1rem;
        background: <?php echo $bmiColor; ?>22; color: <?php echo $bmiColor; ?>;
        border: 1px solid <?php echo $bmiColor; ?>44; margin-top: 16px;
    }

    .section-card {
        background: var(--bg-panel); border: 1px solid var(--border); border-radius: 20px; padding: 28px; margin-bottom: 24px;
    }
    .section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
    .section-title i { color: var(--danger); }
    .info-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.92rem; }
    .info-row:last-child { border-bottom: none; }
    .info-row-label { color: var(--text-muted); font-weight: 500; }
    .info-row-value { color: #e5e7eb; font-weight: 600; }
    .no-data {
        text-align: center; padding: 50px 20px; color: var(--text-muted);
    }
    .no-data i { font-size: 3rem; opacity: 0.2; margin-bottom: 14px; display: block; }
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
        <a href="clearance.php" class="nav-item"><i class="fas fa-check-circle"></i> Clearance</a>
        <a href="document_requests.php" class="nav-item"><i class="fas fa-file-alt"></i> Document Requests</a>
        <a href="sslg_elections.php" class="nav-item"><i class="fas fa-vote-yea"></i> SSLG Elections</a>
        <a href="clinic.php" class="nav-item active"><i class="fas fa-briefcase-medical"></i> School Clinic</a>
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
                <h1>School Clinic</h1>
                <p>Your health records and BMI assessment (DepEd SF8)</p>
            </div>
        </div>
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo e($student['first_name'].' '.$student['last_name']); ?></span>
        </div>
    </header>

    <div class="health-hero">
        <div class="health-hero-icon"><i class="fas fa-heartbeat"></i></div>
        <div>
            <h1>Health & Wellness Record</h1>
            <p>This page displays your latest health assessment as recorded by the school nurse or clinic officer based on DepEd's School Form 8 (SF8) — Health & Nutrition Report.</p>
            <?php if ($health): ?>
            <p style="margin-top:8px;color:var(--accent);font-size:0.88rem"><i class="fas fa-check-circle"></i> Last updated: <?php echo date('F d, Y', strtotime($health['recorded_at'])); ?> by <?php echo e($health['recorded_by']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($health): ?>
    <!-- Vital Cards -->
    <div class="vitals-grid">
        <div class="vital-card">
            <div class="vital-icon" style="color:#06b6d4">📏</div>
            <div class="vital-value" style="color:#06b6d4"><?php echo $health['height_cm'] ? $health['height_cm'].' cm' : '—'; ?></div>
            <div class="vital-label">Height</div>
        </div>
        <div class="vital-card">
            <div class="vital-icon" style="color:#6366f1">⚖️</div>
            <div class="vital-value" style="color:#6366f1"><?php echo $health['weight_kg'] ? $health['weight_kg'].' kg' : '—'; ?></div>
            <div class="vital-label">Weight</div>
        </div>
        <div class="vital-card">
            <div class="vital-icon" style="color:<?php echo $bmiColor; ?>">💪</div>
            <div class="vital-value" style="color:<?php echo $bmiColor; ?>"><?php echo $health['bmi'] ?? '—'; ?></div>
            <div class="vital-label">BMI</div>
        </div>
        <div class="vital-card">
            <div class="vital-icon" style="color:#06b6d4">🩸</div>
            <div class="vital-value" style="color:#06b6d4;font-size:1.5rem"><?php echo e($health['blood_type']); ?></div>
            <div class="vital-label">Blood Type</div>
        </div>
    </div>

    <!-- BMI Meter -->
    <div class="bmi-meter">
        <h3><i class="fas fa-ruler" style="color:var(--danger);margin-right:8px"></i>BMI Assessment (WHO & DepEd Standard)</h3>
        <div class="bmi-scale">
            <div class="bmi-seg" style="background:#3b82f6;opacity:.7"></div>
            <div class="bmi-seg" style="background:#10b981"></div>
            <div class="bmi-seg" style="background:#f59e0b"></div>
            <div class="bmi-seg" style="background:#06b6d4"></div>
        </div>
        <div class="bmi-pointer-row">
            <span>Underweight (&lt;18.5)</span>
            <span>Normal (18.5–24.9)</span>
            <span>Overweight (25–29.9)</span>
            <span>Obese (≥30)</span>
        </div>
        <div>
            <span class="bmi-result"><i class="fas fa-circle"></i> <?php echo $bmiLabel; ?> (<?php echo $health['bmi'] ?? '?'; ?>)</span>
        </div>
    </div>

    <!-- Other Health Info -->
    <div class="section-card">
        <div class="section-title"><i class="fas fa-eye"></i> Vision & Other Details</div>
        <div class="info-row">
            <span class="info-row-label">Vision (Left Eye)</span>
            <span class="info-row-value"><?php echo e($health['vision_left']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-row-label">Vision (Right Eye)</span>
            <span class="info-row-value"><?php echo e($health['vision_right']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-row-label">Nutritional Status</span>
            <span class="info-row-value" style="color:<?php echo $bmiColor; ?>"><?php echo e($health['nutritional_status']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-row-label">Medical History / Notes</span>
            <span class="info-row-value"><?php echo e($health['medical_history'] ?: 'None recorded'); ?></span>
        </div>
    </div>

    <?php if (count($allRecords) > 1): ?>
    <!-- Health History Table -->
    <div class="section-card">
        <div class="section-title"><i class="fas fa-history"></i> Assessment History</div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Height (cm)</th>
                        <th>Weight (kg)</th>
                        <th>BMI</th>
                        <th>Status</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allRecords as $rec):
                        [$rl, $rc] = getBmiCategory($rec['bmi'] ?? null);
                    ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($rec['recorded_at'])); ?></td>
                        <td><?php echo e($rec['height_cm']); ?></td>
                        <td><?php echo e($rec['weight_kg']); ?></td>
                        <td><?php echo e($rec['bmi']); ?></td>
                        <td><span style="color:<?php echo $rc; ?>;font-weight:600"><?php echo $rl; ?></span></td>
                        <td><?php echo e($rec['recorded_by']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="section-card">
        <div class="no-data">
            <i class="fas fa-stethoscope"></i>
            <p style="font-size:1.1rem;font-weight:600;color:#fff;margin-bottom:8px">No Health Records Yet</p>
            <p>Your health assessment (BMI, vision, blood type) has not been recorded yet by the school clinic. Please visit the clinic office for your annual assessment.</p>
        </div>
    </div>
    <?php endif; ?>
</main>
<script>
</script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>