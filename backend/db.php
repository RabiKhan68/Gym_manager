<?php

$host = "localhost";
$dbname = "fitness_management";
$username = "root";
$password = "srkthepro145@";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $dbname
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>