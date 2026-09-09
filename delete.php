<?php
$conn = new mysqli("localhost", "root", "", "enrollment_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$lrn = $_GET['lrn'];
$sql = "DELETE FROM students WHERE lrn = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL Preparation Error: " . $conn->error);
}

$stmt->bind_param("s", $lrn);

if ($stmt->execute()) {
    header("Location: dashboard.php");
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
