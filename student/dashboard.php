<?php
/**
 * Student Portal Dashboard
 * Shows enrollment details, attendance, contact editing, PDF download
 */
session_start();
require_once dirname(__DIR__) . '/config.php';

// Auth guard
if (!isset($_SESSION['student_id'])) {
    header("Location: login.html");
    exit();
}

$conn = get_db_connection();


// Fetch student data
$student_id = $_SESSION['student_id'];
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    session_destroy();
    header("Location: login.html?error=" . urlencode("Student record not found."));
    exit();
}

// Fetch attendance records
$attendance = [];
$att_stmt = $conn->prepare("SELECT date, check_in_time FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 30");
$att_stmt->bind_param("i", $student_id);
$att_stmt->execute();
$att_result = $att_stmt->get_result();
while ($row = $att_result->fetch_assoc()) {
    $attendance[] = $row;
}
$att_stmt->close();

// Total attendance count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE student_id = ?");
$count_stmt->bind_param("i", $student_id);
$count_stmt->execute();
$total_attendance = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// Fetch grades
$grades = [];
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

$conn->close();

// Helper to safely output
if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars(isset($val) ? $val : '—', ENT_QUOTES, 'UTF-8');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Portal | SCNHS Student</title>
    <meta name="description" content="Student dashboard — View enrollment details, attendance records, and manage your profile.">
    <link rel="icon" href="../image/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../static/dashboard_shared.css">
    <style>
        /* Student-specific overrides */
        .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .info-card { background: var(--bg-panel); backdrop-filter: var(--glass-blur); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
        .info-card__header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border); }
        .info-card__icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .info-card__icon--teal { background: rgba(6, 182, 212, 0.1); color: #06b6d4; }
        .info-card__icon--green { background: rgba(16, 185, 129, 0.1); color: var(--accent); }
        .info-card__icon--amber { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .info-card__icon--purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .info-card__icon--blue { background: rgba(59, 130, 246, 0.1); color: var(--primary); }
        .info-card__title { font-size: 1rem; font-weight: 600; color: #fff; }
        .info-card__subtitle { font-size: 0.8rem; color: var(--text-muted); }
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .info-row:last-child { border-bottom: none; }
        .info-row__label { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        .info-row__value { font-size: 0.9rem; color: #e5e7eb; font-weight: 500; text-align: right; max-width: 60%; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge--teal { background: rgba(6, 182, 212, 0.15); color: #06b6d4; }
        .badge--green { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .badge--red { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .full-width { grid-column: 1 / -1; }
        .edit-form { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
        .field__label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .field__input { width: 100%; padding: 12px 16px; background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 8px; color: #fff; font-family: inherit; font-size: 0.95rem; transition: border-color 0.3s; }
        .field__input:focus { outline: none; border-color: var(--primary); }
        .btn-save { grid-column: 1 / -1; padding: 12px 20px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-family: inherit; font-weight: 600; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; }
        .btn-save:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.4); }
        .alert-msg { display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-radius: 10px; margin-bottom: 24px; font-weight: 500; }
        .alert-msg--success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--accent); }
        .alert-msg--error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--danger); }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 2.5rem; opacity: 0.4; margin-bottom: 12px; display: block; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../image/logo.png" alt="SCNHS Logo">
            <div class="sidebar-title">
                <h2>SCNHS</h2>
                <span>Student Portal</span>
            </div>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-item active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="#edit-section" class="nav-item" onclick="document.getElementById('edit-section').scrollIntoView({behavior:'smooth'}); return false;"><i class="fas fa-user-circle"></i> Learner's Profile</a>
            <a href="my_grades.php" class="nav-item"><i class="fas fa-chart-bar"></i> My Grades (SF9)</a>
            <a href="#attendance-section" class="nav-item" onclick="document.getElementById('attendance-section').scrollIntoView({behavior:'smooth'}); return false;"><i class="fas fa-calendar-alt"></i> Class Schedule</a>
            
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

    <!-- Main Content -->
    <main class="main-content">

        <!-- Header -->
        <header class="header">
            <div style="display:flex;align-items:center;gap:16px">
                <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
                <div>
                    <h1>Welcome, <?php echo e($student['first_name']); ?>!</h1>
                    <p>LRN: <strong><?php echo e($student['lrn']); ?></strong> &nbsp;|&nbsp; Grade <?php echo e($student['grade']); ?> — <?php echo e($student['strand']); ?> (<?php echo e($student['section']); ?>)</p>
                </div>
            </div>
            <div class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span style="font-weight: 500;"><?php echo e($student['first_name'] . ' ' . $student['last_name']); ?></span>
            </div>
        </header>

        <!-- Success/Error Messages -->
        <div id="update-alert" style="display:none;" class="alert-msg alert-msg--success" role="alert">
            <i class="fas fa-check-circle"></i>
            <span id="update-alert-text"></span>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(6,182,212,0.1);color:#06b6d4"><i class="fas fa-graduation-cap"></i></div>
                <div class="stat-info">
                    <h3>Grade <?php echo e($student['grade']); ?></h3>
                    <p><?php echo e($student['track']); ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-clipboard-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_attendance; ?></h3>
                    <p>Attendance Days</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="fas fa-book"></i></div>
                <div class="stat-info">
                    <h3><?php echo count($grades); ?></h3>
                    <p>Subjects</p>
                </div>
            </div>
        </div>

        <!-- Info Cards Grid -->
        <div class="info-grid">

            <!-- Academic Info -->
            <div class="info-card">
                <div class="info-card__header">
                    <div class="info-card__icon info-card__icon--teal"><i class="fas fa-school"></i></div>
                    <div>
                        <div class="info-card__title">Academic Details</div>
                        <div class="info-card__subtitle">Current enrollment status</div>
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Grade Level</span>
                    <span class="info-row__value"><?php echo e($student['grade']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Track</span>
                    <span class="info-row__value"><?php echo e($student['track']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Strand</span>
                    <span class="info-row__value"><span class="badge badge--teal"><?php echo e($student['strand']); ?></span></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Section</span>
                    <span class="info-row__value"><?php echo e($student['section']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">School Year</span>
                    <span class="info-row__value"><?php echo e($student['school_year']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Learning Modality</span>
                    <span class="info-row__value"><?php echo e($student['learning_modality']); ?></span>
                </div>
            </div>

            <!-- Personal Info -->
            <div class="info-card">
                <div class="info-card__header">
                    <div class="info-card__icon info-card__icon--green"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="info-card__title">Personal Information</div>
                        <div class="info-card__subtitle">Your profile details</div>
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Full Name</span>
                    <span class="info-row__value"><?php echo e($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Date of Birth</span>
                    <span class="info-row__value"><?php echo e($student['dob'] ? date('M d, Y', strtotime($student['dob'])) : '—'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Gender</span>
                    <span class="info-row__value"><?php echo e($student['gender']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Age</span>
                    <span class="info-row__value"><?php echo e($student['age']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Contact</span>
                    <span class="info-row__value"><?php echo e($student['contact']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Mother Tongue</span>
                    <span class="info-row__value"><?php echo e($student['mother_tongue']); ?></span>
                </div>
            </div>

            <!-- Address -->
            <div class="info-card">
                <div class="info-card__header">
                    <div class="info-card__icon info-card__icon--amber"><i class="fas fa-map-marker-alt"></i></div>
                    <div>
                        <div class="info-card__title">Address</div>
                        <div class="info-card__subtitle">Current residential address</div>
                    </div>
                </div>
                <div class="info-row">
                    <span class="info-row__label">House No.</span>
                    <span class="info-row__value"><?php echo e($student['house_number']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Street</span>
                    <span class="info-row__value"><?php echo e($student['street']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Barangay</span>
                    <span class="info-row__value"><?php echo e($student['barangay']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Municipality</span>
                    <span class="info-row__value"><?php echo e($student['municipality']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Province</span>
                    <span class="info-row__value"><?php echo e($student['province']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Zip Code</span>
                    <span class="info-row__value"><?php echo e($student['zip_code']); ?></span>
                </div>
            </div>

            <!-- Parent/Guardian -->
            <div class="info-card">
                <div class="info-card__header">
                    <div class="info-card__icon info-card__icon--purple"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="info-card__title">Parent / Guardian</div>
                        <div class="info-card__subtitle">Family contact details</div>
                    </div>
                </div>
                <?php if (!empty($student['father_first_name'])): ?>
                <div class="info-row">
                    <span class="info-row__label">Father</span>
                    <span class="info-row__value"><?php echo e($student['father_first_name'] . ' ' . ($student['father_middle_name'] ?? '') . ' ' . ($student['father_last_name'] ?? '')); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($student['mother_first_name'])): ?>
                <div class="info-row">
                    <span class="info-row__label">Mother</span>
                    <span class="info-row__value"><?php echo e($student['mother_first_name'] . ' ' . ($student['mother_middle_name'] ?? '') . ' ' . ($student['mother_last_name'] ?? '')); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($student['guardian_first_name'])): ?>
                <div class="info-row">
                    <span class="info-row__label">Guardian</span>
                    <span class="info-row__value"><?php echo e($student['guardian_first_name'] . ' ' . ($student['guardian_middle_name'] ?? '') . ' ' . ($student['guardian_last_name'] ?? '')); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-row__label">Contact</span>
                    <span class="info-row__value"><?php echo e($student['contact_guardian_parent']); ?></span>
                </div>
            </div>
        </div>

        <!-- Grades Panel -->
        <div class="panel" id="grades-section">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-chart-line"></i>
                    <h2>My Grades</h2>
                </div>
            </div>
            <?php if (count($grades) > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th style="text-align:center">Q1</th>
                            <th style="text-align:center">Q2</th>
                            <th style="text-align:center">Q3</th>
                            <th style="text-align:center">Q4</th>
                            <th style="text-align:center">Final Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $g): ?>
                        <tr>
                            <td style="font-weight: 500;"><?php echo htmlspecialchars($g['subject_name']); ?></td>
                            <td style="text-align:center"><?php echo $g['q1'] !== null ? number_format($g['q1'], 2) : '—'; ?></td>
                            <td style="text-align:center"><?php echo $g['q2'] !== null ? number_format($g['q2'], 2) : '—'; ?></td>
                            <td style="text-align:center"><?php echo $g['q3'] !== null ? number_format($g['q3'], 2) : '—'; ?></td>
                            <td style="text-align:center"><?php echo $g['q4'] !== null ? number_format($g['q4'], 2) : '—'; ?></td>
                            <td style="text-align:center">
                                <?php if ($g['final'] !== null): ?>
                                    <?php 
                                    $fval = floatval($g['final']);
                                    $bclass = $fval >= 75 ? 'badge--green' : 'badge--red';
                                    ?>
                                    <span class="badge <?php echo $bclass; ?>" style="font-size:0.9rem; padding:4px 8px;"><?php echo number_format($fval, 2); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted)">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No grades have been posted for your subjects yet.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Attendance Panel -->
        <div class="panel" id="attendance-section">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-clipboard-check"></i>
                    <h2>Attendance Records</h2>
                </div>
                <span class="badge badge--green" style="font-size:0.85rem;padding:6px 12px;"><i class="fas fa-check"></i> <?php echo $total_attendance; ?> total</span>
            </div>
            <?php if (count($attendance) > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Check-in Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $i => $att): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo date('M d, Y', strtotime($att['date'])); ?></td>
                            <td><?php echo date('l', strtotime($att['date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($att['check_in_time'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p>No attendance records found yet.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Edit Contact Panel -->
        <div class="panel" id="edit-section">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-pen-to-square"></i>
                    <h2>Update Contact Information</h2>
                </div>
            </div>
            <form class="edit-form" id="editContactForm">
                <div>
                    <label for="edit-contact" class="field__label">Contact Number</label>
                    <input type="tel" id="edit-contact" name="contact" class="field__input"
                           value="<?php echo e($student['contact']); ?>" placeholder="e.g. 09123456789">
                </div>
                <div>
                    <label for="edit-province" class="field__label">Province</label>
                    <input type="text" id="edit-province" name="province" class="field__input"
                           value="<?php echo e($student['province']); ?>" placeholder="Province">
                </div>
                <div>
                    <label for="edit-municipality" class="field__label">Municipality</label>
                    <input type="text" id="edit-municipality" name="municipality" class="field__input"
                           value="<?php echo e($student['municipality']); ?>" placeholder="Municipality">
                </div>
                <div>
                    <label for="edit-barangay" class="field__label">Barangay</label>
                    <input type="text" id="edit-barangay" name="barangay" class="field__input"
                           value="<?php echo e($student['barangay']); ?>" placeholder="Barangay">
                </div>
                <div>
                    <label for="edit-street" class="field__label">Street</label>
                    <input type="text" id="edit-street" name="street" class="field__input"
                           value="<?php echo e($student['street']); ?>" placeholder="Street">
                </div>
                <div>
                    <label for="edit-house" class="field__label">House Number</label>
                    <input type="text" id="edit-house" name="house_number" class="field__input"
                           value="<?php echo e($student['house_number']); ?>" placeholder="House No.">
                </div>
                <div>
                    <label for="edit-zip" class="field__label">Zip Code</label>
                    <input type="text" id="edit-zip" name="zip_code" class="field__input"
                           value="<?php echo e($student['zip_code']); ?>" placeholder="Zip Code">
                </div>
                <button type="submit" class="btn-save" id="btnSave">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>

    </main>

    <script>
        // Edit contact form — AJAX submit
        document.getElementById('editContactForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = document.getElementById('btnSave');
            const alert = document.getElementById('update-alert');
            const alertText = document.getElementById('update-alert-text');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            const formData = new FormData(this);

            fetch('update_contact.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert.style.display = 'flex';

                if (data.success) {
                    alert.className = 'alert-msg alert-msg--success';
                    alertText.textContent = data.success;
                } else {
                    alert.className = 'alert-msg alert-msg--error';
                    alertText.textContent = data.error || 'Something went wrong.';
                }

                alert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                setTimeout(function() { alert.style.display = 'none'; }, 5000);
            })
            .catch(function() {
                alert.style.display = 'flex';
                alert.className = 'alert-msg alert-msg--error';
                alertText.textContent = 'Network error. Please try again.';
            })
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            });
        });
    </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
