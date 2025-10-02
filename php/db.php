<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$host = "localhost";      // หรือ 127.0.0.1
$user = "kritsanai";           // ชื่อผู้ใช้ MySQL ของคุณ
$password = "kritsanai1234";           // รหัสผ่าน MySQL ของคุณ
$database = "bigproject"; // ชื่อฐานข้อมูล

// สร้างการเชื่อมต่อ
$conn = new mysqli($host, $user, $password, $database);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error);
}

// ตั้งค่า charset ให้เป็น UTF-8
$conn->set_charset("utf8mb4");
?>
