<?php
require_once "db.php"; // เรียกไฟล์เชื่อมต่อฐานข้อมูล

// ดึงข้อมูลสรุปจากตารางต่างๆ
$stock_count = $conn->query("SELECT IFNULL(SUM(CASE WHEN stock_type='import' THEN quantity ELSE -quantity END),0) AS total FROM stock")->fetch_assoc()['total'];
$employees_count = $conn->query("SELECT COUNT(*) AS c FROM employees WHERE status = 1")->fetch_assoc()['c']; // นับเฉพาะคนที่ยังทำงาน
$transactions_count = $conn->query("SELECT COUNT(*) AS c FROM transactions")->fetch_assoc()['c'];
$orders_count = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📊 จัดการรายงานทั้งหมด</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"/>
<style>
    :root {
        --primary-blue: #0A7EA4;
        --secondary-blue: #3A89C9;
        --light-blue: #D6EFFF;
        --text-dark: #1F2E3A;
        --text-light: #F0F4F8;
        --accent-orange: #FF7F50;
        --accent-yellow: #FFD700;
        --card-bg-1: linear-gradient(135deg, #1abc9c, #16a085);
        --card-bg-2: linear-gradient(135deg, #3498db, #2980b9);
        --card-bg-3: linear-gradient(135deg, #9b59b6, #8e44ad);
        --card-bg-4: linear-gradient(135deg, #f1c40f, #f39c12);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Sarabun', sans-serif; }
    body {
        min-height: 100vh;
        background: linear-gradient(to bottom right, #eaf6f6, #d4eaf7);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px;
    }
    
    .header-container {
        width: 100%;
        max-width: 1200px;
        text-align: center;
        margin-bottom: 2rem;
    }
    .header {
        font-family: 'Playfair Display', serif;
        font-size: 3rem;
        font-weight: 700;
        color: var(--navy-blue, #001f3f);
        text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
    }
    .home-button {
        display: inline-block;
        margin-top: 1rem;
        padding: 12px 30px;
        background: var(--primary-blue);
        color: #fff;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    .home-button:hover {
        background: var(--secondary-blue);
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }
    .grid-dashboard {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        width: 100%;
        max-width: 1200px;
    }
    .card {
        display: flex;
        align-items: center;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: transform 0.4s ease, box-shadow 0.4s ease;
        text-decoration: none;
        color: #fff;
        gap: 20px;
        position: relative;
        overflow: hidden;
    }
    .card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
        transition: transform 0.8s ease;
        transform: scale(0);
    }
    .card:hover::before {
        transform: scale(2);
    }
    .card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }
    .card .card-icon-wrapper {
        width: 70px;
        height: 70px;
        min-width: 70px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.4s;
    }
    .card:hover .card-icon-wrapper {
        transform: rotate(360deg) scale(1.1);
    }
    .card .card-icon-wrapper i {
        font-size: 32px;
        color: #fff;
    }
    .card-content { text-align: left; }
    .card-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 5px;
        opacity: 0.9;
    }
    .card-value {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }
    .card-unit {
        font-size: 1.1rem;
        opacity: 0.8;
        margin-left: 5px;
    }
    .card:nth-child(1) { background: var(--card-bg-1); }
    .card:nth-child(2) { background: var(--card-bg-2); }
    .card:nth-child(3) { background: var(--card-bg-3); }
    .card:nth-child(4) { background: var(--card-bg-4); }

    @media (max-width: 768px) {
        body { padding: 20px; }
        .header { font-size: 2rem; }
        .grid-dashboard { grid-template-columns: 1fr; }
    }
</style>
</head>
<body>

<div class="header-container">
    <div class="header"><i class="fas fa-tachometer-alt"></i> จัดการรายงาน</div>
    <a href="index.php" class="home-button"><i class="fas fa-home"></i> กลับหน้าหลัก</a>
</div>

<div class="grid-dashboard">
    <a href="stock_dashboard.php" class="card">
        <div class="card-icon-wrapper">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <div class="card-content">
            <div class="card-title">สินค้าคงคลัง</div>
            <div class="card-value"><?= number_format($stock_count) ?> <span class="card-unit">ชิ้น</span></div>
        </div>
    </a>
    <a href="employee_dashboard.php" class="card">
        <div class="card-icon-wrapper">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <div class="card-content">
            <div class="card-title">พนักงาน</div>
            <div class="card-value"><?= number_format($employees_count) ?> <span class="card-unit">คน</span></div>
        </div>
    </a>
    <a href="transactions_dashboard.php" class="card">
        <div class="card-icon-wrapper">
            <i class="fa-solid fa-money-bill-transfer"></i>
        </div>
        <div class="card-content">
            <div class="card-title">ธุรกรรมทั้งหมด</div>
            <div class="card-value"><?= number_format($transactions_count) ?> <span class="card-unit">รายการ</span></div>
        </div>
    </a>
    <a href="orders_dashboard.php" class="card">
        <div class="card-icon-wrapper">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <div class="card-content">
            <div class="card-title">คำสั่งขาย</div>
            <div class="card-value"><?= number_format($orders_count) ?> <span class="card-unit">ออเดอร์</span></div>
        </div>
    </a>
</div>

</body>
</html>