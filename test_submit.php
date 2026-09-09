<?php 
$_SERVER['REQUEST_METHOD'] = 'POST'; 
$_POST['submission_status'] = 'submitted'; 
$_POST['password'] = '12345678'; 
require 'submit.php';
