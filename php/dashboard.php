<?php
require_once "db.php"; // เรียกไฟล์เชื่อมต่อฐานข้อมูล

$stock_count = $conn->query("SELECT IFNULL(SUM(CASE WHEN stock_type='import' THEN quantity ELSE -quantity END),0) AS total FROM stock")->fetch_assoc()['total'];
$employees_count = $conn->query("SELECT COUNT(*) AS c FROM employees")->fetch_assoc()['c'];
$transactions_count = $conn->query("SELECT COUNT(*) AS c FROM transactions")->fetch_assoc()['c'];
$orders_count = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>📊 จัดการรายงานทั้งหมด</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Sarabun', sans-serif;
    }
   body {
    min-height: 100vh;
    padding: 40px;
    background: linear-gradient(to bottom right, #1a5276, #2e86c1, #48c9b0);
    background-size: cover;
    display: flex;
    flex-direction: column;
    align-items: center;
    color: var(--text-color);
}
    .header {
        font-size: 3rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: #1a5276;
        text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    .home-button {
        display: inline-block;
        margin-bottom: 40px;
        padding: 14px 30px;
        background: #3498db;
        color: #fff;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        transition: 0.3s;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    .home-button:hover {
        background: #217dbb;
        transform: translateY(-3px);
    }
    .grid-dashboard {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 30px;
        width: 100%;
        max-width: 1000px;
        padding: 20px;
    }
.card {
    display: flex;
    flex-direction: row;
    align-items: center;
    padding: 50px 100px; /* เพิ่ม padding แนวนอนเป็น 100px */
    border-radius: 25px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    transition: transform 0.4s ease, box-shadow 0.4s ease;
    text-decoration: none;
    color: #fff;
    gap: 30px;
    position: relative;
    overflow: hidden;
    min-height: 120px;
}

    .card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }
    .card .card-icon-wrapper {
        width: 75px;
        height: 75px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.4s;
    }
    .card .card-icon-wrapper i {
        font-size: 38px;
        color: #fff;
    }
    .card-content {
        text-align: left;
    }
    .card-title {
        font-size: 1.4rem;
        font-weight: 600;
        margin-bottom: 5px;
    }
    .card-value {
        font-size: 2.8rem;
        font-weight: 700;
        line-height: 1;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.05);
    }
    .card-unit {
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.8);
    }

    /* สไตล์เฉพาะการ์ดแต่ละใบ */
    .card:nth-child(1) {
        background: linear-gradient(135deg, #1abc9c, #16a085);
    }
    .card:nth-child(1) .card-value {
        color: #d1f2eb;
    }
    .card:nth-child(1):hover {
        background: linear-gradient(135deg, #1abc9c, #16a085);
    }

    .card:nth-child(2) {
        background: linear-gradient(135deg, #2ecc71, #27ae60);
    }
    .card:nth-child(2) .card-value {
        color: #e8f8f5;
    }
    .card:nth-child(2):hover {
        background: linear-gradient(135deg, #2ecc71, #27ae60);
    }

    .card:nth-child(3) {
        background: linear-gradient(135deg, #3498db, #2980b9);
    }
    .card:nth-child(3) .card-value {
        color: #d6eaf8;
    }
    .card:nth-child(3):hover {
        background: linear-gradient(135deg, #3498db, #2980b9);
    }

    .card:nth-child(4) {
        background: linear-gradient(135deg, #9b59b6, #8e44ad);
    }
    .card:nth-child(4) .card-value {
        color: #f5eef8;
    }
    .card:nth-child(4):hover {
        background: linear-gradient(135deg, #9b59b6, #8e44ad);
    }

    @media (max-width: 768px) {
        .header {
            font-size: 2.5rem;
        }
        .grid-dashboard {
            grid-template-columns: 1fr;
            padding: 10px;
        }
        .card {
            padding: 25px;
            gap: 20px;
        }
    }
</style>
</head>
<body>

<div class="header">📊 จัดการรายงาน</div>
<a href="index.php" class="home-button">หน้าหลัก</a>

<div class="grid-dashboard">
    <a href="stock_dashboard.php" class="card">
        <div class="card-icon-wrapper">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <div class="card-content">
            <div class="card-title">ข้อมูลสินค้าคงคลัง</div>
            <div class="card-value"><?= number_format($stock_count) ?> <span class="card-unit">ชิ้น</span></div>
        </div>
    </a>
    <a href="employee_dashboard.php" class="card">
        <div class="card-icon-wrapper">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <div class="card-content">
            <div class="card-title">รายงานข้อมูลพนักงาน</div>
            <div class="card-value"><?= number_format($employees_count) ?> <span class="card-unit">คน</span></div>
        </div>
    </a>
    <a href="transactions_dashboard.php" class="card">
        <div class="card-icon-wrapper">
            <i class="fa-solid fa-money-bill-transfer"></i>
        </div>
        <div class="card-content">
            <div class="card-title">ข้อมูลธุรกรรม</div>
            <div class="card-value"><?= number_format($transactions_count) ?> <span class="card-unit">รายการ</span></div>
        </div>
    </a>
    <a href="orders_dashboard.php" class="card">
        <div class="card-icon-wrapper">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <div class="card-content">
            <div class="card-title">ข้อมูลการขาย</div>
            <div class="card-value"><?= number_format($orders_count) ?> <span class="card-unit">ออเดอร์</span></div>
        </div>
    </a>
</div>

</body>
</html>