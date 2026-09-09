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
$stmt = $conn->prepare("SELECT * FROM sections WHERE teacher_name LIKE ? ORDER BY grade_level, section_name");
$search_name = "%" . $teacher_name . "%";
$stmt->bind_param("s", $search_name);
$stmt->execute();
$sections_result = $stmt->get_result();
$sections = [];
$total_students = 0;

while ($row = $sections_result->fetch_assoc()) {
    $sec_id = $row['section_id'];
    // Count students in this section (prepared statement)
    $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM students WHERE section_id = ?");
    $count_stmt->bind_param("i", $sec_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['cnt'];
    $count_stmt->close();
    $row['student_count'] = $count;
    $total_students += $count;
    $sections[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | SCNHS</title>
    <link rel="icon" href="../image/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../static/dashboard_shared.css">
    <style>
        /* Teacher-specific overrides */
        .section-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}
        .section-card{background:rgba(0,0,0,0.2);border:1px solid var(--border);border-radius:12px;padding:20px;transition:all 0.3s}
        .section-card:hover{background:rgba(255,255,255,0.03);border-color:var(--primary);transform:translateY(-3px)}
        .sec-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
        .sec-title h3{font-size:1.2rem;font-weight:600;color:#fff;margin-bottom:4px}
        .sec-title span{font-size:0.85rem;color:var(--text-muted);background:rgba(255,255,255,0.05);padding:4px 8px;border-radius:4px;display:inline-block}
        .sec-code{font-family:monospace;background:rgba(59,130,246,0.1);color:var(--primary);padding:6px 10px;border-radius:6px;font-weight:bold;font-size:0.9rem}
        .sec-stats{display:flex;gap:16px;margin-bottom:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.05)}
        .sec-stat{display:flex;align-items:center;gap:8px;color:var(--text-muted);font-size:0.9rem}
        .sec-stat i{color:var(--accent)}
        .btn-view{display:block;width:100%;padding:10px;text-align:center;background:rgba(59,130,246,0.1);color:var(--primary);text-decoration:none;border-radius:8px;font-weight:600;transition:all 0.3s;border:1px solid rgba(59,130,246,0.2)}
        .btn-view:hover{background:var(--primary);color:#fff}
        .empty-state{grid-column:1/-1;text-align:center;padding:60px 20px;background:rgba(0,0,0,0.1);border-radius:12px;border:1px dashed var(--border)}
        .empty-state i{font-size:3rem;color:var(--text-muted);margin-bottom:16px;opacity:0.5;display:block}
        .empty-state h3{font-size:1.2rem;color:#fff;margin-bottom:8px}
        .empty-state p{color:var(--text-muted)}
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
            <a href="teacher_dashboard.php" class="nav-item active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="my_students.php" class="nav-item"><i class="fas fa-users"></i> My Students</a>
            <a href="attendance_report.php" class="nav-item"><i class="fas fa-clipboard-check"></i> Attendance</a>
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
                    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['teacher_name']); ?>!</h1>
                    <p><i class="fas fa-building" style="color:var(--primary);margin-right:6px"></i> Department: <?php echo htmlspecialchars($_SESSION['department'] ?? 'Faculty'); ?></p>
                </div>
            </div>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-layer-group"></i></div>
                <div class="stat-info">
                    <h3><?php echo count($sections); ?></h3>
                    <p>Advisory Sections</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_students; ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-chalkboard"></i>
                    <h2>My Advisory Classes</h2>
                </div>
            </div>
            
            <div class="section-grid">
                <?php if (count($sections) > 0): ?>
                    <?php foreach ($sections as $sec): ?>
                        <div class="section-card">
                            <div class="sec-header">
                                <div class="sec-title">
                                    <h3><?php echo htmlspecialchars($sec['section_name']); ?></h3>
                                    <span>Grade <?php echo htmlspecialchars($sec['grade_level']); ?></span>
                                </div>
                                <div class="sec-code" title="Section Code"><?php echo htmlspecialchars($sec['section_code']); ?></div>
                            </div>
                            <div class="sec-stats">
                                <div class="sec-stat">
                                    <i class="fas fa-users"></i>
                                    <?php echo $sec['student_count']; ?> Enrolled
                                </div>
                                <div class="sec-stat">
                                    <i class="fas fa-book"></i>
                                    <?php echo htmlspecialchars($sec['strand']); ?>
                                </div>
                            </div>
                            <a href="my_students.php?section_id=<?php echo $sec['section_id']; ?>" class="btn-view">View Class List <i class="fas fa-arrow-right" style="margin-left:4px;font-size:0.8rem"></i></a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No Sections Assigned</h3>
                        <p>You haven't been assigned as an adviser for any sections yet. If you believe this is an error, please contact the administrator.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
