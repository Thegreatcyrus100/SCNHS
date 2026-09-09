<?php
/**
 * Document Requests — Student Portal
 * Students can request official school documents (Form 137, CoE, Good Moral, etc.)
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

// Fetch student data
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) { session_destroy(); header("Location: login.html"); exit(); }

// Auto-create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS document_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    purpose TEXT,
    status ENUM('pending','processing','ready','released') DEFAULT 'pending',
    remarks TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$msg = $msgType = '';

// Handle new request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $doc_type = trim($_POST['document_type'] ?? '');
    $purpose  = trim($_POST['purpose'] ?? '');
    if ($doc_type && $purpose) {
        $ins = $conn->prepare("INSERT INTO document_requests (student_id, document_type, purpose) VALUES (?, ?, ?)");
        $ins->bind_param("iss", $student_id, $doc_type, $purpose);
        if ($ins->execute()) {
            $msg = "Your request for <strong>" . e($doc_type) . "</strong> has been submitted successfully!";
            $msgType = 'success';
        } else {
            $msg = "Failed to submit request. Please try again.";
            $msgType = 'error';
        }
        $ins->close();
    } else {
        $msg = "Please fill in all required fields.";
        $msgType = 'error';
    }
}

// Fetch my requests
$requests = [];
$rq = $conn->prepare("SELECT * FROM document_requests WHERE student_id = ? ORDER BY requested_at DESC");
$rq->bind_param("i", $student_id);
$rq->execute();
$res = $rq->get_result();
while ($row = $res->fetch_assoc()) $requests[] = $row;
$rq->close();

$conn->close();

$docs = [
    'Certificate of Enrollment',
    'Good Moral Certificate',
    'Form 137 (Permanent Record/SF10)',
    'Form 138 (Report Card/SF9)',
    'Diploma Copy',
    'Certificate of Completion',
    'Authentication of Documents',
    'Transfer Credentials'
];

$statusColors = [
    'pending'    => ['color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)',  'icon' => 'fa-clock'],
    'processing' => ['color' => '#06b6d4', 'bg' => 'rgba(6,182,212,0.1)',  'icon' => 'fa-spinner'],
    'ready'      => ['color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)', 'icon' => 'fa-check-circle'],
    'released'   => ['color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)','icon' => 'fa-box-open'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Requests | SCNHS Student</title>
<meta name="description" content="Request official school documents like Form 137, Certificate of Enrollment, and Good Moral Certificate.">
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css">
<style>
    .page-hero {
        background: linear-gradient(135deg, rgba(6,182,212,0.15) 0%, rgba(6,182,212,0.1) 100%);
        border: 1px solid rgba(6,182,212,0.2);
        border-radius: 20px;
        padding: 32px;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 24px;
    }
    .page-hero-icon {
        width: 72px; height: 72px; border-radius: 18px;
        background: rgba(6,182,212,0.2);
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; color: #06b6d4;
        flex-shrink: 0;
    }
    .page-hero h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: 6px; }
    .page-hero p { color: var(--text-muted); font-size: 0.95rem; }

    .request-form-card {
        background: var(--bg-panel); border: 1px solid var(--border);
        border-radius: 20px; padding: 32px; margin-bottom: 32px;
    }
    .section-label {
        display: flex; align-items: center; gap: 10px;
        font-size: 1.1rem; font-weight: 600; margin-bottom: 24px;
        padding-bottom: 16px; border-bottom: 1px solid var(--border);
    }
    .section-label i { color: var(--primary); }

    .doc-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px;
    }
    .doc-option { display: none; }
    .doc-option + label {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 10px; padding: 20px 16px; border-radius: 14px;
        border: 2px solid var(--border); background: rgba(0,0,0,0.2);
        cursor: pointer; transition: all 0.25s; text-align: center;
        font-size: 0.85rem; font-weight: 500; color: var(--text-muted);
        min-height: 100px;
    }
    .doc-option + label i { font-size: 1.6rem; }
    .doc-option + label:hover { border-color: var(--primary); color: #fff; background: rgba(6,182,212,0.08); }
    .doc-option:checked + label {
        border-color: var(--primary); background: rgba(6,182,212,0.12);
        color: var(--primary); box-shadow: 0 0 0 3px rgba(6,182,212,0.15);
    }

    .purpose-area {
        width: 100%; padding: 14px 18px;
        background: rgba(0,0,0,0.2); border: 1px solid var(--border);
        border-radius: 10px; color: #fff; font-family: inherit;
        font-size: 0.95rem; resize: vertical; min-height: 100px;
        transition: border-color 0.3s;
    }
    .purpose-area:focus { outline: none; border-color: var(--primary); }
    .purpose-area::placeholder { color: var(--text-muted); }

    .submit-btn {
        display: inline-flex; align-items: center; gap: 10px;
        padding: 14px 32px; background: var(--primary);
        color: #fff; border: none; border-radius: 10px;
        font-family: inherit; font-weight: 600; font-size: 1rem;
        cursor: pointer; transition: all 0.3s; margin-top: 20px;
    }
    .submit-btn:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(6,182,212,0.4); }

    .requests-table { width: 100%; }
    .req-card {
        background: rgba(0,0,0,0.15); border: 1px solid var(--border);
        border-radius: 14px; padding: 20px 24px;
        margin-bottom: 14px; display: flex; align-items: center;
        justify-content: space-between; gap: 20px; transition: background 0.2s;
    }
    .req-card:hover { background: rgba(255,255,255,0.03); }
    .req-card-left { display: flex; align-items: center; gap: 16px; }
    .req-icon {
        width: 44px; height: 44px; border-radius: 12px;
        background: rgba(6,182,212,0.1); color: #06b6d4;
        display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        flex-shrink: 0;
    }
    .req-doc-name { font-weight: 600; color: #fff; margin-bottom: 4px; }
    .req-meta { font-size: 0.82rem; color: var(--text-muted); }
    .status-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px; border-radius: 20px;
        font-size: 0.82rem; font-weight: 600; white-space: nowrap;
    }
    .empty-requests {
        text-align: center; padding: 60px 20px; color: var(--text-muted);
    }
    .empty-requests i { font-size: 3.5rem; opacity: 0.2; margin-bottom: 16px; display: block; }
    .empty-requests p { font-size: 1rem; }
    .alert-msg { display: flex; align-items: center; gap: 10px; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; font-weight: 500; }
    .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981; }
    .alert-error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.2);  color: #ef4444; }
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
        <a href="#" class="nav-item" onclick="scrollTo('edit-section')"><i class="fas fa-user-circle"></i> Learner's Profile</a>
        <a href="dashboard.php#grades-section" class="nav-item"><i class="fas fa-chart-bar"></i> My Grades (SF9)</a>
        <a href="dashboard.php#attendance-section" class="nav-item"><i class="fas fa-calendar-alt"></i> Class Schedule</a>
        <?php if (isset($student['grade']) && $student['grade'] == '12'): ?>
        <a href="work_immersion.php" class="nav-item"><i class="fas fa-briefcase"></i> Work Immersion</a>
        <?php endif; ?>
        <a href="../admin/print.php?lrn=<?php echo urlencode($student['lrn']); ?>" target="_blank" class="nav-item"><i class="fas fa-file-signature"></i> Enrollment Form</a>
        <a href="clearance.php" class="nav-item"><i class="fas fa-check-circle"></i> Clearance</a>
        <a href="document_requests.php" class="nav-item active"><i class="fas fa-file-alt"></i> Document Requests</a>
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
                <h1>Document Requests</h1>
                <p>Request official school records and certificates</p>
            </div>
        </div>
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span style="font-weight:500"><?php echo e($student['first_name'].' '.$student['last_name']); ?></span>
        </div>
    </header>

    <!-- Hero Banner -->
    <div class="page-hero">
        <div class="page-hero-icon"><i class="fas fa-folder-open"></i></div>
        <div>
            <h1>Official Document Request System</h1>
            <p>Submit requests for Form 137, Certificate of Enrollment, Good Moral Certificate, and more. Processing usually takes <strong>3–5 school days</strong>. Please claim your documents at the Registrar's Office.</p>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert-msg alert-<?php echo $msgType; ?>">
        <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <!-- Request Form -->
    <div class="request-form-card">
        <div class="section-label"><i class="fas fa-plus-circle"></i> New Document Request</div>
        <form method="POST" action="document_requests.php">
            <p style="color:var(--text-muted);margin-bottom:18px;font-size:0.9rem;">Select the document type you need:</p>
            <div class="doc-grid">
                <?php
                $docIcons = [
                    'Certificate of Enrollment'  => 'fa-file-certificate',
                    'Good Moral Certificate'     => 'fa-award',
                    'Form 137 (Permanent Record/SF10)' => 'fa-folder',
                    'Form 138 (Report Card/SF9)' => 'fa-chart-bar',
                    'Diploma Copy'               => 'fa-scroll',
                    'Certificate of Completion'  => 'fa-medal',
                    'Authentication of Documents'=> 'fa-stamp',
                    'Transfer Credentials'       => 'fa-exchange-alt',
                ];
                foreach ($docs as $i => $doc):
                    $ico = $docIcons[$doc] ?? 'fa-file';
                ?>
                <input type="radio" name="document_type" id="doc_<?php echo $i; ?>" value="<?php echo e($doc); ?>" class="doc-option" required>
                <label for="doc_<?php echo $i; ?>">
                    <i class="fas <?php echo $ico; ?>"></i>
                    <?php echo e($doc); ?>
                </label>
                <?php endforeach; ?>
            </div>

            <label class="form-group" style="margin-top:8px">
                <span style="display:block;font-size:0.85rem;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;">Purpose / Reason for Request</span>
                <textarea name="purpose" class="purpose-area" placeholder="e.g. For college application at De La Salle University..." required></textarea>
            </label>

            <button type="submit" name="submit_request" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Submit Request
            </button>
        </form>
    </div>

    <!-- My Requests -->
    <div class="request-form-card">
        <div class="section-label"><i class="fas fa-list-alt"></i> My Request History</div>
        <?php if (empty($requests)): ?>
        <div class="empty-requests">
            <i class="fas fa-inbox"></i>
            <p>No requests submitted yet.</p>
            <p style="font-size:0.85rem;margin-top:6px">Use the form above to request your first document.</p>
        </div>
        <?php else: ?>
        <?php foreach ($requests as $req):
            $sc = $statusColors[$req['status']] ?? $statusColors['pending'];
        ?>
        <div class="req-card">
            <div class="req-card-left">
                <div class="req-icon"><i class="fas fa-file-alt"></i></div>
                <div>
                    <div class="req-doc-name"><?php echo e($req['document_type']); ?></div>
                    <div class="req-meta">
                        <i class="fas fa-align-left" style="margin-right:4px"></i><?php echo e(substr($req['purpose'],0,60)).(strlen($req['purpose'])>60?'...':''); ?>
                    </div>
                    <div class="req-meta" style="margin-top:3px">
                        <i class="fas fa-clock" style="margin-right:4px"></i><?php echo date('M d, Y h:i A', strtotime($req['requested_at'])); ?>
                    </div>
                    <?php if ($req['remarks']): ?>
                    <div class="req-meta" style="margin-top:3px;color:#f59e0b">
                        <i class="fas fa-comment-dots" style="margin-right:4px"></i><?php echo e($req['remarks']); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <span class="status-badge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>">
                <i class="fas <?php echo $sc['icon']; ?>"></i>
                <?php echo ucfirst($req['status']); ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script src="../static/sidebar.js?v=2"></script>
</body>
</html>