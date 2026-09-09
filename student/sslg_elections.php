<?php
/**
 * SSLG Elections — Student Portal
 * Secure 1-vote-per-position digital voting system for Supreme Secondary Learner Government
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

// Check if elections are enabled via settings
$settingStmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'enable_sslg_voting' LIMIT 1");
$votingEnabled = false;
if ($settingStmt && $row = $settingStmt->fetch_assoc()) {
    $votingEnabled = $row['setting_value'] === '1';
} else {
    // Default: allow if table is missing
    $votingEnabled = true;
}

$msg = $msgType = '';

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cast_vote']) && $votingEnabled) {
    $votes = $_POST['vote'] ?? [];
    if (!empty($votes)) {
        $errors = [];
        foreach ($votes as $position => $candidate_id) {
            $position     = trim($position);
            $candidate_id = intval($candidate_id);
            if (!$candidate_id) continue;
            // Check if already voted for this position
            $chk = $conn->prepare("SELECT id FROM sslg_votes WHERE student_id = ? AND position = ?");
            $chk->bind_param("is", $student_id, $position);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = "Already voted for {$position}.";
                $chk->close();
                continue;
            }
            $chk->close();
            $ins = $conn->prepare("INSERT INTO sslg_votes (student_id, candidate_id, position) VALUES (?, ?, ?)");
            $ins->bind_param("iis", $student_id, $candidate_id, $position);
            $ins->execute();
            $ins->close();
        }
        if (empty($errors)) {
            $msg = "Your votes have been successfully cast. Thank you for participating!";
            $msgType = 'success';
        } else {
            $msg = "Some votes were skipped because you already voted for those positions.";
            $msgType = 'warning';
        }
    }
}

// Fetch candidates grouped by position
$candidates = [];
$cres = $conn->query("SELECT * FROM sslg_candidates WHERE is_active = 1 ORDER BY position, full_name");
if ($cres) {
    while ($row = $cres->fetch_assoc()) {
        $candidates[$row['position']][] = $row;
    }
}

// Fetch what this student has already voted for
$myVotes = [];
$vres = $conn->prepare("SELECT position FROM sslg_votes WHERE student_id = ?");
$vres->bind_param("i", $student_id);
$vres->execute();
$vr = $vres->get_result();
while ($row = $vr->fetch_assoc()) $myVotes[] = $row['position'];
$vres->close();

$conn->close();

$hasVotedAll = !empty($candidates) && count($myVotes) >= count($candidates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SSLG Elections | SCNHS Student</title>
<link rel="icon" href="../image/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../static/dashboard_shared.css">
<style>
    .election-hero {
        background: linear-gradient(135deg, rgba(6,182,212,0.18) 0%, rgba(139,92,246,0.1) 100%);
        border: 1px solid rgba(6,182,212,0.3);
        border-radius: 20px; padding: 32px 40px; margin-bottom: 32px;
        text-align: center;
    }
    .election-hero .flag { font-size: 3.5rem; margin-bottom: 12px; }
    .election-hero h1 { font-size: 2rem; font-weight: 800; background: linear-gradient(90deg, #06b6d4, #8b5cf6); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    .election-hero p { color: var(--text-muted); font-size: 0.95rem; margin-top: 8px; }
    .election-status {
        display: inline-flex; align-items: center; gap: 8px; margin-top: 16px;
        padding: 8px 20px; border-radius: 30px; font-weight: 600; font-size: 0.9rem;
        background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10b981;
    }

    .position-section { margin-bottom: 36px; }
    .position-title {
        display: flex; align-items: center; gap: 10px;
        font-size: 1.1rem; font-weight: 700; color: #fff;
        padding: 12px 0; margin-bottom: 18px;
        border-bottom: 2px solid rgba(6,182,212,0.3);
    }
    .position-title i { color: #06b6d4; }
    .voted-badge {
        margin-left: auto; padding: 4px 12px; border-radius: 20px;
        background: rgba(16,185,129,0.1); color: #10b981; font-size: 0.8rem;
        border: 1px solid rgba(16,185,129,0.2);
    }

    .candidates-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
    .candidate-card {
        background: var(--bg-panel); border: 2px solid var(--border);
        border-radius: 16px; padding: 24px; cursor: pointer;
        transition: all 0.25s; display: flex; flex-direction: column; align-items: center;
        text-align: center; position: relative;
    }
    .candidate-card:hover { border-color: #06b6d4; transform: translateY(-4px); box-shadow: 0 8px 24px rgba(6,182,212,0.2); }
    .candidate-card.selected { border-color: #06b6d4; background: rgba(6,182,212,0.08); box-shadow: 0 0 0 3px rgba(6,182,212,0.15); }
    .candidate-card input[type=radio] { position: absolute; opacity: 0; pointer-events: none; }

    .candidate-avatar {
        width: 70px; height: 70px; border-radius: 50%;
        background: linear-gradient(135deg, #06b6d4, #8b5cf6);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem; color: #fff; font-weight: 700;
        margin-bottom: 14px; flex-shrink: 0;
    }
    .candidate-name { font-weight: 700; font-size: 1rem; color: #fff; margin-bottom: 4px; }
    .candidate-meta { font-size: 0.82rem; color: var(--text-muted); margin-bottom: 12px; }
    .candidate-platform {
        font-size: 0.82rem; color: var(--text-muted); line-height: 1.5;
        background: rgba(255,255,255,0.03); border-radius: 8px; padding: 10px;
        text-align: left; margin-top: auto;
    }
    .select-check {
        position: absolute; top: 12px; right: 12px; width: 24px; height: 24px;
        border-radius: 50%; background: #06b6d4; color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 0.8rem;
        opacity: 0; transition: opacity .2s;
    }
    .candidate-card.selected .select-check { opacity: 1; }

    .vote-submit-wrap { padding: 32px; text-align: center; border-top: 1px solid var(--border); margin-top: 8px; }
    .vote-btn {
        padding: 16px 48px; background: linear-gradient(135deg, #06b6d4, #8b5cf6);
        color: #fff; border: none; border-radius: 14px; font-family: inherit;
        font-weight: 700; font-size: 1.1rem; cursor: pointer; transition: all 0.3s;
        display: inline-flex; align-items: center; gap: 12px;
    }
    .vote-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(6,182,212,0.5); }
    .vote-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    .voted-banner {
        background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(6,182,212,0.1));
        border: 1px solid rgba(16,185,129,0.3); border-radius: 20px;
        padding: 40px; text-align: center;
    }
    .voted-banner i { font-size: 4rem; color: #10b981; margin-bottom: 16px; display: block; }
    .voted-banner h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; }
    .voted-banner p { color: var(--text-muted); }

    .no-elections {
        background: var(--bg-panel); border: 1px solid var(--border);
        border-radius: 20px; padding: 60px; text-align: center;
    }
    .no-elections i { font-size: 3.5rem; opacity: 0.2; margin-bottom: 16px; display: block; }
    .alert-msg { display: flex; align-items: center; gap: 10px; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; font-weight: 500; }
    .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981; }
    .alert-warning { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #f59e0b; }
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
        <a href="sslg_elections.php" class="nav-item active"><i class="fas fa-vote-yea"></i> SSLG Elections</a>
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
                <h1>SSLG Elections</h1>
                <p>Supreme Secondary Learner Government</p>
            </div>
        </div>
        <div class="user-profile">
            <i class="fas fa-user-circle"></i>
            <span><?php echo e($student['first_name'].' '.$student['last_name']); ?></span>
        </div>
    </header>

    <!-- Hero -->
    <div class="election-hero">
        <div class="flag">🗳️</div>
        <h1>SSLG General Elections</h1>
        <p>Santa Cruz National High School &nbsp;|&nbsp; Supreme Secondary Learner Government</p>
        <div class="election-status">
            <i class="fas fa-circle" style="font-size:0.5rem"></i>
            <?php echo $votingEnabled ? 'Voting is Open' : 'Voting is Closed'; ?>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert-msg alert-<?php echo $msgType; ?>">
        <i class="fas fa-<?php echo $msgType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <?php echo e($msg); ?>
    </div>
    <?php endif; ?>

    <?php if (!$votingEnabled): ?>
    <div class="no-elections">
        <i class="fas fa-ban"></i>
        <p style="font-size:1.2rem;font-weight:700;color:#fff;margin-bottom:8px">Voting is Currently Closed</p>
        <p>The school administrator has not yet opened the SSLG voting period. Please check back later or wait for official announcements.</p>
    </div>

    <?php elseif ($hasVotedAll): ?>
    <div class="voted-banner">
        <i class="fas fa-check-circle"></i>
        <h2>Your Votes Have Been Cast!</h2>
        <p>Thank you, <strong><?php echo e($student['first_name']); ?></strong>, for participating in the SSLG elections. Your votes are recorded securely and cannot be changed.</p>
        <p style="margin-top:12px;font-size:0.88rem">Results will be announced after the voting period closes.</p>
    </div>

    <?php elseif (empty($candidates)): ?>
    <div class="no-elections">
        <i class="fas fa-user-times"></i>
        <p style="font-size:1.2rem;font-weight:700;color:#fff;margin-bottom:8px">No Candidates Listed Yet</p>
        <p>No candidates have been added yet. Please check back once the list of official candidates has been published by the school.</p>
    </div>

    <?php else: ?>
    <div style="background:var(--bg-panel);border:1px solid var(--border);border-radius:20px;overflow:hidden;margin-bottom:24px">
        <form method="POST" action="sslg_elections.php" id="voteForm">
            <div style="padding:28px 32px">
                <?php foreach ($candidates as $position => $cands):
                    $hasVotedThisPos = in_array($position, $myVotes);
                ?>
                <div class="position-section">
                    <div class="position-title">
                        <i class="fas fa-user-tie"></i>
                        <?php echo e($position); ?>
                        <?php if ($hasVotedThisPos): ?>
                        <span class="voted-badge"><i class="fas fa-check"></i> Voted</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasVotedThisPos): ?>
                    <p style="color:var(--text-muted);font-size:0.9rem;padding:12px 0;font-style:italic">
                        <i class="fas fa-lock" style="margin-right:6px"></i>You have already cast your vote for this position.
                    </p>
                    <?php else: ?>
                    <div class="candidates-grid">
                        <?php foreach ($cands as $c): ?>
                        <label class="candidate-card" for="vote_<?php echo $c['id']; ?>" onclick="this.classList.toggle('selected')">
                            <input type="radio" name="vote[<?php echo e($position); ?>]" id="vote_<?php echo $c['id']; ?>" value="<?php echo $c['id']; ?>" required>
                            <div class="select-check"><i class="fas fa-check"></i></div>
                            <div class="candidate-avatar"><?php echo strtoupper(substr($c['full_name'],0,1)); ?></div>
                            <div class="candidate-name"><?php echo e($c['full_name']); ?></div>
                            <div class="candidate-meta">
                                <?php echo $c['strand'] ? e($c['grade_level']).' | '.e($c['strand']) : 'SCNHS'; ?>
                            </div>
                            <?php if ($c['platform']): ?>
                            <div class="candidate-platform">"<?php echo e($c['platform']); ?>"</div>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="vote-submit-wrap">
                <p style="color:var(--text-muted);margin-bottom:20px;font-size:0.9rem"><i class="fas fa-shield-alt" style="color:#06b6d4;margin-right:6px"></i>Your vote is anonymous, secure, and final. You can only vote once per position.</p>
                <button type="submit" name="cast_vote" class="vote-btn" id="voteBtn">
                    <i class="fas fa-vote-yea"></i> Cast My Votes
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</main>
<script>
// Highlight selected candidate cards
document.querySelectorAll('.candidate-card').forEach(card => {
    card.addEventListener('click', () => {
        const name = card.querySelector('input').getAttribute('name');
        document.querySelectorAll(`input[name="${name}"]`).forEach(inp => {
            inp.closest('.candidate-card').classList.remove('selected');
        });
        card.classList.add('selected');
    });
});
</script>
<script src="../static/sidebar.js?v=2"></script>
</body>
</html>