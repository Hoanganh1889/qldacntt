<?php
$host = "localhost";
$user = "root";
$pass = "";       
$db   = "qldacntt";

$conn = new mysqli($host, $user, $pass, $db);


if ($conn->connect_error) {
    die("❌ Lỗi kết nối CSDL: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
