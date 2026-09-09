<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

try {
    $conn = get_db_connection();
    $sql = "SELECT id, title, description, year, icon FROM achievements ORDER BY created_at DESC LIMIT 10";
    $result = $conn->query($sql);

    $achievements = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $achievements[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $achievements]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
