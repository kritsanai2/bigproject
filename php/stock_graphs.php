<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว

function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
                5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
                9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[$month] ?? $month;
}

// ===================== รับค่าจาก filter =====================
$day_type   = $_GET['day_type'] ?? '';
$day_month  = $_GET['day_month'] ?? date('n');
$day_year   = $_GET['day_year'] ?? date('Y');

$month_type = $_GET['month_type'] ?? '';
$month_year = $_GET['month_year'] ?? date('Y');

$year_type  = $_GET['year_type'] ?? '';

// ===================== กราฟรายวัน =====================
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $day_month, $day_year);
$dailyData = [];
for($d=1;$d<=$days_in_month;$d++){
    $dailyData[$d] = ['in'=>0,'out'=>0];
}

$sql = "SELECT DAY(stock_date) AS d,
                SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END) AS total_in,
                SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END) AS total_out
        FROM stock
        WHERE YEAR(stock_date)=$day_year AND MONTH(stock_date)=$day_month AND deleted=0";
if($day_type) $sql .= " AND stock_type='".$conn->real_escape_string($day_type)."'";
$sql .= " GROUP BY DAY(stock_date)";
$res = $conn->query($sql);
while($r = $res->fetch_assoc()){
    $dailyData[(int)$r['d']]['in']   += (float)$r['total_in'];
    $dailyData[(int)$r['d']]['out'] += (float)$r['total_out'];
}

// ===================== กราฟรายเดือน =====================
$monthlyData = [];
for($m=1;$m<=12;$m++){
    $monthlyData[$m] = ['in'=>0,'out'=>0];
}

$sql = "SELECT MONTH(stock_date) AS m,
                SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END) AS total_in,
                SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END) AS total_out
        FROM stock
        WHERE YEAR(stock_date)=$month_year AND deleted=0";
if($month_type) $sql .= " AND stock_type='".$conn->real_escape_string($month_type)."'";
$sql .= " GROUP BY MONTH(stock_date)";
$res = $conn->query($sql);
while($r = $res->fetch_assoc()){
    $monthlyData[(int)$r['m']]['in']   += (float)$r['total_in'];
    $monthlyData[(int)$r['m']]['out'] += (float)$r['total_out'];
}

// ===================== กราฟรายปี =====================
$yearlyData = [];
$sql = "SELECT YEAR(stock_date) AS y,
                SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END) AS total_in,
                SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END) AS total_out
        FROM stock
        WHERE deleted=0";
if($year_type) $sql .= " AND stock_type='".$conn->real_escape_string($year_type)."'";
$sql .= " GROUP BY YEAR(stock_date)";
$res = $conn->query($sql);
while($r = $res->fetch_assoc()){
    $yearlyData[(int)$r['y']]['in']   = (float)$r['total_in'];
    $yearlyData[(int)$r['y']]['out'] = (float)$r['total_out'];
}
?>


<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>กราฟสต็อกสินค้า</title>
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
  }
  .filter form {
    flex-direction: column;
    align-items: stretch;
  }
  .filter select, 
  .filter button {
    width: 100%;
  }
}

</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
  <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
  <h2>ข้อมูลสต็อกสินค้า</h2>
  <a href="stock_dashboard.php?page=table&type=&day_month="><i class="fas fa-list"></i>&nbsp; <span>รายงานสต็อก</span></a>
  <a href="stock_graphs.php" class="active"><i class="fas fa-chart-pie"></i>&nbsp; <span>กราฟรายงาน</span></a>
</div>

<!-- Main content -->
<div class="main" id="main">
<h1><i class="fas fa-chart-bar"></i> กราฟรายงานคลังสินค้า</h1>

<!-- Daily Chart -->
<h2>กราฟรายวัน (<?=thai_month($day_month)?>/<?=$day_year?>)</h2>
<div class="filter">
<form method="get">
  <input type="hidden" name="month_type" value="<?=$month_type?>">
  <input type="hidden" name="month_year" value="<?=$month_year?>">
  <input type="hidden" name="year_type" value="<?=$year_type?>">
  <label for="day_type">ประเภท:</label>
  <select name="day_type" id="day_type">
    <option value="" <?=($day_type=='')?'selected':''?>>ทั้งหมด</option>
    <option value="import" <?=($day_type=='import')?'selected':''?>>นำเข้า</option>
    <option value="remove" <?=($day_type=='remove')?'selected':''?>>นำออก</option>
  </select>
  <label for="day_month">เดือน:</label>
  <select name="day_month" id="day_month"><?php for($m=1;$m<=12;$m++): ?>
      <option value="<?=$m?>" <?=($day_month==$m)?'selected':''?>><?=thai_month($m)?></option>
  <?php endfor; ?></select>
  <label for="day_year">ปี:</label>
  <select name="day_year" id="day_year"><?php for($y=2023;$y<=2035;$y++): ?>
      <option value="<?=$y?>" <?=($day_year==$y)?'selected':''?>><?=$y?></option>
  <?php endfor; ?></select>
  <button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
