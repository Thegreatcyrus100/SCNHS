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

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

// Ensure selected section belongs to this teacher
if ($selected_section > 0 && !in_array($selected_section, $my_section_ids)) {
    die("Error: Access denied to this section.");
}

// 1. Get all students under advisory sections
$section_filter_sql = "";
$params = [];
$types = "";

if ($selected_section > 0) {
    $section_filter_sql = "s.section_id = ?";
    $params[] = $selected_section;
    $types .= "i";
} else {
    $placeholders = implode(',', array_fill(0, count($my_section_ids), '?'));
    $section_filter_sql = "s.section_id IN ($placeholders)";
    foreach ($my_section_ids as $id) {
        $params[] = $id;
        $types .= "i";
    }
}

// Get student count
$count_sql = "SELECT COUNT(*) as cnt FROM students s WHERE $section_filter_sql";
$count_stmt = $conn->prepare($count_sql);
if (count($params) > 0) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_students = $count_stmt->get_result()->fetch_assoc()['cnt'];
$count_stmt->close();

// 2. Fetch attendance logs for selected section(s) on the specific date
$att_sql = "SELECT a.*, s.first_name, s.last_name, s.lrn, sec.section_name, sec.grade_level 
            FROM attendance a 
            INNER JOIN students s ON a.student_id = s.id 
            INNER JOIN sections sec ON s.section_id = sec.section_id 
            WHERE a.date = ? AND $section_filter_sql 
            ORDER BY a.check_in_time DESC";

$att_params = array_merge([$filter_date], $params);
$att_types = "s" . $types;

$att_stmt = $conn->prepare($att_sql);
$att_stmt->bind_param($att_types, ...$att_params);
$att_stmt->execute();
$attendance_result = $att_stmt->get_result();
$attendance_records = [];
$present_student_ids = [];

while ($row = $attendance_result->fetch_assoc()) {
    $attendance_records[] = $row;
    $present_student_ids[] = $row['student_id'];
}
$att_stmt->close();

$present_count = count($attendance_records);
$absent_count = $total_students - $present_count;
$attendance_rate = $total_students > 0 ? round(($present_count / $total_students) * 100, 1) : 0;

