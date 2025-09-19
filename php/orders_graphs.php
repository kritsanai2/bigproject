<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว

// ================== กำหนดค่าฟิลเตอร์ ==================
$day_year   = $_GET['day_year']   ?? date("Y");
$day_month  = $_GET['day_month']  ?? date("n");
$month_year = $_GET['month_year'] ?? date("Y");

// ================== ฟังก์ชันชื่อเดือน ==================
function thai_month($m) {
    $months = ["","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
    return $months[$m];
}

// ================== กราฟรายวัน ==================
$dailyData = array_fill(1, cal_days_in_month(CAL_GREGORIAN, $day_month, $day_year), 0);
$sql = "SELECT DAY(order_date) as d, SUM(total_amount) as total 
        FROM orders 
        WHERE YEAR(order_date)=$day_year AND MONTH(order_date)=$day_month
        GROUP BY DAY(order_date)";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()){
    $dailyData[(int)$row['d']] = (float)$row['total'];
}

// ================== กราฟรายเดือน ==================
$monthlyData = array_fill(1, 12, 0);
$sql = "SELECT MONTH(order_date) as m, SUM(total_amount) as total 
        FROM orders 
        WHERE YEAR(order_date)=$month_year
        GROUP BY MONTH(order_date)";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()){
    $monthlyData[(int)$row['m']] = (float)$row['total'];
}

// ================== กราฟรายปี ==================
$yearlyData = [];
$sql = "SELECT YEAR(order_date) as y, SUM(total_amount) as total 
        FROM orders 
        GROUP BY YEAR(order_date)";
$res = $conn->query($sql);
while($row = $res->fetch_assoc()){
    $yearlyData[(int)$row['y']] = (float)$row['total'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>📊 กราฟยอดขาย</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

/* --- Filter & Chart Section --- */
.container {
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.filter {
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

.export-group {
    text-align: center;
    margin-top: 2rem;
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
  <a href="orders_dashboard.php"><i class="fas fa-receipt"></i>&nbsp; <span>รายงานคำสั่งซื้อ</span></a>
  <a href="orders_graphs.php" class="active"><i class="fas fa-chart-pie"></i>&nbsp; <span>รายงานการขายสินค้า</span></a>
</div>

<div class="main" id="main">
  <h1><i class="fas fa-chart-line"></i>&nbsp; กราฟยอดขาย</h1>

  <div class="container">
    <h2>ยอดขายรายวัน</h2>
    <div class="filter">
      <form method="get">
        <label>เดือน:</label>
        <select name="day_month" onchange="this.form.submit()">
          <?php for($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= ($m==$day_month)?'selected':'' ?>><?= thai_month($m) ?></option>
          <?php endfor; ?>
        </select>
        <label>ปี:</label>
        <select name="day_year" onchange="this.form.submit()">
          <?php for($y=2023;$y<=2035;$y++): ?>
            <option value="<?= $y ?>" <?= ($y==$day_year)?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </form>
    </div>
    <canvas id="dailyChart"></canvas>
  </div>

  <div class="container">
    <h2>ยอดขายรายเดือน</h2>
    <div class="filter">
      <form method="get">
        <label>ปี:</label>
        <select name="month_year" onchange="this.form.submit()">
          <?php for($y=2023;$y<=2035;$y++): ?>
            <option value="<?= $y ?>" <?= ($y==$month_year)?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </form>
    </div>
    <canvas id="monthlyChart"></canvas>
  </div>

  <div class="container">
    <h2>ยอดขายรายปี</h2>
    <canvas id="yearlyChart"></canvas>
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

// Charts
document.addEventListener('DOMContentLoaded', () => {
    // รายวัน (Bar)
    const dailyChart = new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($dailyData)) ?>,
            datasets: [{
                label: 'ยอดขาย (บาท)',
                data: <?= json_encode(array_values($dailyData)) ?>,
                backgroundColor: 'rgba(54,162,235,0.6)',
                borderRadius: 5
            }]
        },
        options:{ responsive:true, plugins:{legend:{position:'top'}} }
    });

    // รายเดือน (Bar)
    const monthlyChart = new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map("thai_month", array_keys($monthlyData))) ?>,
            datasets: [{
                label: 'ยอดขาย (บาท)',
                data: <?= json_encode(array_values($monthlyData)) ?>,
                backgroundColor: 'rgba(255,99,132,0.6)',
                borderRadius: 5
            }]
        },
        options:{ responsive:true, plugins:{legend:{position:'top'}} }
    });

    // รายปี (Line)
    const yearlyChart = new Chart(document.getElementById('yearlyChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_keys($yearlyData)) ?>,
            datasets: [{
                label: 'ยอดขาย (บาท)',
                data: <?= json_encode(array_values($yearlyData)) ?>,
                borderColor: 'rgba(75,192,192,1)',
                backgroundColor: 'rgba(75,192,192,0.2)',
                fill: true,
                tension:0.3
            }]
        },
        options:{ responsive:true, plugins:{legend:{position:'top'}} }
    });
});
</script>

</div>
</body>
</html>


