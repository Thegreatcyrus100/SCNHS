<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

try {
    $conn = get_db_connection();
    $sql = "SELECT id, title, content, published_date FROM announcements ORDER BY published_date DESC, created_at DESC LIMIT 10";
    $result = $conn->query($sql);

    $announcements = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $announcements]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
