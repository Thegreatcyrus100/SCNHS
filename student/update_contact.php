<?php
/**
 * Student Portal — Update Contact Information
 * Handles AJAX POST to update student contact & address details
 */
session_start();

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

require_once dirname(__DIR__) . '/config.php';
$conn = get_db_connection();


$student_id = $_SESSION['student_id'];

// Sanitize inputs
$contact    = $conn->real_escape_string(trim($_POST['contact'] ?? ''));
$province   = $conn->real_escape_string(trim($_POST['province'] ?? ''));
$municipality = $conn->real_escape_string(trim($_POST['municipality'] ?? ''));
$barangay   = $conn->real_escape_string(trim($_POST['barangay'] ?? ''));
$street     = $conn->real_escape_string(trim($_POST['street'] ?? ''));
$house_number = $conn->real_escape_string(trim($_POST['house_number'] ?? ''));
$zip_code   = $conn->real_escape_string(trim($_POST['zip_code'] ?? ''));

// Validate contact number
if (!empty($contact) && !preg_match('/^[0-9+\-\s]{7,20}$/', $contact)) {
    echo json_encode(['error' => 'Invalid contact number format']);
    $conn->close();
    exit();
}

$stmt = $conn->prepare(
    "UPDATE students SET contact = ?, province = ?, municipality = ?, barangay = ?, street = ?, house_number = ?, zip_code = ? WHERE id = ?"
);

$stmt->bind_param("sssssssi", $contact, $province, $municipality, $barangay, $street, $house_number, $zip_code, $student_id);

if ($stmt->execute()) {
    echo json_encode(['success' => 'Contact information updated successfully']);
} else {
    echo json_encode(['error' => 'Failed to update: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
