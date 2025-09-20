<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว

// ฟังก์ชันแปลงเดือนเป็นภาษาไทย
function thai_month($month){
    $months = [
        1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
        5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
        9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
    ];
    return $months[$month] ?? $month;
}

// กรองเดือนและปี สำหรับรายวัน
$day_month = isset($_GET['day_month']) && $_GET['day_month'] !== '' ? intval($_GET['day_month']) : 0; // 0 = ทั้งหมด
$day_year  = isset($_GET['day_year']) && $_GET['day_year'] !== '' ? intval($_GET['day_year']) : intval(date('Y'));

// SQL ดึงข้อมูลคำสั่งซื้อ
$sql_orders = "
SELECT o.order_id, o.order_date, c.full_name AS customer_name,
        SUM(od.quantity * od.price) AS total_amount
FROM orders o
JOIN customers c ON o.customer_id = c.customer_id
JOIN order_details od ON o.order_id = od.order_id
WHERE o.deleted = 0
";

// ถ้าเลือกเดือนจริง ๆ ให้กรอง
if($day_month > 0){
    $sql_orders .= " AND MONTH(o.order_date) = $day_month";
}
$sql_orders .= " AND YEAR(o.order_date) = $day_year
GROUP BY o.order_id
ORDER BY o.order_date DESC";

$result_orders = $conn->query($sql_orders);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>รายงานการขาย</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
/* --- General Styles & Typography --- */
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Sarabun', sans-serif;
    background-color: #f4f7f9;
    color: #2c3e50;
    line-height: 1.6;
    font-size: 16px;
    display: flex;
}

h1 {
    font-size: 2rem;
    margin-bottom: 1.5rem;
    font-weight: 700;
    color: #3498db;
    border-bottom: 3px solid #3498db;
    padding-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

h2 {
    font-size: 1.5rem;
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-weight: 600;
    color: #34495e;
}

/* --- Sidebar Section --- */
.sidebar {
    width: 250px;
    background-color: #3498db;
    color: white;
    padding: 2rem 1.5rem;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    transition: transform 0.3s ease-in-out;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    z-index: 100;
}

.sidebar.hidden {
    transform: translateX(-100%);
}

.logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.3);
    object-fit: cover;
    margin-bottom: 1.5rem;
}

.sidebar h2 {
    font-size: 1.5rem;
    margin-bottom: 2rem;
    font-weight: 700;
    text-align: center;
    border-bottom: none;
    padding-bottom: 0;
    color: white;
}

