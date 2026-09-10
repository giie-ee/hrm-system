<?php

date_default_timezone_set("Africa/Lusaka");

$host = "localhost";
$username = "root";
$password = "";
$database = "hrms_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>