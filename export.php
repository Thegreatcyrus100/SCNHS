<?php
$conn = new mysqli("localhost", "root", "", "enrollment_db");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=students.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['LRN', 'First Name', 'Last Name', 'Grade', 'Track', 'Strand']);

$result = $conn->query("SELECT lrn, first_name, last_name, grade, track, strand FROM students");

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
?>
