<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว

// --- ฟังก์ชันเดือนภาษาไทย ---
function thai_month($m){
    $months=["","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
    return $months[$m];
}

// --- รับค่าตัวกรองสำหรับกราฟรายวัน ---
$daily_filter_year = $_GET['daily_year'] ?? date("Y");
$daily_filter_month = $_GET['daily_month'] ?? date("m");
$daily_filter_type = $_GET['daily_type'] ?? '';
$daily_where_clause = " WHERE deleted=0 AND YEAR(transaction_date)=$daily_filter_year AND MONTH(transaction_date)=$daily_filter_month ";
if ($daily_filter_type) {
    $daily_where_clause .= " AND transaction_type = '" . $conn->real_escape_string($daily_filter_type) . "'";
}

// --- กราฟรายวัน ---
$dailyData = [];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $daily_filter_month, $daily_filter_year);
for($d=1;$d<=$daysInMonth;$d++) {
    $dailyData[$d] = ['income'=>0,'expense'=>0];
}

$sql = "SELECT DAY(transaction_date) as d, transaction_type, SUM(amount) as total
         FROM transactions
         $daily_where_clause
         GROUP BY d, transaction_type";
$res = $conn->query($sql);
while($row=$res->fetch_assoc()){
    $dailyData[(int)$row['d']][$row['transaction_type']] = (float)$row['total'];
}

// --- รับค่าตัวกรองสำหรับกราฟรายเดือน ---
$monthly_filter_year = $_GET['monthly_year'] ?? date("Y");
$monthly_filter_type = $_GET['monthly_type'] ?? '';
$monthly_where_clause = " WHERE deleted=0 AND YEAR(transaction_date)=$monthly_filter_year ";
if ($monthly_filter_type) {
    $monthly_where_clause .= " AND transaction_type = '" . $conn->real_escape_string($monthly_filter_type) . "'";
}

// --- กราฟรายเดือน ---
$monthlyData = [];
for($m=1;$m<=12;$m++) {
    $monthlyData[$m]=['income'=>0,'expense'=>0];
}
$sql = "SELECT MONTH(transaction_date) as m, transaction_type, SUM(amount) as total
         FROM transactions
         $monthly_where_clause
         GROUP BY m, transaction_type";
$res = $conn->query($sql);
while($row=$res->fetch_assoc()){
    $monthlyData[(int)$row['m']][$row['transaction_type']] = (float)$row['total'];
}

// --- รับค่าตัวกรองสำหรับกราฟรายปี ---
$yearly_filter_type = $_GET['yearly_type'] ?? '';
$yearly_where_clause = " WHERE deleted=0 ";
if ($yearly_filter_type) {
    $yearly_where_clause .= " AND transaction_type = '" . $conn->real_escape_string($yearly_filter_type) . "'";
}

// --- กราฟรายปี ---
$yearlyData = [];
$sql = "SELECT YEAR(transaction_date) as y,
                SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END) as expense
         FROM transactions
         $yearly_where_clause
         GROUP BY y
         ORDER BY y";
