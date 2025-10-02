<?php
session_start();
// ถ้า user ยังไม่ล็อกอิน → ไปหน้า login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
