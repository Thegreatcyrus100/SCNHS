<?php
/**
 * My Grades (SF9) — Student Portal
 * DepEd-style report card showing quarterly grades.
 * Respects admin setting: enable_show_grades
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

// Check admin toggle: enable_show_grades
$gradesEnabled = false;
$settingStmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'enable_show_grades' LIMIT 1");
if ($settingStmt && $row = $settingStmt->fetch_assoc()) {
    $gradesEnabled = ($row['setting_value'] === '1');
}

// Fetch grades
$grades = [];
if ($gradesEnabled) {
    $g_stmt = $conn->prepare("SELECT subject_name, q1, q2, q3, q4, final FROM grades WHERE student_id = ? ORDER BY subject_name");
    if ($g_stmt) {
        $g_stmt->bind_param("i", $student_id);
        $g_stmt->execute();
        $g_res = $g_stmt->get_result();
        while ($grow = $g_res->fetch_assoc()) {
            $grades[] = $grow;
        }
        $g_stmt->close();
    }
}

$conn->close();

// Compute general average
$validFinals = array_filter(array_column($grades, 'final'), fn($v) => $v !== null);
$generalAverage = count($validFinals) > 0 ? round(array_sum($validFinals) / count($validFinals), 2) : null;

// SHS Subject groupings (DepEd K-12 curriculum)
$coreSubjects = ['Oral Communication', 'Komunikasyon at Pananaliksik', 'Reading and Writing', 'Pagbasa at Pagsusuri', '21st Century Literature', 'Contemporary Philippine Arts', 'Media and Information Literacy', 'General Mathematics', 'Statistics and Probability', 'Earth and Life Science', 'Physical Science', 'Introduction to Philosophy', 'Physical Education and Health', 'Personal Development', 'Understanding Culture, Society, and Politics', 'Earth Science', 'Disaster Readiness'];
$appliedSubjects = ['Empowerment Technologies', 'English for Academic and Professional Purposes', 'Practical Research 1', 'Practical Research 2', 'Filipino sa Piling Larang', 'Inquiries, Investigations and Immersion', 'Work Immersion'];

// Helper: Categorize subjects
function categorizeSubject(string $name, array $core, array $applied): string {
    foreach ($core as $c) {
        if (stripos($name, $c) !== false) return 'core';
    }
    foreach ($applied as $a) {
        if (stripos($name, $a) !== false) return 'applied';
    }
    return 'specialized'; // Strand-specific subjects
}

// Group grades by category
$grouped = ['core' => [], 'applied' => [], 'specialized' => []];
foreach ($grades as $g) {
    $cat = categorizeSubject($g['subject_name'], $coreSubjects, $appliedSubjects);
    $grouped[$cat][] = $g;
}

// Grade color helper
function gradeColor(?float $grade): string {
    if ($grade === null) return '#6b7280';
    if ($grade >= 90) return '#10b981';
    if ($grade >= 85) return '#3b82f6';
    if ($grade >= 80) return '#6366f1';
    if ($grade >= 75) return '#f59e0b';
    return '#ef4444';
}

function gradeRemarks(?float $grade): string {
    if ($grade === null) return '';
    return $grade >= 75 ? 'Passed' : 'Failed';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Grades (SF9) | SCNHS Student</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css">
<style>

    /* DepEd Report Card Header */
    .report-header {
        background: linear-gradient(135deg, rgba(6,182,212,0.12) 0%, rgba(6,182,212,0.03) 100%);
        border: 1px solid rgba(6,182,212,0.2);
        border-radius: 20px; padding: 32px; margin-bottom: 28px;
        position: relative; overflow: hidden;
    }
    .report-header::before {
        content: ''; position: absolute; top: -30px; right: -30px;
        width: 120px; height: 120px; border-radius: 50%;
        background: rgba(6,182,212,0.06); z-index: 0;
    }
    .report-header-content { position: relative; z-index: 1; }
    .report-title { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
    .report-logo {
        width: 60px; height: 60px; border-radius: 50%;
        background: linear-gradient(135deg, #0e7490, #06b6d4);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.6rem; color: #fff; flex-shrink: 0;
    }
    .report-title h1 { font-size: 1.3rem; font-weight: 700; line-height: 1.3; }
    .report-title h1 span { display: block; font-size: 0.82rem; font-weight: 400; color: var(--text-muted); margin-top: 2px; }

    .student-info-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 12px; background: rgba(0,0,0,0.12); border-radius: 14px; padding: 18px;
    }
    .info-item { display: flex; flex-direction: column; }
    .info-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: var(--text-muted); margin-bottom: 3px; }
    .info-value { font-size: 0.92rem; font-weight: 600; color: #e5e7eb; }

    /* Stats Summary */
    .grade-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
    .grade-stat-card {
        background: var(--bg-panel); border: 1px solid var(--border); border-radius: 16px;
        padding: 20px; text-align: center; position: relative; overflow: hidden;
    }
    .grade-stat-card::after {
        content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
    }
    .grade-stat-val { font-size: 2.4rem; font-weight: 800; line-height: 1; margin-bottom: 6px; }
    .grade-stat-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); font-weight: 600; }

    /* SF9 Table */
    .sf9-panel {
        background: var(--bg-panel); border: 1px solid var(--border);
        border-radius: 20px; padding: 0; margin-bottom: 24px; overflow: hidden;
    }
    .sf9-panel-header {
        padding: 18px 24px; display: flex; align-items: center; justify-content: space-between;
        border-bottom: 1px solid var(--border);
    }
    .sf9-panel-title { font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .sf9-panel-count {
        font-size: 0.78rem; background: rgba(6,182,212,0.1); color: #06b6d4;
        padding: 4px 12px; border-radius: 20px; font-weight: 600;
    }

    .sf9-table { width: 100%; border-collapse: collapse; }
    .sf9-table thead { background: rgba(0,0,0,0.2); }
    .sf9-table th {
        padding: 12px 16px; font-size: 0.75rem; text-transform: uppercase;
        letter-spacing: 0.5px; font-weight: 700; color: var(--text-muted);
        text-align: center; border-bottom: 1px solid var(--border);
    }
    .sf9-table th:first-child { text-align: left; }
    .sf9-table td {
        padding: 14px 16px; font-size: 0.92rem; text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .sf9-table td:first-child { text-align: left; font-weight: 600; color: #e5e7eb; }
    .sf9-table tr:last-child td { border-bottom: none; }
    .sf9-table tr:hover { background: rgba(255,255,255,0.02); }

    .grade-cell {
        display: inline-flex; align-items: center; justify-content: center;
        width: 44px; height: 32px; border-radius: 8px; font-weight: 700; font-size: 0.88rem;
    }
    .grade-empty { color: var(--text-muted); font-weight: 400; }

    .final-grade-cell {
        display: inline-flex; align-items: center; justify-content: center;
        padding: 4px 14px; border-radius: 20px; font-weight: 800; font-size: 0.92rem;
    }

    .remarks-passed { color: #10b981; font-weight: 600; font-size: 0.82rem; }
    .remarks-failed { color: #ef4444; font-weight: 600; font-size: 0.82rem; }

    .sf9-table tfoot td {
        padding: 14px 16px; font-weight: 700; background: rgba(0,0,0,0.15);
        border-top: 2px solid var(--border);
    }

    /* Locked Banner */
    .locked-banner {
        background: linear-gradient(135deg, rgba(239,68,68,0.08), rgba(239,68,68,0.02));
        border: 1px solid rgba(239,68,68,0.2); border-radius: 20px;
        padding: 60px 40px; text-align: center;
    }
    .locked-icon {
        width: 80px; height: 80px; border-radius: 50%;
        background: rgba(239,68,68,0.1); border: 2px solid rgba(239,68,68,0.2);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px; font-size: 2rem; color: #ef4444;
    }
    .locked-banner h2 { font-size: 1.3rem; margin-bottom: 10px; }
    .locked-banner p { color: var(--text-muted); max-width: 450px; margin: 0 auto; line-height: 1.6; }

    @media print {
        .report-header { background: #f0f4ff !important; -webkit-print-color-adjust: exact; }
        body { background: #fff !important; color: #000 !important; }
        .sf9-panel { border: 1px solid #ccc !important; }
        .sf9-table th, .sf9-table td { color: #000 !important; }
    }
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
        <a href="my_grades.php" class="nav-item active"><i class="fas fa-chart-bar"></i> My Grades (SF9)</a>
        <a href="dashboard.php#attendance-section" class="nav-item"><i class="fas fa-calendar-alt"></i> Class Schedule</a>
        <?php if (isset($student['grade']) && $student['grade'] == '12'): ?>
        <a href="work_immersion.php" class="nav-item"><i class="fas fa-briefcase"></i> Work Immersion</a>
        <?php endif; ?>
        <a href="../admin/print.php?lrn=<?php echo urlencode($student['lrn']); ?>" target="_blank" class="nav-item"><i class="fas fa-file-signature"></i> Enrollment Form</a>
        <a href="clearance.php" class="nav-item"><i class="fas fa-check-circle"></i> Clearance</a>
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
                <h1>My Grades</h1>
                <p>School Form 9 (SF9) — Learner Progress Report Card</p>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <?php if ($gradesEnabled && !empty($grades)): ?>
            <button onclick="window.print()" class="btn btn-primary" style="padding:8px 18px;font-size:0.85rem">
                <i class="fas fa-print" style="margin-right:5px"></i>Print
            </button>
            <?php endif; ?>
            <div class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></span>
            </div>
        </div>
    </header>

    <?php if (!$gradesEnabled): ?>
    <!-- Grades Hidden by Admin -->
    <div class="locked-banner">
        <div class="locked-icon"><i class="fas fa-lock"></i></div>
        <h2>Grades are Currently Hidden</h2>
        <p>Your grades are not yet available for viewing. The school administration will enable grade visibility once the encoding period is complete. Please check back later.</p>
    </div>
    <?php else: ?>

    <!-- DepEd Report Card Header -->
    <div class="report-header">
        <div class="report-header-content">
            <div class="report-title">
                <div class="report-logo"><i class="fas fa-graduation-cap"></i></div>
                <h1>
                    Learner Progress Report Card
                    <span>Senior High School — School Form 9 (SF9) | Republic of the Philippines • Department of Education</span>
                </h1>
            </div>
            <div class="student-info-grid">
                <div class="info-item">
                    <span class="info-label">Learner's Name</span>
                    <span class="info-value"><?php echo e($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">LRN</span>
                    <span class="info-value"><?php echo e($student['lrn']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Grade Level</span>
                    <span class="info-value">Grade <?php echo e($student['grade']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Track / Strand</span>
                    <span class="info-value"><?php echo e(($student['track'] ?? 'Academic') . ' — ' . $student['strand']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Section</span>
                    <span class="info-value"><?php echo e($student['section']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">School Year / Semester</span>
                    <span class="info-value"><?php echo e(($student['school_year'] ?? '2024-2025') . ' / ' . ($student['semester'] ?? '1st Semester')); ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($grades)): ?>
    <div class="locked-banner" style="border-color:rgba(245,158,11,0.2);background:linear-gradient(135deg,rgba(245,158,11,0.06),transparent)">
        <div class="locked-icon" style="background:rgba(245,158,11,0.1);border-color:rgba(245,158,11,0.2);color:#f59e0b"><i class="fas fa-clipboard"></i></div>
        <h2>No Grades Encoded Yet</h2>
        <p>Your teachers have not yet submitted grades for your subjects. Once your quarterly grades are encoded, they will appear here in the official SF9 format.</p>
    </div>
    <?php else: ?>

    <!-- Stats Summary -->
    <div class="grade-stats">
        <div class="grade-stat-card">
            <div class="grade-stat-val" style="color:<?php echo gradeColor($generalAverage); ?>"><?php echo $generalAverage ?? '—'; ?></div>
            <div class="grade-stat-label">General Average</div>
            <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:<?php echo gradeColor($generalAverage); ?>"></div>
        </div>
        <div class="grade-stat-card">
            <div class="grade-stat-val" style="color:#3b82f6"><?php echo count($grades); ?></div>
            <div class="grade-stat-label">Total Subjects</div>
            <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:#3b82f6"></div>
        </div>
        <div class="grade-stat-card">
            <?php
            $passedCount = count(array_filter($validFinals, fn($v) => $v >= 75));
            $failedCount = count($validFinals) - $passedCount;
            ?>
            <div class="grade-stat-val" style="color:#10b981"><?php echo $passedCount; ?></div>
            <div class="grade-stat-label">Subjects Passed</div>
            <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:#10b981"></div>
        </div>
        <div class="grade-stat-card">
            <div class="grade-stat-val" style="color:<?php echo $failedCount > 0 ? '#ef4444' : '#10b981'; ?>"><?php echo $failedCount; ?></div>
            <div class="grade-stat-label">Subjects Failed</div>
            <div style="position:absolute;bottom:0;left:0;right:0;height:3px;background:<?php echo $failedCount > 0 ? '#ef4444' : '#10b981'; ?>"></div>
        </div>
    </div>

    <!-- Core Subjects -->
    <?php
    $sections = [
        'core' => ['Core Subjects', 'fa-book-open', '#3b82f6'],
        'applied' => ['Applied & Track Subjects', 'fa-cogs', '#8b5cf6'],
        'specialized' => ['Specialized Subjects (' . e($student['strand']) . ')', 'fa-flask', '#f59e0b'],
    ];
    foreach ($sections as $key => [$title, $icon, $color]):
        if (empty($grouped[$key])) continue;

        // Section average
        $sectionFinals = array_filter(array_column($grouped[$key], 'final'), fn($v) => $v !== null);
        $sectionAvg = count($sectionFinals) > 0 ? round(array_sum($sectionFinals) / count($sectionFinals), 2) : null;
    ?>
    <div class="sf9-panel">
        <div class="sf9-panel-header">
            <div class="sf9-panel-title">
                <i class="fas <?php echo $icon; ?>" style="color:<?php echo $color; ?>"></i>
                <?php echo $title; ?>
            </div>
            <span class="sf9-panel-count"><?php echo count($grouped[$key]); ?> subject<?php echo count($grouped[$key]) > 1 ? 's' : ''; ?></span>
        </div>
        <table class="sf9-table">
            <thead>
                <tr>
                    <th style="width:35%">Subject</th>
                    <th>1st Quarter</th>
                    <th>2nd Quarter</th>
                    <th>3rd Quarter</th>
                    <th>4th Quarter</th>
                    <th>Final Grade</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grouped[$key] as $g):
                    $final = $g['final'] !== null ? floatval($g['final']) : null;
                    $fColor = gradeColor($final);
                ?>
                <tr>
                    <td><?php echo e($g['subject_name']); ?></td>
                    <?php foreach (['q1','q2','q3','q4'] as $q): ?>
                    <td>
                        <?php if ($g[$q] !== null): ?>
                        <span class="grade-cell" style="background:<?php echo gradeColor(floatval($g[$q])); ?>18;color:<?php echo gradeColor(floatval($g[$q])); ?>"><?php echo number_format($g[$q], 0); ?></span>
                        <?php else: ?>
                        <span class="grade-empty">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td>
                        <?php if ($final !== null): ?>
                        <span class="final-grade-cell" style="background:<?php echo $fColor; ?>22;color:<?php echo $fColor; ?>;border:1px solid <?php echo $fColor; ?>44">
                            <?php echo number_format($final, 0); ?>
                        </span>
                        <?php else: ?>
                        <span class="grade-empty">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($final !== null): ?>
                        <span class="<?php echo $final >= 75 ? 'remarks-passed' : 'remarks-failed'; ?>">
                            <?php echo gradeRemarks($final); ?>
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($sectionAvg !== null): ?>
            <tfoot>
                <tr>
                    <td style="text-align:left">Section Average</td>
                    <td colspan="4"></td>
                    <td>
                        <span class="final-grade-cell" style="background:<?php echo gradeColor($sectionAvg); ?>22;color:<?php echo gradeColor($sectionAvg); ?>;border:1px solid <?php echo gradeColor($sectionAvg); ?>44">
                            <?php echo number_format($sectionAvg, 2); ?>
                        </span>
                    </td>
                    <td><span class="<?php echo $sectionAvg >= 75 ? 'remarks-passed' : 'remarks-failed'; ?>"><?php echo $sectionAvg >= 75 ? 'Passed' : 'Failed'; ?></span></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php endforeach; ?>

    <!-- General Average Footer -->
    <?php if ($generalAverage !== null): ?>
    <div class="sf9-panel" style="border-color:<?php echo gradeColor($generalAverage); ?>44">
        <div class="sf9-panel-header" style="border-bottom:none">
            <div class="sf9-panel-title" style="font-size:1.1rem">
                <i class="fas fa-award" style="color:<?php echo gradeColor($generalAverage); ?>;font-size:1.3rem"></i>
                General Weighted Average
            </div>
            <div style="display:flex;align-items:center;gap:14px">
                <span class="final-grade-cell" style="font-size:1.6rem;padding:8px 24px;background:<?php echo gradeColor($generalAverage); ?>22;color:<?php echo gradeColor($generalAverage); ?>;border:2px solid <?php echo gradeColor($generalAverage); ?>44">
                    <?php echo number_format($generalAverage, 2); ?>
                </span>
                <span class="<?php echo $generalAverage >= 75 ? 'remarks-passed' : 'remarks-failed'; ?>" style="font-size:1rem">
                    <?php echo $generalAverage >= 75 ? '✓ Passed' : '✗ Failed'; ?>
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- DepEd Grading Scale Reference -->
    <div class="sf9-panel">
        <div class="sf9-panel-header">
            <div class="sf9-panel-title">
                <i class="fas fa-info-circle" style="color:#6366f1"></i>
                DepEd Grading Scale Reference
            </div>
        </div>
        <div style="padding:20px 24px;display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px">
            <?php
            $scale = [
                ['Outstanding', '90–100', '#10b981'],
                ['Very Satisfactory', '85–89', '#3b82f6'],
                ['Satisfactory', '80–84', '#6366f1'],
                ['Fairly Satisfactory', '75–79', '#f59e0b'],
                ['Did Not Meet Expectations', 'Below 75', '#ef4444'],
            ];
            foreach ($scale as [$desc, $range, $clr]): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px;background:<?php echo $clr; ?>0d;border:1px solid <?php echo $clr; ?>22;border-radius:10px">
                <div style="width:12px;height:12px;border-radius:50%;background:<?php echo $clr; ?>;flex-shrink:0"></div>
                <div>
                    <div style="font-weight:600;font-size:0.82rem;color:<?php echo $clr; ?>"><?php echo $range; ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted)"><?php echo $desc; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; // end empty grades check ?>
    <?php endif; // end gradesEnabled check ?>
</main>

<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