// 3. Fetch absent students (who didn't scan check-in today)
$absent_records = [];
if ($total_students > 0) {
    $abs_placeholders = "";
    $abs_params = $params;
    $abs_types = $types;
    
    if ($present_count > 0) {
        $in_placeholders = implode(',', array_fill(0, $present_count, '?'));
        $abs_placeholders = "AND s.id NOT IN ($in_placeholders)";
        $abs_params = array_merge($params, $present_student_ids);
        $abs_types .= str_repeat('i', $present_count);
    }
    
    $abs_sql = "SELECT s.id, s.lrn, s.first_name, s.last_name, sec.section_name, sec.grade_level 
                FROM students s 
                INNER JOIN sections sec ON s.section_id = sec.section_id 
                WHERE $section_filter_sql $abs_placeholders 
                ORDER BY s.last_name, s.first_name";
                
    $abs_stmt = $conn->prepare($abs_sql);
    $abs_stmt->bind_param($abs_types, ...$abs_params);
    $abs_stmt->execute();
    $abs_result = $abs_stmt->get_result();
    while ($row = $abs_result->fetch_assoc()) {
        $absent_records[] = $row;
    }
    $abs_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report | Teacher Portal</title>
    <link rel="icon" href="../image/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--bg-dark:#0b1120;--bg-panel:rgba(17,24,39,.75);--bg-panel-hover:rgba(31,41,55,.85);--text-main:#f9fafb;--text-muted:#9ca3af;--primary:#3b82f6;--primary-hover:#2563eb;--accent:#10b981;--danger:#ef4444;--warning:#f59e0b;--border:rgba(255,255,255,.08);--glass:blur(16px)}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:var(--bg-dark);background-image:radial-gradient(circle at 85% 15%,rgba(59,130,246,.08),transparent 25%),radial-gradient(circle at 15% 85%,rgba(16,185,129,.05),transparent 25%);background-attachment:fixed;color:var(--text-main);display:flex;min-height:100vh}
        .header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:40px;flex-wrap:wrap;gap:20px}
        .header h1{font-size:2rem;font-weight:700;margin-bottom:8px;background:linear-gradient(90deg,#fff,#9ca3af);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .header p{color:var(--text-muted);font-size:1.05rem}
        
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin-bottom:30px}
        .stat-card{background:var(--bg-panel);backdrop-filter:var(--glass);border:1px solid var(--border);border-radius:16px;padding:20px;display:flex;align-items:center;gap:16px}
        .stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem}
        .icon-blue{background:rgba(59,130,246,.1);color:var(--primary)}
        .icon-green{background:rgba(16,185,129,.1);color:var(--accent)}
        .icon-red{background:rgba(239,68,68,.1);color:var(--danger)}
        .icon-yellow{background:rgba(245,158,11,.1);color:var(--warning)}
        .stat-info h3{font-size:1.6rem;font-weight:700;color:#fff;line-height:1;margin-bottom:4px}
        .stat-info p{color:var(--text-muted);font-size:.8rem;font-weight:500;text-transform:uppercase}

        .filters-panel{background:var(--bg-panel);backdrop-filter:var(--glass);border:1px solid var(--border);border-radius:16px;padding:20px;margin-bottom:30px;display:flex;gap:16px;flex-wrap:wrap;align-items:center}
        .filter-group{display:flex;flex-direction:column;gap:6px;flex:1;min-width:200px}
        .filter-group label{font-size:0.8rem;color:var(--text-muted);font-weight:600;text-transform:uppercase}
        .form-control{width:100%;padding:10px 14px;background:rgba(0,0,0,.2);border:1px solid var(--border);border-radius:8px;color:#fff;font-family:inherit;font-size:0.9rem;transition:border-color .3s}
        .form-control:focus{outline:none;border-color:var(--primary)}
        
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 18px;border-radius:8px;border:none;cursor:pointer;font-family:inherit;font-weight:600;font-size:.9rem;transition:all .3s;text-decoration:none;color:#fff}
        .btn-primary{background:var(--primary)}.btn-primary:hover{background:var(--primary-hover)}
        
        .grid-panels{display:grid;grid-template-columns:1fr 1fr;gap:30px}
        .panel{background:var(--bg-panel);backdrop-filter:var(--glass);border:1px solid var(--border);border-radius:16px;padding:24px;margin-bottom:30px}
        .panel-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .panel-title{display:flex;align-items:center;gap:12px}
        .panel-title h2{font-size:1.2rem;font-weight:600}.panel-title i{color:var(--primary)}
        
        .table-responsive{overflow-x:auto}
        table{width:100%;border-collapse:collapse;text-align:left}
        th{padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.8rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;white-space:nowrap}
        td{padding:12px 16px;border-bottom:1px solid rgba(255,255,255,.03);color:#e5e7eb;vertical-align:middle;white-space:nowrap;font-size:0.9rem}
        tr:hover td{background:rgba(255,255,255,.02)}
        
        .badge{font-family:monospace;background:rgba(16,185,129,.1);color:var(--accent);padding:4px 8px;border-radius:4px;font-weight:bold;font-size:0.8rem}
        .badge-red{background:rgba(239,68,68,.1);color:var(--danger)}
        
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:1024px){.grid-panels{grid-template-columns:1fr}}
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
            <a href="attendance_report.php" class="nav-item active"><i class="fas fa-clipboard-check"></i> Attendance</a>
            <a href="grades.php" class="nav-item"><i class="fas fa-chart-line"></i> Grades</a>
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
                    <h1>Attendance Reports</h1>
                    <p>Track daily attendance scans for your advisory sections.</p>
                </div>
            </div>
        </header>

        <form method="GET" class="filters-panel">
            <div class="filter-group">
                <label for="section_id">Advisory Section</label>
                <select name="section_id" id="section_id" class="form-control" onchange="this.form.submit()">
                    <option value="0">All Advisory Sections</option>
                    <?php foreach ($my_sections as $sec): ?>
                        <option value="<?php echo $sec['section_id']; ?>" <?php echo $selected_section === $sec['section_id'] ? 'selected' : ''; ?>>
                            Grade <?php echo $sec['grade_level'] . ' - ' . htmlspecialchars($sec['section_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="date">Report Date</label>
                <input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>" onchange="this.form.submit()">
            </div>
            <div style="align-self:flex-end">
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync"></i> Refresh</button>
            </div>
        </form>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_students; ?></h3>
                    <p>Total Advisory Students</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $present_count; ?></h3>
                    <p>Present Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-red"><i class="fas fa-user-xmark"></i></div>
                <div class="stat-info">
                    <h3><?php echo $absent_count; ?></h3>
                    <p>Absent Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-yellow"><i class="fas fa-chart-bar"></i></div>
                <div class="stat-info">
                    <h3><?php echo $attendance_rate; ?>%</h3>
                    <p>Attendance Rate</p>
                </div>
            </div>
        </div>

        <div class="grid-panels">
            <!-- Present List -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-check-circle" style="color:var(--accent)"></i>
                        <h2>Present Students (<?php echo $present_count; ?>)</h2>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Section</th>
                                <th>Time Scanned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($attendance_records) > 0): ?>
                                <?php foreach ($attendance_records as $rec): ?>
                                <tr>
                                    <td style="font-weight:600"><?php echo htmlspecialchars($rec['last_name'] . ', ' . $rec['first_name']); ?></td>
                                    <td>Grade <?php echo htmlspecialchars($rec['grade_level'] . ' - ' . $rec['section_name']); ?></td>
                                    <td><span class="badge"><?php echo date('h:i A', strtotime($rec['check_in_time'])); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">
                                        No check-in scans recorded for this day.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Absent List -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas fa-times-circle" style="color:var(--danger)"></i>
                        <h2>Absent Students (<?php echo $absent_count; ?>)</h2>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Section</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($absent_records) > 0): ?>
                                <?php foreach ($absent_records as $rec): ?>
                                <tr>
                                    <td style="font-weight:600"><?php echo htmlspecialchars($rec['last_name'] . ', ' . $rec['first_name']); ?></td>
                                    <td>Grade <?php echo htmlspecialchars($rec['grade_level'] . ' - ' . $rec['section_name']); ?></td>
                                    <td><span class="badge badge-red">ABSENT</span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center;padding:30px;color:var(--text-muted)">
                                        <?php echo $total_students > 0 ? "Perfect Attendance! All students present." : "No advisory students enrolled."; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
