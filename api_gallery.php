<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

try {
    $conn = get_db_connection();
    $sql = "SELECT id, title, category, image_path FROM gallery ORDER BY created_at DESC LIMIT 20";
    $result = $conn->query($sql);

    $gallery = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $gallery[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $gallery]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
