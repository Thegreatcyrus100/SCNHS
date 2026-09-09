<?php
session_start();

// Redirect to login page if the registrar is NOT logged in
if (!isset($_SESSION['registrar'])) {
    header("Location: ../admin/login.html");
    exit();
}

// Auto logout after 15 minutes of inactivity
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 900)) {
    session_unset();
    session_destroy();
    header("Location: ../admin/login.html");
    exit();
} else {
    $_SESSION['login_time'] = time(); // Reset session time
}

// Connect to the database
require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();

// Fetch student data
// Handle Approve / Reject actions
if (isset($_GET['approve_lrn'])) {
    $approve_lrn = $_GET['approve_lrn'];
    $stmt = $conn->prepare("UPDATE students SET status = 'approved' WHERE lrn = ?");
    $stmt->bind_param("s", $approve_lrn);
    $stmt->execute();
    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

if (isset($_GET['reject_lrn'])) {
    $reject_lrn = $_GET['reject_lrn'];
    $stmt = $conn->prepare("UPDATE students SET status = 'rejected' WHERE lrn = ?");
    $stmt->bind_param("s", $reject_lrn);
    $stmt->execute();
    $stmt->close();
    header("Location: dashboard.php");
    exit();
}

// Fetch student data
$stmt = $conn->prepare("SELECT lrn, first_name, last_name, grade, track, strand, section, status FROM students ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();

// Fetch stats for analytics cards
$total_students = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()['count'];
$total_sections = $conn->query("SELECT COUNT(*) AS count FROM sections")->fetch_assoc()['count'];
$recent_enrolls = $conn->query("SELECT COUNT(*) AS count FROM students WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../image/logo.png" type="image/x-icon">
    <title>Registrar Dashboard | SCNHS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../static/dashboard_shared.css">
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../image/logo.png" alt="SCNHS Logo">
            <div class="sidebar-title">
                <h2>SCNHS</h2>
                <span>Registrar</span>
            </div>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-item active"><i class="fas fa-chart-pie"></i> Dashboard</a>
            <a href="../index.html" class="nav-item" target="_blank"><i class="fas fa-globe"></i> View Website</a>
        </div>
        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item nav-logout"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
                <div>
                    <h1>Registrar Overview</h1>
                    <p>Manage student records and process enrollments.</p>
                </div>
            </div>
            <div class="user-profile">
                <i class="fas fa-user-circle"></i>
                <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['registrar']); ?></span>
            </div>
        </header>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_students); ?></h3>
                    <p>Total Enrolled</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-purple"><i class="fas fa-layer-group"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_sections); ?></h3>
                    <p>Active Sections</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-green"><i class="fas fa-user-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($recent_enrolls); ?></h3>
                    <p>New Enrollments (7 Days)</p>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-table"></i>
                    <h2>Student Records Directory</h2>
                </div>
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchBox" placeholder="Search by name, LRN, grade..." onkeyup="searchTable()">
                </div>
            </div>
            
            <div class="table-responsive">
                <table id="studentTable">
                    <thead>
                        <tr>
                            <th>LRN</th>
                            <th>Student Name</th>
                            <th>Grade</th>
                            <th>Track & Strand</th>
                            <th>Section</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="lrn-badge"><?php echo htmlspecialchars($row['lrn']); ?></span></td>
                                    <td style="font-weight: 500;"><?php echo htmlspecialchars($row['last_name'] . ", " . $row['first_name']); ?></td>
                                    <td>Grade <?php echo htmlspecialchars($row['grade']); ?></td>
                                    <td>
                                        <div style="font-size: 0.9em; color: var(--text-muted);"><?php echo htmlspecialchars($row['track']); ?></div>
                                        <div><?php echo htmlspecialchars($row['strand']); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['section'])): ?>
                                            <span style="background: rgba(16,185,129,0.1); color: var(--accent); padding: 4px 8px; border-radius: 12px; font-size: 0.85em; font-weight: 600;">
                                                <?php echo htmlspecialchars($row['section']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.85em; font-style: italic;">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $status = $row['status'] ?? 'pending';
                                            if ($status === 'approved') {
                                                echo '<span style="color: var(--accent); font-weight: 600;"><i class="fas fa-check-circle"></i> Approved</span>';
                                            } elseif ($status === 'rejected') {
                                                echo '<span style="color: var(--danger); font-weight: 600;"><i class="fas fa-times-circle"></i> Rejected</span>';
                                            } else {
                                                echo '<span style="color: var(--warning); font-weight: 600;"><i class="fas fa-clock"></i> Pending</span>';
                                            }
                                        ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="action-btns" style="justify-content: flex-end;">
                                            <?php if ($status === 'pending'): ?>
                                                <a href="dashboard.php?approve_lrn=<?php echo $row['lrn']; ?>" class="btn" style="background: rgba(16, 185, 129, 0.1); color: var(--accent); border: 1px solid rgba(16, 185, 129, 0.2);" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="dashboard.php?reject_lrn=<?php echo $row['lrn']; ?>" class="btn" style="background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2);" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="print.php?lrn=<?php echo $row['lrn']; ?>" class="btn btn-print" title="Print Form">
                                                <i class="fas fa-print"></i> Print
                                            </a>
                                            <a href="dropout_check.php?lrn=<?php echo $row['lrn']; ?>" class="btn btn-risk" title="Dropout Risk Analysis">
                                                <i class="fas fa-exclamation-triangle"></i> Risk
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px;">
                                    <i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.5; display: block;"></i>
                                    No student records found in the system.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // Real-time table search
        function searchTable() {
            let input = document.getElementById("searchBox");
            let filter = input.value.toUpperCase();
            let table = document.getElementById("studentTable");
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                let tds = tr[i].getElementsByTagName("td");
                
                // Search across first 5 columns (LRN, Name, Grade, Track/Strand, Section)
                for (let j = 0; j < 5; j++) {
                    if (tds[j]) {
                        let txtValue = tds[j].textContent || tds[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none";
            }
        }

    </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>

