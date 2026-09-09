<?php
/**
 * dropout_check.php — Dropout Risk Checker
 * ─────────────────────────────────────────
 * Admin tool that calls the hosted Flask AI API to assess a student's
 * dropout risk based on their attendance rate and grades average.
 *
 * URL: admin/dropout_check.php?lrn=XXXXXXXXXXXX
 */

session_start();
require_once dirname(__DIR__) . '/config.php';

// ── Auth Guard ──────────────────────────
if (!isset($_SESSION['registrar'])) {
    header('Location: ../admin/login.html');
    exit();
}

$conn = get_db_connection();
$lrn = isset($_GET['lrn']) ? $conn->real_escape_string(trim($_GET['lrn'])) : '';

// ── Fetch student from DB ───────────────
$student = null;
if ($lrn) {
    $stmt = $conn->prepare(
        "SELECT lrn, first_name, last_name, grade, strand, section FROM students WHERE lrn = ?"
    );
    $stmt->bind_param('s', $lrn);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();
}

// ── Handle form submission (manual input) ──
$prediction = null;
$api_error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lrn'])) {
    $post_lrn       = trim($_POST['lrn']);
    $attendance     = floatval($_POST['attendance_rate'] ?? 0);
    $grades         = floatval($_POST['grades_average'] ?? 0);

    // Call the Flask API
    $api_url = rtrim(AI_API_URL, '/') . '/predict';
    $payload = json_encode([
        'attendance_rate' => $attendance,
        'grades_average'  => $grades,
    ]);

    $ch = curl_init($api_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        $api_error = "Could not reach the AI API. Is it deployed on Render? Error: $curl_error";
    } elseif ($http_code !== 200) {
        $decoded = json_decode($response, true);
        $api_error = $decoded['error'] ?? "API returned HTTP $http_code.";
    } else {
        $prediction = json_decode($response, true);
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dropout Risk Check — SCNHS Admin</title>
    <link rel="icon" href="LOGO STA. CRUZ.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2e2e2e;
            --accent:  #e74c3c;
            --green:   #27ae60;
            --bg:      linear-gradient(135deg, #eef2f3, #8e9eab);
            --card-bg: #ffffff;
            --radius:  14px;
            --shadow:  0 8px 30px rgba(0,0,0,0.12);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }

        body {
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2.5rem;
            width: 100%;
            max-width: 520px;
            animation: fadeUp 0.4s ease;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1.8rem;
        }

        .card-header i { font-size: 1.8rem; color: var(--accent); }
        .card-header h1 { font-size: 1.5rem; color: #2c3e50; }

        .student-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem 1.2rem;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            color: #555;
            border-left: 4px solid var(--primary);
        }

        .student-info strong { color: #222; }

        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #444;
            margin-bottom: 0.3rem;
        }

        input[type="number"], input[type="hidden"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            margin-bottom: 1.2rem;
            transition: border-color 0.2s;
        }

        input[type="number"]:focus {
            border-color: var(--primary);
            outline: none;
        }

        .range-hint {
            font-size: 0.78rem;
            color: #888;
            margin-top: -1rem;
            margin-bottom: 1rem;
        }

        button[type="submit"] {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, #2e2e2e, #555);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.5px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0,0,0,0.2);
        }

        /* Result Badge */
        .result-box {
            margin-top: 1.8rem;
            border-radius: var(--radius);
            padding: 1.5rem;
            text-align: center;
            animation: fadeUp 0.3s ease;
        }

        .result-box.high {
            background: rgba(231, 76, 60, 0.08);
            border: 2px solid var(--accent);
        }

        .result-box.low {
            background: rgba(39, 174, 96, 0.08);
            border: 2px solid var(--green);
        }

        .risk-label {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .high .risk-label { color: var(--accent); }
        .low  .risk-label { color: var(--green); }

        .risk-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .high .risk-icon { color: var(--accent); }
        .low  .risk-icon { color: var(--green); }

        .confidence-bar-wrap {
            margin: 1rem auto 0;
            max-width: 280px;
        }

        .confidence-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.3rem;
        }

        .bar-track {
            background: #e9ecef;
            border-radius: 50px;
            height: 10px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            border-radius: 50px;
            transition: width 0.8s ease;
        }

        .high .bar-fill { background: var(--accent); }
        .low  .bar-fill { background: var(--green); }

        .error-box {
            margin-top: 1.5rem;
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--accent);
            border-radius: 10px;
            padding: 1rem;
            color: var(--accent);
            font-size: 0.9rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1.5rem;
            color: #555;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .back-link:hover { color: #222; }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-brain"></i>
            <h1>Dropout Risk Check</h1>
        </div>

        <?php if ($student): ?>
        <div class="student-info">
            <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong><br>
            LRN: <?= htmlspecialchars($student['lrn']) ?> &nbsp;|&nbsp;
            Grade <?= htmlspecialchars($student['grade']) ?> —
            <?= htmlspecialchars($student['strand']) ?>
            (<?= htmlspecialchars($student['section']) ?>)
        </div>
        <?php elseif ($lrn): ?>
        <div class="error-box"><i class="fas fa-exclamation-triangle"></i> Student with LRN <strong><?= htmlspecialchars($lrn) ?></strong> not found.</div>
        <?php endif; ?>

        <form method="POST" action="dropout_check.php">
            <input type="hidden" name="lrn" value="<?= htmlspecialchars($lrn ?? '') ?>">

            <label for="attendance_rate">
                <i class="fas fa-calendar-check"></i> Attendance Rate (%)
            </label>
            <input type="number" id="attendance_rate" name="attendance_rate"
                   min="0" max="100" step="0.1"
                   value="<?= htmlspecialchars($_POST['attendance_rate'] ?? '') ?>"
                   placeholder="e.g. 85" required>
            <p class="range-hint">Enter value between 0 – 100</p>

            <label for="grades_average">
                <i class="fas fa-chart-line"></i> Grades Average
            </label>
            <input type="number" id="grades_average" name="grades_average"
                   min="0" max="100" step="0.1"
                   value="<?= htmlspecialchars($_POST['grades_average'] ?? '') ?>"
                   placeholder="e.g. 78" required>
            <p class="range-hint">Enter value between 0 – 100</p>

            <button type="submit" id="analyzeBtn">
                <i class="fas fa-robot"></i> Analyze Dropout Risk
            </button>
        </form>

        <?php if ($api_error): ?>
        <div class="error-box">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($api_error) ?>
        </div>
        <?php endif; ?>

        <?php if ($prediction): ?>
        <?php
            $is_high     = $prediction['dropout_risk'] === 'High';
            $conf_pct    = round($prediction['confidence'] * 100);
            $risk_class  = $is_high ? 'high' : 'low';
            $risk_icon   = $is_high ? 'fa-exclamation-triangle' : 'fa-check-circle';
        ?>
        <div class="result-box <?= $risk_class ?>">
            <div class="risk-icon"><i class="fas <?= $risk_icon ?>"></i></div>
            <div class="risk-label"><?= $prediction['dropout_risk'] ?> Risk</div>
            <div class="confidence-bar-wrap">
                <p class="confidence-label">Confidence: <?= $conf_pct ?>%</p>
                <div class="bar-track">
                    <div class="bar-fill" style="width: <?= $conf_pct ?>%"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</body>
</html>
