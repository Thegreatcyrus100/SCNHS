<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['submission_status'] = 'submitted';
$_POST['password'] = '12345678';
$_POST['strand'] = 'STEM';
require 'submit.php';
