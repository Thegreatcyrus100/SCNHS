<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

if (!isset($_SESSION['teacher_id'])) {
    header("Location: login.html?error=" . urlencode("Please log in as a teacher."));
    exit();
}

$teacher_name = $_SESSION['teacher_name'];

// Fetch sections assigned to this teacher
$sec_stmt = $conn->prepare("SELECT * FROM sections WHERE teacher_name LIKE ? ORDER BY grade_level, section_name");
$search_name = "%" . $teacher_name . "%";
$sec_stmt->bind_param("s", $search_name);
$sec_stmt->execute();
$sections_result = $sec_stmt->get_result();
$my_sections = [];
$my_section_ids = [0];

while ($row = $sections_result->fetch_assoc()) {
    $my_sections[] = $row;
    $my_section_ids[] = $row['section_id'];
}
$sec_stmt->close();

$selected_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : (count($my_sections) > 0 ? $my_sections[0]['section_id'] : 0);
$selected_subject = isset($_GET['subject']) ? trim($_GET['subject']) : 'Advisory Class Gen Ave';

// Ensure selected section belongs to this teacher
if ($selected_section > 0 && !in_array($selected_section, $my_section_ids)) {
    die("Error: Access denied to this section.");
}

$msg = "";
$msgType = "";

// Handle grade updates
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save_grades'])) {
    $q1_grades = $_POST['q1'] ?? [];
    $q2_grades = $_POST['q2'] ?? [];
    $q3_grades = $_POST['q3'] ?? [];
    $q4_grades = $_POST['q4'] ?? [];
    
    $success = true;
    foreach ($q1_grades as $student_id => $val) {
        $student_id = intval($student_id);
        $q1 = $val !== '' ? floatval($val) : null;
        $q2 = isset($q2_grades[$student_id]) && $q2_grades[$student_id] !== '' ? floatval($q2_grades[$student_id]) : null;
        $q3 = isset($q3_grades[$student_id]) && $q3_grades[$student_id] !== '' ? floatval($q3_grades[$student_id]) : null;
        $q4 = isset($q4_grades[$student_id]) && $q4_grades[$student_id] !== '' ? floatval($q4_grades[$student_id]) : null;
        
        // Calculate final grade
        $grades_count = 0;
        $sum = 0;
        if ($q1 !== null) { $sum += $q1; $grades_count++; }
        if ($q2 !== null) { $sum += $q2; $grades_count++; }
        if ($q3 !== null) { $sum += $q3; $grades_count++; }
        if ($q4 !== null) { $sum += $q4; $grades_count++; }
        $final = $grades_count > 0 ? $sum / $grades_count : null;

        // Check if grade record exists
        $check_stmt = $conn->prepare("SELECT id FROM grades WHERE student_id = ? AND subject_name = ?");
        $check_stmt->bind_param("is", $student_id, $selected_subject);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        
        if ($check_res->num_rows > 0) {
            // Update
            $up_stmt = $conn->prepare("UPDATE grades SET q1 = ?, q2 = ?, q3 = ?, q4 = ?, final = ? WHERE student_id = ? AND subject_name = ?");
            $up_stmt->bind_param("dddddis", $q1, $q2, $q3, $q4, $final, $student_id, $selected_subject);
            if (!$up_stmt->execute()) $success = false;
            $up_stmt->close();
        } else {
            // Get student lrn
            $lrn_stmt = $conn->prepare("SELECT lrn FROM students WHERE id = ?");
            $lrn_stmt->bind_param("i", $student_id);
            $lrn_stmt->execute();
            $lrn = $lrn_stmt->get_result()->fetch_assoc()['lrn'];
            $lrn_stmt->close();

            // Insert
            $in_stmt = $conn->prepare("INSERT INTO grades (student_id, lrn, subject_name, q1, q2, q3, q4, final) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $in_stmt->bind_param("issddddd", $student_id, $lrn, $selected_subject, $q1, $q2, $q3, $q4, $final);
            if (!$in_stmt->execute()) $success = false;
            $in_stmt->close();
        }
        $check_stmt->close();
    }
    
    if ($success) {
        $msg = "Grades updated successfully!";
        $msgType = "success";
    } else {
        $msg = "An error occurred while saving grades.";
        $msgType = "error";
    }
}