$res = $conn->query($sql);
while($row=$res->fetch_assoc()){
    $yearlyData[(int)$row['y']] = [
        'income' => (float)$row['income'],
        'expense'=> (float)$row['expense']
    ];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>📊 กราฟธุรกรรม</title>
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
    margin-top: 2.5rem;
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

.chart-container {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    margin-bottom: 2rem;
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

.btn-export:hover {
    transform: translateY(-2px);
}
.btn-export.pdf:hover { background-color: #c0392b; }


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

/* Print Styles */
@media print {
    body > :not(#print-area) {
        display: none !important;
    }
    .print-chart-container {
        page-break-inside: avoid;
        margin-bottom: 2rem;
    }
    h2 {
      text-align: center;
      margin-bottom: 1rem;
      border-bottom: 2px solid #3498db;
    }
    canvas {
      width: 100% !important;
      height: auto !important;
    }
}
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
  <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
  <h2>ข้อมูลรายรับ-รายจ่าย</h2>
  <a href="transactions_dashboard.php"><i class="fas fa-list"></i>&nbsp; <span>รายงานรายรับ-รายจ่าย</span></a>
  <a href="transactions_graphs.php" class="active"><i class="fas fa-chart-pie"></i>&nbsp; <span>กราฟรายงานรายรับ-รายจ่าย</span></a>
</div>

<div class="main" id="main">
<h1><i class="fas fa-chart-bar"></i>&nbsp; กราฟรายงานรายรับ-รายจ่าย</h1>

<div class="chart-container">
<h2>กราฟรายวัน (<?= $daily_filter_year."/".$daily_filter_month ?>)</h2>
<div class="filter">
<form method="get">
<label for="daily_year-select">ปี:</label>
<select name="daily_year" id="daily_year-select">
<?php for($y=2023;$y<=2035;$y++): ?>
<option value="<?= $y ?>" <?= $daily_filter_year==$y?'selected':'' ?>><?= $y ?></option>
<?php endfor; ?>
</select>
<label for="daily_month-select">เดือน:</label>
<select name="daily_month" id="daily_month-select">
<?php for($m=1;$m<=12;$m++): ?>
<option value="<?= $m ?>" <?= $daily_filter_month==$m?'selected':'' ?>><?= thai_month($m) ?></option>
<?php endfor; ?>
</select>
<label for="daily_type-select">ประเภท:</label>
<select name="daily_type" id="daily_type-select">
<option value="" <?= $daily_filter_type==''?'selected':'' ?>>ทั้งหมด</option>
<option value="income" <?= $daily_filter_type=='income'?'selected':'' ?>>รายรับ</option>
<option value="expense" <?= $daily_filter_type=='expense'?'selected':'' ?>>รายจ่าย</option>
</select>
<button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
</form>
</div>
<canvas id="dailyChart" height="150"></canvas>
</div>

<div class="chart-container">
<h2>กราฟรายเดือน (<?= $monthly_filter_year ?>)</h2>
<div class="filter">
<form method="get">
<label for="monthly_year-select">ปี:</label>
<select name="monthly_year" id="monthly_year-select">
<?php for($y=2023;$y<=2035;$y++): ?>
<option value="<?= $y ?>" <?= $monthly_filter_year==$y?'selected':'' ?>><?= $y ?></option>
<?php endfor; ?>
</select>
<label for="monthly_type-select">ประเภท:</label>
<select name="monthly_type" id="monthly_type-select">
<option value="" <?= $monthly_filter_type==''?'selected':'' ?>>ทั้งหมด</option>
<option value="income" <?= $monthly_filter_type=='income'?'selected':'' ?>>รายรับ</option>
<option value="expense" <?= $monthly_filter_type=='expense'?'selected':'' ?>>รายจ่าย</option>
</select>
<button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
</form>
</div>
<canvas id="monthlyChart" height="150"></canvas>
</div>

<div class="chart-container">
<h2>กราฟรายปี</h2>
<div class="filter">
<form method="get">
<label for="yearly_type-select">ประเภท:</label>
<select name="yearly_type" id="yearly_type-select">
<option value="" <?= $yearly_filter_type==''?'selected':'' ?>>ทั้งหมด</option>
<option value="income" <?= $yearly_filter_type=='income'?'selected':'' ?>>รายรับ</option>
<option value="expense" <?= $yearly_filter_type=='expense'?'selected':'' ?>>รายจ่าย</option>
</select>
<button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
</form>
</div>
<canvas id="yearlyChart" height="150"></canvas>
</div>

<div class="export-group">
    <button onclick="exportGraphsToPdf()" class="btn-export pdf"><i class="fas fa-file-pdf"></i>&nbsp; ส่งออก PDF</button>
</div>

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
const dailyChart = new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($dailyData)) ?>,
        datasets:[
            { label:'รายรับ', data: <?= json_encode(array_column($dailyData,'income')) ?>, backgroundColor:'rgba(39,174,96,0.7)', borderRadius:5 },
            { label:'รายจ่าย', data: <?= json_encode(array_column($dailyData,'expense')) ?>, backgroundColor:'rgba(192,57,43,0.7)', borderRadius:5 }
        ]
    },
    options:{ responsive:true, plugins:{legend:{position:'top'}} }
});

const monthlyChart = new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map('thai_month', array_keys($monthlyData))) ?>,
        datasets:[
            { label:'รายรับ', data: <?= json_encode(array_column($monthlyData,'income')) ?>, backgroundColor:'rgba(39,174,96,0.7)', borderRadius:5 },
            { label:'รายจ่าย', data: <?= json_encode(array_column($monthlyData,'expense')) ?>, backgroundColor:'rgba(192,57,43,0.7)', borderRadius:5 }
        ]
    },
    options:{ responsive:true, plugins:{legend:{position:'top'}} }
});

const yearlyChart = new Chart(document.getElementById('yearlyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($yearlyData)) ?>,
        datasets:[
            { label:'รายรับ', data: <?= json_encode(array_column($yearlyData,'income')) ?>, borderColor:'rgba(39,174,96,1)', backgroundColor:'rgba(39,174,96,0.2)', fill:true, tension:0.3 },
            { label:'รายจ่าย', data: <?= json_encode(array_column($yearlyData,'expense')) ?>, borderColor:'rgba(192,57,43,1)', backgroundColor:'rgba(192,57,43,0.2)', fill:true, tension:0.3 }
        ]
    },
    options:{ responsive:true, plugins:{legend:{position:'top'}} }
});


// Function to handle PDF export by printing the charts
function exportGraphsToPdf() {
    const mainContent = document.getElementById('main');
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggle-btn');
    const filterForms = document.querySelectorAll('.filter');

    // Create a temporary print area
    const printArea = document.createElement('div');
    printArea.id = 'print-area';

    // Copy chart containers to the print area
    const charts = document.querySelectorAll('.chart-container');
    charts.forEach(chartContainer => {
        printArea.appendChild(chartContainer.cloneNode(true));
    });

    // Hide original content and forms
    mainContent.style.display = 'none';
    sidebar.style.display = 'none';
    toggleBtn.style.display = 'none';
    filterForms.forEach(form => form.style.display = 'none');

    // Append print area to body
    document.body.appendChild(printArea);

    window.print();

    // Revert back after printing
    setTimeout(() => {
        document.body.removeChild(printArea);
        mainContent.style.display = 'block';
        sidebar.style.display = 'flex';
        toggleBtn.style.display = 'flex';
        filterForms.forEach(form => form.style.display = 'block');
    }, 1000);
}
</script>
</body>
</html>
