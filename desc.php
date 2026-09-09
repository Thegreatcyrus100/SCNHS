<?php
require 'config.php';
$conn = get_db_connection();
$res = $conn->query("DESCRIBE grades");
while($r = $res->fetch_assoc()) echo $r['Field'] . " ";
