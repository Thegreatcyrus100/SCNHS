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
$my_section_ids = [0]; // fallback to avoid empty SQL array

while ($row = $sections_result->fetch_assoc()) {
    $my_sections[] = $row;
    $my_section_ids[] = $row['section_id'];
}
$sec_stmt->close();

$selected_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ensure selected section belongs to this teacher
if ($selected_section > 0 && !in_array($selected_section, $my_section_ids)) {
    die("Error: Access denied to this section.");
}

// Build query
$query_parts = [];
$params = [];
$types = '';

if ($selected_section > 0) {
    $query_parts[] = "s.section_id = ?";
    $params[] = $selected_section;
    $types .= 'i';
} else {
    // Show all students in any of this teacher's sections
    $placeholders = implode(',', array_fill(0, count($my_section_ids), '?'));
    $query_parts[] = "s.section_id IN ($placeholders)";
    foreach ($my_section_ids as $id) {
        $params[] = $id;
        $types .= 'i';
    }
}

if (!empty($search)) {
    $query_parts[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = "";
if (count($query_parts) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $query_parts);
}

$sql = "SELECT s.*, sec.section_name, sec.grade_level, sec.strand 
        FROM students s 
        LEFT JOIN sections sec ON s.section_id = sec.section_id 
        $where_clause 
        ORDER BY sec.grade_level, sec.section_name, s.last_name, s.first_name";

$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students_result = $stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students | Teacher Portal</title>
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
        .btn-detail{background:rgba(59,130,246,0.1);color:var(--primary);border:1px solid rgba(59,130,246,0.2);padding:6px 12px;font-size:0.8rem;font-weight:600}
        .btn-detail:hover{background:var(--primary);color:#fff}

        .panel{background:var(--bg-panel);backdrop-filter:var(--glass);border:1px solid var(--border);border-radius:16px;padding:24px;margin-bottom:30px}
        .panel-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .panel-title{display:flex;align-items:center;gap:12px}
        .panel-title h2{font-size:1.3rem;font-weight:600}.panel-title i{color:var(--primary);font-size:1.2rem}
        
        .table-responsive{overflow-x:auto}
        table{width:100%;border-collapse:collapse;text-align:left}
        th{padding:16px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:1px;font-weight:600;white-space:nowrap}
        td{padding:16px;border-bottom:1px solid rgba(255,255,255,.03);color:#e5e7eb;vertical-align:middle;white-space:nowrap}
        tr:hover td{background:rgba(255,255,255,.02)}
        
        .badge{font-family:monospace;background:rgba(59,130,246,.1);color:var(--primary);padding:4px 8px;border-radius:4px;font-weight:bold;font-size:0.8rem}
        .badge-strand{background:rgba(16,185,129,.1);color:var(--accent)}
        
        /* Modal */
        .modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:all 0.3s;z-index:100}
        .modal.open{opacity:1;pointer-events:auto}
        .modal-content{background:var(--bg-dark);border:1px solid var(--border);border-radius:16px;width:90%;max-width:700px;max-height:85vh;overflow-y:auto;padding:30px;position:relative;transform:scale(0.9);transition:all 0.3s}
        .modal.open .modal-content{transform:scale(1)}
        .modal-close{position:absolute;top:20px;right:20px;background:none;border:none;color:var(--text-muted);font-size:1.3rem;cursor:pointer;transition:color 0.3s}
        .modal-close:hover{color:#fff}
        .modal-header{margin-bottom:24px;border-bottom:1px solid var(--border);padding-bottom:16px}
        .modal-header h2{font-size:1.5rem;font-weight:700;color:#fff}
        .modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .modal-sec-title{grid-column:1/-1;font-size:1rem;font-weight:700;color:var(--primary);margin-top:10px;border-bottom:1px solid rgba(255,255,255,0.05);padding-bottom:6px}
        .info-item{display:flex;flex-direction:column;gap:4px}
        .info-item label{font-size:0.75rem;color:var(--text-muted);text-transform:uppercase}
        .info-item span{font-size:0.95rem;color:#fff;font-weight:500}

        @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:1024px){}
        @media(max-width:600px){.modal-grid{grid-template-columns:1fr}}
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
            <a href="my_students.php" class="nav-item active"><i class="fas fa-users"></i> My Students</a>
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
                    <h1>Advisory Class Students</h1>
                    <p>View and manage students enrolled under your advisory sections.</p>
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
                            Grade <?php echo $sec['grade_level'] . ' - ' . htmlspecialchars($sec['section_name']); ?> (<?php echo htmlspecialchars($sec['strand']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="search">Search Student</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Search by name or LRN..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div style="align-self:flex-end;display:flex;gap:10px">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                <?php if ($selected_section > 0): ?>
                    <a href="../admin/export_section.php?section_id=<?php echo $selected_section; ?>" class="btn btn-success" target="_blank"><i class="fas fa-file-excel"></i> Export Excel</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="panel">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fas fa-user-graduate"></i>
                    <h2>Students List (<?php echo count($students); ?>)</h2>
                </div>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>LRN</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Section</th>
                            <th>Strand</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($students) > 0): ?>
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td><span class="badge"><?php echo htmlspecialchars($student['lrn']); ?></span></td>
                                <td style="font-weight:600"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                <td><?php echo htmlspecialchars($student['age']); ?></td>
                                <td>Grade <?php echo htmlspecialchars($student['grade_level'] . ' - ' . $student['section_name']); ?></td>
                                <td><span class="badge badge-strand"><?php echo htmlspecialchars($student['strand']); ?></span></td>
                                <td style="text-align:right">
                                    <button class="btn btn-detail" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:40px;color:var(--text-muted)">
                                    <i class="fas fa-folder-open" style="font-size:2rem;margin-bottom:12px;opacity:.5;display:block"></i>
                                    No students found matching filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Student Detail Modal -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
            <div class="modal-header">
                <h2 id="m_fullname">Student Name</h2>
            </div>
            <div class="modal-grid">
                <div class="modal-sec-title"><i class="fas fa-info-circle"></i> Personal Information</div>
                <div class="info-item"><label>LRN</label><span id="m_lrn"></span></div>
                <div class="info-item"><label>Gender</label><span id="m_gender"></span></div>
                <div class="info-item"><label>Age</label><span id="m_age"></span></div>
                <div class="info-item"><label>Birth Date</label><span id="m_dob"></span></div>
                <div class="info-item"><label>Birth Place</label><span id="m_birthplace"></span></div>
                <div class="info-item"><label>Contact</label><span id="m_contact"></span></div>

                <div class="modal-sec-title"><i class="fas fa-map-marker-alt"></i> Address</div>
                <div class="info-item" style="grid-column:1/-1;"><label>Current Address</label><span id="m_address"></span></div>

                <div class="modal-sec-title"><i class="fas fa-users"></i> Parents / Guardians</div>
                <div class="info-item"><label>Father Name</label><span id="m_father"></span></div>
                <div class="info-item"><label>Mother Name</label><span id="m_mother"></span></div>
                <div class="info-item" style="grid-column:1/-1;"><label>Guardian Name</label><span id="m_guardian"></span></div>
                <div class="info-item"><label>Guardian/Parent Contact</label><span id="m_guardian_contact"></span></div>

                <div class="modal-sec-title"><i class="fas fa-laptop-code"></i> Other Information</div>
                <div class="info-item"><label>Learning Modality</label><span id="m_modality"></span></div>
                <div class="info-item"><label>4Ps Beneficiary</label><span id="m_4ps"></span></div>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('detailModal');

        function viewDetails(student) {
            document.getElementById('m_fullname').innerText = student.last_name + ', ' + student.first_name + ' ' + (student.middle_name || '') + ' ' + (student.extension_name || '');
            document.getElementById('m_lrn').innerText = student.lrn;
            document.getElementById('m_gender').innerText = student.gender || 'N/A';
            document.getElementById('m_age').innerText = student.age || 'N/A';
            document.getElementById('m_dob').innerText = student.dob || 'N/A';
            document.getElementById('m_birthplace').innerText = student.birthplace || 'N/A';
            document.getElementById('m_contact').innerText = student.contact || 'N/A';
            
            const addr = [student.house_number, student.street, student.barangay, student.municipality, student.province].filter(Boolean).join(', ');
            document.getElementById('m_address').innerText = addr || 'N/A';
            
            document.getElementById('m_father').innerText = [student.father_first_name, student.father_middle_name, student.father_last_name].filter(Boolean).join(' ') || 'N/A';
            document.getElementById('m_mother').innerText = [student.mother_first_name, student.mother_middle_name, student.mother_last_name].filter(Boolean).join(' ') || 'N/A';
            document.getElementById('m_guardian').innerText = [student.guardian_first_name, student.guardian_middle_name, student.guardian_last_name].filter(Boolean).join(' ') || 'N/A';
            document.getElementById('m_guardian_contact').innerText = student.contact_guardian_parent || 'N/A';
            document.getElementById('m_modality').innerText = student.learning_modality || 'N/A';
            document.getElementById('m_4ps').innerText = student.FourPs || 'N/A';

            modal.classList.add('open');
        }

        function closeModal() {
            modal.classList.remove('open');
        }

        // Close on click outside modal content
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
    </script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>
