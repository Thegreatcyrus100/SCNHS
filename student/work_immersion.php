<?php
/**
 * Work Immersion Portal — Grade 12 ONLY
 * Students can view their deployment info, log daily hours, and track total hours rendered.
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

$stmt = $conn->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { session_destroy(); header("Location: login.html"); exit(); }

// STRICT GRADE 12 CHECK
if ($student['grade'] != 12) {
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

// Auto-create tables
$conn->query("CREATE TABLE IF NOT EXISTS immersion_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    company_name VARCHAR(200), company_address TEXT,
    supervisor_name VARCHAR(150), supervisor_contact VARCHAR(50),
    required_hours INT DEFAULT 80, completed_hours INT DEFAULT 0,
    start_date DATE, end_date DATE,
    status ENUM('not_started','ongoing','completed') DEFAULT 'not_started',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS immersion_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    log_date DATE NOT NULL,
    hours_rendered DECIMAL(4,2) NOT NULL,
    tasks_done TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$msg = $msgType = '';

// Handle daily log submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_log'])) {
    $log_date    = $_POST['log_date'] ?? date('Y-m-d');
    $hours       = floatval($_POST['hours_rendered'] ?? 0);
    $tasks       = trim($_POST['tasks_done'] ?? '');

    if ($hours > 0 && $hours <= 12 && $log_date) {
        // Check no duplicate for same date
        $dupChk = $conn->prepare("SELECT id FROM immersion_logs WHERE student_id = ? AND log_date = ?");
        $dupChk->bind_param("is", $student_id, $log_date);
        $dupChk->execute();
        if ($dupChk->get_result()->num_rows > 0) {
            $msg = "You already have a log entry for this date.";
            $msgType = 'error';
        } else {
            $logIns = $conn->prepare("INSERT INTO immersion_logs (student_id, log_date, hours_rendered, tasks_done) VALUES (?, ?, ?, ?)");
            $logIns->bind_param("isds", $student_id, $log_date, $hours, $tasks);
            $logIns->execute();
            $logIns->close();

            // Recalculate completed hours
            $sumRes = $conn->prepare("SELECT SUM(hours_rendered) as total FROM immersion_logs WHERE student_id = ?");
            $sumRes->bind_param("i", $student_id);
            $sumRes->execute();
            $newTotal = $sumRes->get_result()->fetch_assoc()['total'] ?? 0;
            $sumRes->close();

            // Upsert immersion_records
            $recChk = $conn->prepare("SELECT id FROM immersion_records WHERE student_id = ?");
            $recChk->bind_param("i", $student_id);
            $recChk->execute();
            if ($recChk->get_result()->num_rows === 0) {
                $recIns = $conn->prepare("INSERT INTO immersion_records (student_id, completed_hours, status) VALUES (?, ?, 'ongoing')");
                $recIns->bind_param("id", $student_id, $newTotal);
                $recIns->execute();
                $recIns->close();
            } else {
                $reqHrs = 80;
                $newStatus = $newTotal >= $reqHrs ? 'completed' : 'ongoing';
                $upd = $conn->prepare("UPDATE immersion_records SET completed_hours = ?, status = ? WHERE student_id = ?");
                $upd->bind_param("dsi", $newTotal, $newStatus, $student_id);
                $upd->execute();
                $upd->close();
            }
            $recChk->close();

            $msg = "Daily log for <strong>" . date('M d, Y', strtotime($log_date)) . "</strong> recorded successfully!";
            $msgType = 'success';
        }
        $dupChk->close();
    } else {
        $msg = "Please enter valid hours (between 0.5 and 12).";
        $msgType = 'error';
    }
}

// Fetch immersion record
$ir = $conn->prepare("SELECT * FROM immersion_records WHERE student_id = ? LIMIT 1");
$ir->bind_param("i", $student_id);
$ir->execute();
$immersion = $ir->get_result()->fetch_assoc() ?: [];
$ir->close();

// Fetch logs
$logs = [];
$lg = $conn->prepare("SELECT * FROM immersion_logs WHERE student_id = ? ORDER BY log_date DESC");
$lg->bind_param("i", $student_id);
$lg->execute();
$lgr = $lg->get_result();
while ($row = $lgr->fetch_assoc()) $logs[] = $row;
$lg->close();

$conn->close();

$required = !empty($immersion['required_hours']) ? (float)$immersion['required_hours'] : 80;
$completed = !empty($immersion['completed_hours']) ? (float)$immersion['completed_hours'] : 0;
$progress = $required > 0 ? min(100, round(($completed / $required) * 100)) : 0;
$remaining = max(0, $required - $completed);
$statusColors = [
    'not_started' => ['#9ca3af', 'rgba(156,163,175,0.1)'],
    'ongoing'     => ['#06b6d4', 'rgba(6,182,212,0.1)'],
    'completed'   => ['#10b981', 'rgba(16,185,129,0.1)'],
];
$st = $immersion['status'] ?? 'not_started';
[$stColor, $stBg] = $statusColors[$st];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Work Immersion | SCNHS Student</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css">
<style>
    .immersion-hero {
        background: linear-gradient(135deg, rgba(6,182,212,0.15) 0%, rgba(251,191,36,0.05) 100%);
        border: 1px solid rgba(6,182,212,0.25);
        border-radius: 20px; padding: 32px; margin-bottom: 32px;
    }
    .hero-top { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; margin-bottom: 24px; }
    .hero-top h1 { font-size: 1.6rem; font-weight: 700; }
    .hero-top p { color: var(--text-muted); margin-top: 4px; }
    .status-pill {
        padding: 8px 20px; border-radius: 30px; font-weight: 700;
        display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem;
        background: <?php echo $stBg; ?>; color: <?php echo $stColor; ?>;
        border: 1px solid <?php echo $stColor; ?>44;
    }

    .progress-label { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.88rem; color: var(--text-muted); }
    .progress-bg { background: rgba(255,255,255,0.06); border-radius: 100px; height: 14px; overflow: hidden; }
    .progress-fill {
        height: 100%; border-radius: 100px; transition: width 1s ease;
        background: linear-gradient(90deg, #06b6d4, #22d3ee);
        position: relative;
    }
    .progress-fill::after {
        content: attr(data-progress);
        position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
        font-size: 0.7rem; font-weight: 700; color: #000;
    }
    .progress-fill.hide-text::after { display: none; }

    .stats-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 32px; }
    @media(max-width:600px) { .stats-3 { grid-template-columns: 1fr; } }
    .mini-stat {
        background: var(--bg-panel); border: 1px solid var(--border); border-radius: 16px;
        padding: 20px; text-align: center;
    }
    .mini-stat-val { font-size: 2.2rem; font-weight: 800; margin-bottom: 4px; }
    .mini-stat-label { font-size: 0.82rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: .5px; }

    .two-col { display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px; margin-bottom: 24px; }
    @media(max-width:900px) { .two-col { grid-template-columns: 1fr; } }

    .section-card { background: var(--bg-panel); border: 1px solid var(--border); border-radius: 20px; padding: 28px; }
    .section-title { display: flex; align-items: center; gap: 10px; font-size: 1.05rem; font-weight: 600; padding-bottom: 16px; border-bottom: 1px solid var(--border); margin-bottom: 20px; }
    .section-title i { color: #06b6d4; }

    .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.9rem; }
    .info-row:last-child { border-bottom: none; }
    .info-row-label { color: var(--text-muted); }
    .info-row-value { color: #e5e7eb; font-weight: 600; text-align: right; max-width: 60%; }

    .log-form { display: flex; flex-direction: column; gap: 14px; }
    .log-input { width: 100%; padding: 12px 16px; background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 10px; color: #fff; font-family: inherit; font-size: 0.95rem; transition: border-color .3s; }
    .log-input:focus { outline: none; border-color: #06b6d4; }
    .log-input::placeholder { color: var(--text-muted); }
    .log-textarea { min-height: 90px; resize: vertical; }
    .log-btn { padding: 13px; background: #06b6d4; color: #000; border: none; border-radius: 10px; font-family: inherit; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: all .3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .log-btn:hover { background: #22d3ee; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(6,182,212,.4); }

    .log-entry {
        display: flex; align-items: flex-start; gap: 14px; padding: 14px 0;
        border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .log-entry:last-child { border-bottom: none; }
    .log-date-box {
        background: rgba(6,182,212,0.1); border: 1px solid rgba(6,182,212,0.2);
        border-radius: 10px; padding: 8px 12px; text-align: center; flex-shrink: 0;
        min-width: 60px;
    }
    .log-date-day { font-size: 1.5rem; font-weight: 800; color: #06b6d4; line-height: 1; }
    .log-date-mon { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }
    .log-hours { font-weight: 700; color: #06b6d4; font-size: 1rem; }
    .log-tasks { font-size: 0.85rem; color: var(--text-muted); margin-top: 3px; line-height: 1.4; }

    .alert-msg { display: flex; align-items: center; gap: 10px; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; font-weight: 500; }
    .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981; }
    .alert-error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.2);  color: #ef4444; }

    .no-data { text-align: center; padding: 30px 20px; color: var(--text-muted); }
    .no-data i { font-size: 2.5rem; opacity: .2; margin-bottom: 10px; display: block; }
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
        <a href="work_immersion.php" class="nav-item active"><i class="fas fa-briefcase"></i> Work Immersion</a>
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
                <h1>Work Immersion</h1>
                <p>Grade 12 Applied Subject Deployment Tracker</p>
            </div>
        </div>
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo e($student['first_name'].' '.$student['last_name']); ?></span>
        </div>
    </header>

    <?php if ($msg): ?>
    <div class="alert-msg alert-<?php echo $msgType; ?>">
        <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <!-- Hero: Progress -->
    <div class="immersion-hero">
        <div class="hero-top">
            <div>
                <h1>Immersion Progress Tracker</h1>
                <p><?php echo e($immersion['company_name'] ?? 'No company assigned yet'); ?></p>
            </div>
            <span class="status-pill">
                <i class="fas fa-<?php echo ['not_started'=>'clock','ongoing'=>'spinner','completed'=>'check-double'][$st] ?? 'clock'; ?>"></i>
                <?php echo ucfirst(str_replace('_',' ', $st)); ?>
            </span>
        </div>
        <div class="progress-wrap">
            <div class="progress-label">
                <span><i class="fas fa-clock" style="margin-right:5px;color:#06b6d4"></i><?php echo $completed; ?> hours completed</span>
                <span><?php echo $remaining; ?> hours remaining of <?php echo $required; ?></span>
            </div>
            <div class="progress-bg"><div class="progress-fill <?php echo $progress < 15 ? 'hide-text' : ''; ?>" style="width: <?php echo $progress; ?>%;" data-progress="<?php echo $progress; ?>%"></div></div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-3">
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#06b6d4"><?php echo $completed; ?>h</div>
            <div class="mini-stat-label">Hours Completed</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#3b82f6"><?php echo $remaining; ?>h</div>
            <div class="mini-stat-label">Hours Remaining</div>
        </div>
        <div class="mini-stat">
            <div class="mini-stat-val" style="color:#10b981"><?php echo count($logs); ?></div>
            <div class="mini-stat-label">Daily Logs Submitted</div>
        </div>
    </div>

    <div class="two-col">
        <!-- Deployment Info -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-building"></i> Deployment Details</div>
            <?php if ($immersion && $immersion['company_name']): ?>
            <div class="info-row"><span class="info-row-label">Company / Partner</span><span class="info-row-value"><?php echo e($immersion['company_name']); ?></span></div>
            <div class="info-row"><span class="info-row-label">Address</span><span class="info-row-value"><?php echo e($immersion['company_address']); ?></span></div>
            <div class="info-row"><span class="info-row-label">Supervisor</span><span class="info-row-value"><?php echo e($immersion['supervisor_name']); ?></span></div>
            <div class="info-row"><span class="info-row-label">Supervisor Contact</span><span class="info-row-value"><?php echo e($immersion['supervisor_contact']); ?></span></div>
            <div class="info-row"><span class="info-row-label">Start Date</span><span class="info-row-value"><?php echo $immersion['start_date'] ? date('M d, Y', strtotime($immersion['start_date'])) : '—'; ?></span></div>
            <div class="info-row"><span class="info-row-label">End Date</span><span class="info-row-value"><?php echo $immersion['end_date'] ? date('M d, Y', strtotime($immersion['end_date'])) : '—'; ?></span></div>
            <div class="info-row"><span class="info-row-label">Required Hours</span><span class="info-row-value"><?php echo e($immersion['required_hours']); ?> hours</span></div>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-building"></i>
                <p>No company assigned yet.</p>
                <p style="font-size:.85rem;margin-top:4px">Your teacher will assign your immersion partner company soon.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Add Daily Log -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-plus-circle"></i> Submit Daily Time Log</div>
            <form method="POST" class="log-form">
                <div>
                    <label style="font-size:.85rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block">Date</label>
                    <input type="date" name="log_date" class="log-input" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div>
                    <label style="font-size:.85rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block">Hours Rendered</label>
                    <input type="number" name="hours_rendered" class="log-input" placeholder="e.g. 8" min="0.5" max="12" step="0.5" required>
                </div>
                <div>
                    <label style="font-size:.85rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;display:block">Tasks / Activities Done</label>
                    <textarea name="tasks_done" class="log-input log-textarea" placeholder="Describe what you did today at the company..."></textarea>
                </div>
                <button type="submit" name="add_log" class="log-btn">
                    <i class="fas fa-save"></i> Submit Log
                </button>
            </form>
        </div>
    </div>

    <!-- Log History -->
    <div class="section-card">
        <div class="section-title"><i class="fas fa-history"></i> Daily Log History</div>
        <?php if (empty($logs)): ?>
        <div class="no-data">
            <i class="fas fa-clipboard-list"></i>
            <p>No daily logs yet. Start submitting your daily time logs above!</p>
        </div>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <div class="log-entry">
            <div class="log-date-box">
                <div class="log-date-day"><?php echo date('d', strtotime($log['log_date'])); ?></div>
                <div class="log-date-mon"><?php echo date('M', strtotime($log['log_date'])); ?></div>
            </div>
            <div style="flex:1">
                <div class="log-hours"><i class="fas fa-clock" style="margin-right:4px"></i><?php echo $log['hours_rendered']; ?> hours rendered</div>
                <?php if ($log['tasks_done']): ?>
                <div class="log-tasks"><?php echo e($log['tasks_done']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
<script>
</script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