</form>
</div>
<div class="chart-container">
    <canvas id="dailyChart" height="150"></canvas>
</div>


<!-- Monthly Chart -->
<h2>กราฟรายเดือน (<?=$month_year?>)</h2>
<div class="filter">
<form method="get">
  <input type="hidden" name="day_type" value="<?=$day_type?>">
  <input type="hidden" name="day_month" value="<?=$day_month?>">
  <input type="hidden" name="day_year" value="<?=$day_year?>">
  <input type="hidden" name="year_type" value="<?=$year_type?>">
  <label for="month_type">ประเภท:</label>
  <select name="month_type" id="month_type">
    <option value="" <?=($month_type=='')?'selected':''?>>ทั้งหมด</option>
    <option value="import" <?=($month_type=='import')?'selected':''?>>นำเข้า</option>
    <option value="remove" <?=($month_type=='remove')?'selected':''?>>นำออก</option>
  </select>
  <label for="month_year">ปี:</label>
  <select name="month_year" id="month_year"><?php for($y=2023;$y<=2035;$y++): ?>
      <option value="<?=$y?>" <?=($month_year==$y)?'selected':''?>><?=$y?></option>
  <?php endfor; ?></select>
  <button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
</form>
</div>
<div class="chart-container">
    <canvas id="monthlyChart" height="150"></canvas>
</div>

<!-- Yearly Chart -->
<h2>กราฟรายปี</h2>
<div class="filter">
<form method="get">
  <input type="hidden" name="day_type" value="<?=$day_type?>">
  <input type="hidden" name="day_month" value="<?=$day_month?>">
  <input type="hidden" name="day_year" value="<?=$day_year?>">
  <input type="hidden" name="month_type" value="<?=$month_type?>">
  <input type="hidden" name="month_year" value="<?=$month_year?>">
  <label for="year_type">ประเภท:</label>
  <select name="year_type" id="year_type">
    <option value="" <?=($year_type=='')?'selected':''?>>ทั้งหมด</option>
    <option value="import" <?=($year_type=='import')?'selected':''?>>นำเข้า</option>
    <option value="remove" <?=($year_type=='remove')?'selected':''?>>นำออก</option>
  </select>
  <button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
</form>
</div>
<div class="chart-container">
    <canvas id="yearlyChart" height="150"></canvas>
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
            { label:'นำเข้า', data: <?= json_encode(array_column($dailyData,'in')) ?>, backgroundColor:'rgba(39,174,96,0.7)', borderRadius:5 },
            { label:'นำออก', data: <?= json_encode(array_column($dailyData,'out')) ?>, backgroundColor:'rgba(192,57,43,0.7)', borderRadius:5 }
        ]
    },
    options:{ responsive:true, plugins:{legend:{position:'top'}} }
});

const monthlyChart = new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map('thai_month', array_keys($monthlyData))) ?>,
        datasets:[
            { label:'นำเข้า', data: <?= json_encode(array_column($monthlyData,'in')) ?>, backgroundColor:'rgba(39,174,96,0.7)', borderRadius:5 },
            { label:'นำออก', data: <?= json_encode(array_column($monthlyData,'out')) ?>, backgroundColor:'rgba(192,57,43,0.7)', borderRadius:5 }
        ]
    },
    options:{ responsive:true, plugins:{legend:{position:'top'}} }
});

const yearlyChart = new Chart(document.getElementById('yearlyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($yearlyData)) ?>,
        datasets:[
            { label:'นำเข้า', data: <?= json_encode(array_column($yearlyData,'in')) ?>, borderColor:'rgba(39,174,96,1)', backgroundColor:'rgba(39,174,96,0.2)', fill:true, tension:0.3 },
            { label:'นำออก', data: <?= json_encode(array_column($yearlyData,'out')) ?>, borderColor:'rgba(192,57,43,1)', backgroundColor:'rgba(192,57,43,0.2)', fill:true, tension:0.3 }
        ]
    },
    options:{ responsive:true, plugins:{legend:{position:'top'}} }
});
</script>
</body>
</html>
