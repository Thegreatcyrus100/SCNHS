import os

files = ['clearance.php', 'document_requests.php', 'sslg_elections.php', 'clinic.php']
template = """<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.html");
    exit();
}

echo "<h1>Work in Progress</h1>";
echo "<p>This module is currently under development.</p>";
echo "<a href='dashboard.php'>Back to Dashboard</a>";
?>"""

for f in files:
    with open('c:/xampp/htdocs/Final/student/' + f, 'w', encoding='utf-8') as file:
        file.write(template)
