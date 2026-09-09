<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$conn = get_db_connection();
$events = [];

// Fetch the latest 6 events, prioritizing upcoming ones
$sql = "SELECT id, event_title, event_date, description FROM calendar_events ORDER BY event_date DESC LIMIT 6";
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

$conn->close();

echo json_encode(['success' => true, 'events' => $events]);