.sidebar a {
    color: white;
    text-decoration: none;
    font-size: 1.1rem;
    padding: 0.8rem 1.5rem;
    border-radius: 8px;
    width: 100%;
    text-align: left;
    transition: background-color 0.2s ease, transform 0.2s ease;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.sidebar a:hover {
    background-color: rgba(255, 255, 255, 0.2);
    transform: translateX(5px);
}

.sidebar a.active {
    background-color: rgba(255, 255, 255, 0.3);
    font-weight: 500;
}

.toggle-btn {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 101;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: right 0.3s ease-in-out;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* --- Main Content Section --- */
.main {
    margin-left: 250px;
    padding: 2rem;
    flex-grow: 1;
    transition: margin-left 0.3s ease-in-out;
}

.main.full-width {
    margin-left: 0;
}

/* --- Filter Section --- */
.filter {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.filter form {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem;
}

.filter label {
    font-weight: 500;
    color: #7f8c8d;
}

.filter select,
.filter button {
    padding: 0.75rem;
    border: 1px solid #e0e6ea;
    border-radius: 8px;
    font-family: 'Sarabun', sans-serif;
    font-size: 1rem;
    min-width: 120px;
}

.filter select:focus,
.filter button:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}

.filter button {
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    color: white;
    background-color: #3498db;
}

.filter button:hover {
    background-color: #2980b9;
    transform: translateY(-2px);
}

/* --- Export Buttons Section --- */
.export-group {
    text-align: center;
    margin-bottom: 20px;
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-export {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    color: white;
    font-size: 1rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-export.pdf {
    background-color: #e74c3c;
}
.btn-export.excel {
    background-color: #2ecc71;
}

.btn-export:hover {
    transform: translateY(-2px);
}
.btn-export.pdf:hover { background-color: #c0392b; }
.btn-export.excel:hover { background-color: #27ae60; }

/* --- Table Section --- */
table {
    width: 100%;
    border-collapse: collapse;
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

thead {
    background-color: #3498db;
    color: white;
}

th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e0e6ea;
}

th {
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.9rem;
    letter-spacing: 0.5px;
}

tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

tbody tr:hover {
    background-color: #f1faff;
}

/* --- Responsive Design --- */
@media (max-width: 768px) {
    .sidebar {
        width: 200px;
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main {
        margin-left: 0;
    }
    .toggle-btn {
        left: 1rem;
        right: auto;
    }
}
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
    <h2>ข้อมูลการขาย</h2>
    <a href="dashboard.php"><i class="fas fa-home"></i>&nbsp; <span>กลับ</span></a>
    <a href="orders_dashboard.php" class="active"><i class="fas fa-receipt"></i>&nbsp; <span>รายงานคำสั่งซื้อ</span></a>
    <a href="orders_graphs.php"><i class="fas fa-chart-pie"></i>&nbsp; <span>รายงานการขายสินค้า</span></a>
</div>

<div class="main" id="main">
    <h1><i class="fas fa-receipt"></i>&nbsp; รายงานข้อมูลการขาย</h1>

    <!-- ฟิลเตอร์เลือกเดือนและปี -->
    <div class="filter">
        <form method="get">
            <label for="month-select">เดือน:</label>
            <select name="day_month" id="month-select" onchange="this.form.submit()">
                <option value="0" <?= ($day_month==0)?'selected':'' ?>>ทั้งหมด</option>
                <?php for($m=1;$m<=12;$m++): ?>
                    <option value="<?= $m ?>" <?= ($m==$day_month)?'selected':'' ?>>
                        <?= thai_month($m) ?>
                    </option>
                <?php endfor; ?>
            </select>
            <label for="year-select">ปี:</label>
            <select name="day_year" id="year-select" onchange="this.form.submit()">
                <?php for($y=2023;$y<=2035;$y++): ?>
                    <option value="<?= $y ?>" <?= ($y==$day_year)?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <div class="export-group">
        <a class="btn-export pdf" 
            href="orders_export.php?format=pdf&month=<?= $day_month ?>&year=<?= $day_year ?>" 
            target="_blank"><i class="fas fa-file-pdf"></i>&nbsp; ส่งออก PDF</a>
        <a class="btn-export excel" 
            href="orders_export.php?format=excel&month=<?= $day_month ?>&year=<?= $day_year ?>" 
            target="_blank"><i class="fas fa-file-excel"></i>&nbsp; ส่งออก Excel</a>
    </div>

    <!-- ตารางคำสั่งซื้อ -->
    <table>
        <thead>
            <tr>
                <th>ลำดับ</th>
                <th>วันที่สั่งซื้อ</th>
                <th>ชื่อลูกค้า</th>
                <th>ยอดรวม (บาท)</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $i = $result_orders->num_rows;
        while($row = $result_orders->fetch_assoc()):
        ?>
        <tr>
            <td><?= $i-- ?></td>
            <td><?= date('d/m/', strtotime($row['order_date'])) . (date('Y', strtotime($row['order_date'])) + 543) ?></td>
            <td><?= htmlspecialchars($row['customer_name']) ?></td>
            <td><?= number_format($row['total_amount'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

</div>

<script>
// Toggle sidebar
const sidebar = document.getElementById('sidebar');
const main = document.getElementById('main');
const toggleBtn = document.getElementById('toggle-btn');
const isMobile = window.matchMedia('(max-width: 768px)');

function toggleSidebar() {
    sidebar.classList.toggle('hidden');
    if (isMobile.matches) {
        main.classList.toggle('full-width');
    } else {
        main.style.marginLeft = sidebar.classList.contains('hidden') ? '0' : '250px';
    }
}
toggleBtn.addEventListener('click', toggleSidebar);

// Initial state
if (isMobile.matches) {
    sidebar.classList.add('hidden');
    main.classList.add('full-width');
}
</script>

</body>
</html>


