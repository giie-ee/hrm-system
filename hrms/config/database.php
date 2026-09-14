<?php

date_default_timezone_set("Africa/Lusaka");

$host = getenv("DB_HOST") ?: "localhost";
$port = getenv("DB_PORT") ?: "3306";
$username = getenv("DB_USERNAME") ?: "root";
$password = getenv("DB_PASSWORD") ?: "";
$database = getenv("DB_DATABASE") ?: "hrms_db";

$conn = new mysqli($host, $username, $password, $database, (int)$port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>
