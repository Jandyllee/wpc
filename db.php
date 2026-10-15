<?php
$conn = new mysqli("sql104.infinityfree.com", "if0_43160101", "Westprime1234", "if0_43160101_wpc");

if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => 'Database connection failed']));
}

$conn->set_charset("utf8mb4");