// Fetch students and their grades for selected section
$students = [];
if ($selected_section > 0) {
    $sql = "SELECT s.id, s.lrn, s.first_name, s.last_name, g.q1, g.q2, g.q3, g.q4, g.final 
            FROM students s 
            LEFT JOIN grades g ON s.id = g.student_id AND g.subject_name = ? 
            WHERE s.section_id = ? 
            ORDER BY s.last_name, s.first_name";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $selected_subject, $selected_section);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades Management | Teacher Portal</title>
    <link rel="icon" href="../image/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--bg-dark:#0b1120;--bg-panel:rgba(17,24,39,.75);--bg-panel-hover:rgba(31,41,55,.85);--text-main:#f9fafb;--text-muted:#9ca3af;--primary:#3b82f6;--primary-hover:#2563eb;--accent:#10b981;--danger:#ef4444;--border:rgba(255,255,255,.08);--glass:blur(16px)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-dark);background-image:radial-gradient(circle at 85% 15%,rgba(59,130,246,.08),transparent 25%),radial-gradient(circle at 15% 85%,rgba(16,185,129,.05),transparent 25%);background-attachment:fixed;color:var(--text-main);display:flex;min-height:100vh}
        .header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:40px;flex-wrap:wrap;gap:20px}
        .header h1{font-size:2rem;font-weight:700;margin-bottom:8px;background:linear-gradient(90deg,#fff,#9ca3af);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .header p{color:var(--text-muted);font-size:1.05rem}
        
        .filters-panel{background:var(--bg-panel);backdrop-filter:var(--glass);border:1px solid var(--border);border-radius:16px;padding:20px;margin-bottom:30px;display:flex;gap:16px;flex-wrap:wrap;align-items:center}
        .filter-group{display:flex;flex-direction:column;gap:6px;flex:1;min-width:200px}
        .filter-group label{font-size:0.8rem;color:var(--text-muted);font-weight:600;text-transform:uppercase}
        .form-control{width:100%;padding:10px 14px;background:rgba(0,0,0,.2);border:1px solid var(--border);border-radius:8px;color:#fff;font-family:inherit;font-size:0.9rem;transition:border-color .3s}
        .form-control:focus{outline:none;border-color:var(--primary)}
        
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 18px;border-radius:8px;border:none;cursor:pointer;font-family:inherit;font-weight:600;font-size:.9rem;transition:all .3s;text-decoration:none;color:#fff}
        .btn-primary{background:var(--primary)}.btn-primary:hover{background:var(--primary-hover)}
        .btn-success{background:var(--accent)}.btn-success:hover{background:#0d9488}
        
        .panel{background:var(--bg-panel);backdrop-filter:var(--glass);border:1px solid var(--border);border-radius:16px;padding:24px;margin-bottom:30px}
        .panel-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .panel-title{display:flex;align-items:center;gap:12px}
        .panel-title h2{font-size:1.2rem;font-weight:600}.panel-title i{color:var(--primary)}
        
        .table-responsive{overflow-x:auto}
        table{width:100%;border-collapse:collapse;text-align:left}
        th{padding:16px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.8rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;white-space:nowrap}
        td{padding:16px;border-bottom:1px solid rgba(255,255,255,.03);color:#e5e7eb;vertical-align:middle;white-space:nowrap}
        tr:hover td{background:rgba(255,255,255,.02)}
        
        .grade-input{width:70px;padding:6px 10px;background:rgba(0,0,0,.3);border:1px solid var(--border);border-radius:6px;color:#fff;text-align:center;font-weight:600}
        .grade-input:focus{outline:none;border-color:var(--primary)}
        .final-badge{font-family:monospace;background:rgba(59,130,246,.1);color:var(--primary);padding:6px 10px;border-radius:6px;font-weight:bold;font-size:0.95rem}
        .final-pass{background:rgba(16,185,129,.1);color:var(--accent)}
        .final-fail{background:rgba(239,68,68,.1);color:var(--danger)}
        
        .alert{padding:16px;border-radius:8px;margin-bottom:24px;font-weight:500;display:flex;align-items:center;gap:12px}
        .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2);color:var(--accent)}
        .alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:var(--danger)}

        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:1024px){}
    </style>
</head>
<body>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../image/logo.png" alt="SCNHS Logo">
            <div class="sidebar-title">
                <h2>SCNHS</h2>
                <span>Teacher Portal</span>
            </div>
        </div>
        <div class="nav-links">
            <a href="teacher_dashboard.php" class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
            <a href="my_students.php" class="nav-item"><i class="fas fa-users"></i> My Students</a>
            <a href="attendance_report.php" class="nav-item"><i class="fas fa-clipboard-check"></i> Attendance</a>
            <a href="grades.php" class="nav-item active"><i class="fas fa-chart-line"></i> Grades</a>
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
                    <h1>Grades Management</h1>
                    <p>Input and review Quarterly Grades for your advisory students.</p>
                </div>
            </div>
        </header>

        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msgType; ?>">
                <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <form method="GET" class="filters-panel">
            <div class="filter-group">
                <label for="section_id">Advisory Section</label>
                <select name="section_id" id="section_id" class="form-control" onchange="this.form.submit()">
                    <?php if (count($my_sections) === 0): ?>
                        <option value="0">No Advisory Sections</option>
                    <?php endif; ?>
                    <?php foreach ($my_sections as $sec): ?>
                        <option value="<?php echo $sec['section_id']; ?>" <?php echo $selected_section === $sec['section_id'] ? 'selected' : ''; ?>>
                            Grade <?php echo $sec['grade_level'] . ' - ' . htmlspecialchars($sec['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="subject">Subject Name</label>
                <select name="subject" id="subject" class="form-control" onchange="this.form.submit()">
                    <option value="Advisory Class Gen Ave" <?php echo $selected_subject === 'Advisory Class Gen Ave' ? 'selected' : ''; ?>>Advisory Class Gen Ave</option>
                    <option value="Core Subject" <?php echo $selected_subject === 'Core Subject' ? 'selected' : ''; ?>>Core Subject</option>
                    <option value="Applied/Specialized Subject" <?php echo $selected_subject === 'Applied/Specialized Subject' ? 'selected' : ''; ?>>Applied/Specialized Subject</option>
                </select>
            </div>
            <div style="align-self:flex-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync"></i> Refresh</button>
            </div>
        </form>

        <?php if ($selected_section > 0): ?>
        <form method="POST" action="">
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-list-ol"></i>
                        <h2>Students Grades Matrix &mdash; <?php echo htmlspecialchars($selected_subject); ?></h2>
                    </div>
                    <button type="submit" name="save_grades" class="btn btn-success"><i class="fas fa-save"></i> Save All Grades</button>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>LRN</th>
                                <th>Name</th>
                                <th style="text-align:center">Q1</th>
                                <th style="text-align:center">Q2</th>
                                <th style="text-align:center">Q3</th>
                                <th style="text-align:center">Q4</th>
                                <th style="text-align:center">Final</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($students) > 0): ?>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><span class="badge" style="font-size:0.85rem;color:var(--text-muted)"><?php echo htmlspecialchars($student['lrn']); ?></span></td>
                                    <td style="font-weight:600"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></td>
                                    <td style="text-align:center">
                                        <input type="number" step="0.01" min="60" max="100" class="grade-input" name="q1[<?php echo $student['id']; ?>]" value="<?php echo $student['q1'] !== null ? htmlspecialchars($student['q1']) : ''; ?>">
                                    </td>
                                    <td style="text-align:center">
                                        <input type="number" step="0.01" min="60" max="100" class="grade-input" name="q2[<?php echo $student['id']; ?>]" value="<?php echo $student['q2'] !== null ? htmlspecialchars($student['q2']) : ''; ?>">
                                    </td>
                                    <td style="text-align:center">
                                        <input type="number" step="0.01" min="60" max="100" class="grade-input" name="q3[<?php echo $student['id']; ?>]" value="<?php echo $student['q3'] !== null ? htmlspecialchars($student['q3']) : ''; ?>">
                                    </td>
                                    <td style="text-align:center">
                                        <input type="number" step="0.01" min="60" max="100" class="grade-input" name="q4[<?php echo $student['id']; ?>]" value="<?php echo $student['q4'] !== null ? htmlspecialchars($student['q4']) : ''; ?>">
                                    </td>
                                    <td style="text-align:center">
                                        <?php if ($student['final'] !== null): ?>
                                            <?php 
                                            $final_val = floatval($student['final']);
                                            $badge_class = $final_val >= 75 ? 'final-pass' : 'final-fail';
                                            ?>
                                            <span class="final-badge <?php echo $badge_class; ?>">
                                                <?php echo number_format($final_val, 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="final-badge" style="color:var(--text-muted);background:rgba(255,255,255,0.03)">--</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">
                                        No students enrolled in this section.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
        <?php else: ?>
            <div class="panel" style="text-align:center;padding:60px 20px;color:var(--text-muted)">
                <i class="fas fa-chalkboard-user" style="font-size:3rem;margin-bottom:16px;opacity:0.3;display:block"></i>
                <h2>No advisory sections assigned</h2>
                <p>Ensure your administrator has assigned you as an adviser to advisory sections.</p>
            </div>
        <?php endif; ?>
    </main>

    <script>
        </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
