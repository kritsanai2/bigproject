<?php
// includes/auth.php
// ----------------------
// ใช้ตรวจสอบว่า user ได้ login แล้วหรือยัง
// ถ้าไม่ login จะ redirect ไปที่ login.php

// เริ่มต้น session (พร้อมตั้งค่า security)
if (session_status() === PHP_SESSION_NONE) {
    // ป้องกัน session fixation
    ini_set('session.use_strict_mode', 1);

    // ป้องกันการเข้าถึง cookie ผ่าน JavaScript
    ini_set('session.cookie_httponly', 1);

    // ถ้าเว็บคุณรันบน HTTPS ให้เปิดบรรทัดนี้
    // ini_set('session.cookie_secure', 1);

    session_start();
}

// ตรวจสอบการ login
if (!isset($_SESSION['user_id'])) {
    // redirect ไปหน้า login ถ้ายังไม่ได้ล็อกอิน
    header("Location: login.php");
    exit();
}

/**
 * ฟังก์ชันตรวจสอบ role (เช่น admin, user)
 * ถ้าไม่ตรง role ที่ต้องการ จะขึ้น error 403
 */
function require_role($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        http_response_code(403);
        echo "<h3>🚫 คุณไม่มีสิทธิ์เข้าถึงหน้านี้</h3>";
        exit();
    }
}
