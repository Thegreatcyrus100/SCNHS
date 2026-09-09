<?php
session_start();
ob_start(); // Buffer any stray output
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
$conn = get_db_connection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check submission status
    if (!isset($_POST['submission_status']) || $_POST['submission_status'] !== 'submitted') {
        die(json_encode(['error' => "Invalid form submission"]));
    }

    // Collect form data
    $grade = $conn->real_escape_string($_POST['grade'] ?? '');
    $track = $conn->real_escape_string(strtoupper($_POST['track'] ?? ''));
    $strand = $conn->real_escape_string(strtoupper($_POST['strand'] ?? ''));
    $learning_modality = $conn->real_escape_string(strtoupper($_POST['learning_modality'] ?? ''));
    $school_year = $conn->real_escape_string($_POST['school_year'] ?? '');
    $semester = $conn->real_escape_string($_POST['semester'] ?? '');
    $lrn = $conn->real_escape_string($_POST['lrn'] ?? '');
    
    // Hash the password for secure storage
    $raw_password = $_POST['password'] ?? '';
    if (strlen($raw_password) < 6) {
        die(json_encode(['error' => "Password must be at least 6 characters long."]));
    }
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);
    $first_name = $conn->real_escape_string(strtoupper($_POST['first_name'] ?? ''));
    $middle_name = $conn->real_escape_string(strtoupper($_POST['middle_name'] ?? ''));
    $last_name = $conn->real_escape_string(strtoupper($_POST['last_name'] ?? ''));
    $extension_name = $conn->real_escape_string(strtoupper($_POST['extension_name'] ?? ''));
    $disability = $conn->real_escape_string(strtoupper($_POST['disability'] ?? ''));
    $ip_community = $conn->real_escape_string(strtoupper($_POST['ip_community'] ?? ''));
    $FourPs = $conn->real_escape_string(strtoupper($_POST['FourPs'] ?? ''));
    $dob = $conn->real_escape_string($_POST['dob'] ?? '');
    $age = $conn->real_escape_string($_POST['age'] ?? '');
    $gender = $conn->real_escape_string(mb_strtoupper($_POST['gender'] ?? ''));
    $birthplace = $conn->real_escape_string(mb_strtoupper($_POST['birthplace'] ?? ''));
    $indigenous = $conn->real_escape_string(mb_strtoupper($_POST['indigenous'] ?? ''));
    $mother_tongue = $conn->real_escape_string(mb_strtoupper($_POST['mother_tongue'] ?? ''));
    $contact = $conn->real_escape_string($_POST['contact'] ?? '');

    $province = $conn->real_escape_string($_POST['province'] ?? '');
    $municipality = $conn->real_escape_string($_POST['municipality'] ?? '');
    $barangay = $conn->real_escape_string($_POST['barangay'] ?? '');
    $street = $conn->real_escape_string($_POST['street'] ?? '');
    $house_number = $conn->real_escape_string($_POST['house_number'] ?? '');
    $zip_code = $conn->real_escape_string($_POST['zip_code'] ?? '');

    $father_last_name = $conn->real_escape_string($_POST['father_last_name'] ?? '');
    $father_first_name = $conn->real_escape_string($_POST['father_first_name'] ?? '');
    $father_middle_name = $conn->real_escape_string($_POST['father_middle_name'] ?? '');
    $mother_last_name = $conn->real_escape_string($_POST['mother_last_name'] ?? '');
    $mother_first_name = $conn->real_escape_string($_POST['mother_first_name'] ?? '');
    $mother_middle_name = $conn->real_escape_string($_POST['mother_middle_name'] ?? '');
    $guardian_last_name = $conn->real_escape_string($_POST['guardian_last_name'] ?? '');
    $guardian_first_name = $conn->real_escape_string($_POST['guardian_first_name'] ?? '');
    $guardian_middle_name = $conn->real_escape_string($_POST['guardian_middle_name'] ?? '');
    $contact_guardian_parent = $conn->real_escape_string($_POST['contact_guardian_parent'] ?? '');

    $is_returning_or_transfer = $conn->real_escape_string($_POST['is_returning_or_transfer'] ?? '');
    $last_grade_level = $conn->real_escape_string($_POST['last_grade_level'] ?? '');
    $last_school_year = $conn->real_escape_string($_POST['last_school_year'] ?? '');
    $previous_school = $conn->real_escape_string($_POST['previous_school'] ?? '');
    $previous_school_id = $conn->real_escape_string($_POST['previous_school_id'] ?? '');

    try {
        // ✅ AUTOMATIC SECTION ASSIGNMENT
        $sections = ["A", "B", "C"]; // Three sections per strand
        $max_students_per_section = 30; // Maximum students per section

        // Check which section has the least students for the selected strand
        $sql_section = "SELECT section, COUNT(*) as student_count 
                        FROM students 
                        WHERE strand = ? 
                        GROUP BY section 
                        ORDER BY student_count ASC 
                        LIMIT 1";
        $stmt_section = $conn->prepare($sql_section);
        $stmt_section->bind_param("s", $strand);
        $stmt_section->execute();
        $result = $stmt_section->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['student_count'] < $max_students_per_section) {
                $section = $row['section']; // Assign the least populated section
            } else {
                // If all sections are full, randomly assign one
                $section = $strand . " " . $sections[array_rand($sections)];
            }
        } else {
            // If no students are enrolled yet, assign a random section
            $section = $strand . " " . $sections[array_rand($sections)];
        }

        $stmt_section->close();

        // ✅ INSERT INTO DATABASE
        $stmt = $conn->prepare("INSERT INTO students 
        (grade, track, strand, section, learning_modality, school_year, semester, lrn, password, first_name, middle_name, last_name, extension_name, disability, ip_community, FourPs, dob, age, gender, birthplace, indigenous, mother_tongue, contact, 
        province, municipality, barangay, street, house_number, zip_code, 
        father_last_name, father_first_name, father_middle_name, 
        mother_last_name, mother_first_name, mother_middle_name, 
        guardian_last_name, guardian_first_name, guardian_middle_name, contact_guardian_parent, 
        is_returning_or_transfer, last_grade_level, last_school_year, previous_school, previous_school_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            die(json_encode(['error' => "SQL Preparation Error: " . $conn->error]));
        }

        $stmt->bind_param("isssssssssssssssssssssssssssssssssssssssssss", // 1 int + 43 strings = 44 total
            $grade, $track, $strand, $section, $learning_modality, $school_year, $semester, $lrn, $hashed_password, $first_name, $middle_name, $last_name, $extension_name, $disability, $ip_community, $FourPs, $dob, $age, $gender, $birthplace, $indigenous, $mother_tongue, $contact, 
            $province, $municipality, $barangay, $street, $house_number, $zip_code, 
            $father_last_name, $father_first_name, $father_middle_name, 
            $mother_last_name, $mother_first_name, $mother_middle_name, 
            $guardian_last_name, $guardian_first_name, $guardian_middle_name, $contact_guardian_parent, 
            $is_returning_or_transfer, $last_grade_level, $last_school_year, $previous_school, $previous_school_id
        );

        if ($stmt->execute()) {
            ob_end_clean(); // Discard any stray output
            echo json_encode(['success' => "Registration successful! You have been assigned to section: $section"]);
        } else {
            die(json_encode(['error' => "Database Error: " . $stmt->error]));
        }
        
        $stmt->close();
    } catch (Exception $e) {
        if ($e->getCode() == 1062) {
            die(json_encode(['error' => "The LRN provided is already registered."]));
        }
        die(json_encode(['error' => "Database Error: " . $e->getMessage()]));
    }
    $conn->close();
    exit();
} else {
    die(json_encode(['error' => "Invalid request method"]));
}
?>