<?php
$host = "localhost";
$user = "kritsanai";
$pass = "kritsanai1234";
$db   = "bigproject";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: ".$conn->connect_error);
$conn->set_charset("utf8mb4"); // ตั้ง charset


if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}
?>
