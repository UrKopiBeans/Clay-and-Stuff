<?php

// gumagawa ng $conn (mysqli connection) — ito ang ginagamit ng halos lahat ng pages, kaya required_once ito bago ang kahit anong DB query
require_once __DIR__ . "/../helpers/env_config.php";

$host = "localhost";
$username = "root";
$password = "";
$database = "figurify_db";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>
