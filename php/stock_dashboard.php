<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว


// ฟังก์ชันแปลงเดือนและประเภทเป็นไทย
function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
                5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
                9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[$month] ?? '';
}
function thai_type($type){
    return strtolower($type)=='import' ? 'รับเข้า' : 'จ่ายออก';
}

// รับ filter
$type_filter    = $_GET['type'] ?? '';
$month_filter = $_GET['month'] ?? 0;
$year_filter    = $_GET['year'] ?? date('Y');
$page           = $_GET['page'] ?? 'table';

// ดึงข้อมูล stock
$sql = "SELECT s.stock_id,s.stock_date,p.product_name,s.stock_type,s.quantity,p.unit
        FROM stock s
        JOIN products p ON s.product_id=p.product_id
        WHERE s.deleted=0";

if($type_filter)    $sql .= " AND s.stock_type='".$conn->real_escape_string($type_filter)."'";
if($month_filter>0) $sql .= " AND MONTH(s.stock_date)=$month_filter";
if($year_filter) $sql .= " AND YEAR(s.stock_date)=$year_filter";

$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";
$result = $conn->query($sql);

// สำหรับกราฟ
$graph_data = [];
$graph_sql = "SELECT s.stock_type, SUM(s.quantity) AS total_qty FROM stock s WHERE s.deleted=0";
if($type_filter)    $graph_sql .= " AND s.stock_type='".$conn->real_escape_string($type_filter)."'";
if($month_filter>0) $graph_sql .= " AND MONTH(s.stock_date)=$month_filter";
if($year_filter) $graph_sql .= " AND YEAR(s.stock_date)=$year_filter";
$graph_sql .= " GROUP BY s.stock_type";
$graph_result = $conn->query($graph_sql);
while($row = $graph_result->fetch_assoc()){
    $graph_data[$row['stock_type']] = $row['total_qty'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>รายงานสต็อก</title>
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

/* --- Filter & Actions Section --- */
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
}

.filter select {
    padding: 0.5rem;
    border: 1px solid #e0e6ea;
    border-radius: 8px;
    font-family: 'Sarabun', sans-serif;
    font-size: 1rem;
}

.filter button {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    color: white;
    font-size: 1rem;
    background-color: #3498db;
}

.filter button:hover {
    background-color: #2980b9;
    transform: translateY(-2px);
}

.export-group {
    text-align: center;
    margin-top: 15px;
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
  }
  .filter form {
    flex-direction: column;
    align-items: stretch;
  }
  .filter select, .filter button {
    width: 100%;
  }
}

</style>
</head>
<body>

<button class="toggle-btn">☰</button>

<div class="sidebar">
  <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
  <h2>ข้อมูลสต็อกสินค้า</h2>
  <a href="dashboard.php"><i class="fas fa-home"></i>&nbsp; <span>กลับ</span></a>
  <a href="stock_dashboard.php?page=table&type=&day_month=" class="<?=($type_filter=='')?'active':''?>"><i class="fas fa-list"></i>&nbsp; <span>ทั้งหมด</span></a>
  <a href="stock_dashboard.php?page=table&type=import&day_month=" class="<?=($type_filter=='import')?'active':''?>"><i class="fas fa-arrow-down"></i>&nbsp; <span>รับเข้า</span></a>
  <a href="stock_dashboard.php?page=table&type=remove&day_month=" class="<?=($type_filter=='remove')?'active':''?>"><i class="fas fa-arrow-up"></i>&nbsp; <span>จ่ายออก</span></a>
  <a href="stock_graphs.php"><i class="fas fa-chart-pie"></i>&nbsp; <span>รายงานคลังสินค้า</span></a>
</div>


<div class="main">
<h1><i class="fas fa-box"></i>&nbsp; รายงานสต็อกสินค้า</h1>

<?php if($page=='table'): ?>
<div class="filter">
<form method="get">
  <input type="hidden" name="page" value="table">
  <label for="type-filter">ประเภท:</label>
  <select name="type" id="type-filter">
    <option value="" <?=($type_filter=='')?'selected':''?>>ทั้งหมด</option>
    <option value="import" <?=($type_filter=='import')?'selected':''?>>รับเข้า</option>
    <option value="remove" <?=($type_filter=='remove')?'selected':''?>>จ่ายออก</option>
  </select>
  <label for="month-filter">เดือน:</label>
  <select name="month" id="month-filter">
    <option value="0" <?=($month_filter==0)?'selected':''?>>ทั้งหมด</option>
    <?php for($m=1;$m<=12;$m++): ?>
    <option value="<?=$m?>" <?=($month_filter==$m)?'selected':''?>><?=thai_month($m)?></option>
    <?php endfor; ?>
  </select>
  <label for="year-filter">ปี:</label>
  <select name="year" id="year-filter">
    <?php for($y=2023;$y<=2035;$y++): ?>
    <option value="<?=$y?>" <?=($year_filter==$y)?'selected':''?>><?=$y?></option>
    <?php endfor; ?>
  </select>
  <button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>

  <!-- ปุ่มส่งออก -->
  <div class="export-group">
      <a href="stock_export.php?type=<?= $type_filter ?>&format=pdf" class="btn-export pdf"><i class="fas fa-file-pdf"></i>&nbsp; ส่งออก PDF</a>
      <a href="stock_export.php?type=<?= $type_filter ?>&format=excel" class="btn-export excel"><i class="fas fa-file-excel"></i>&nbsp; ส่งออก Excel</a>
  </div>
</form>
</div>

<div class="table-container">
<table>
<thead>
<tr>
<th>ลำดับ</th>
<th>วันที่</th>
<th>สินค้า</th>
<th>ประเภท</th>
<th>จำนวน</th>
<th>หน่วย</th>
</tr>
</thead>
<tbody>
<?php
$rows = [];
while($row = $result->fetch_assoc()){
    $rows[] = $row;
}
$i = count($rows);
foreach($rows as $row):
?>
<tr>
    <td><?=$i--?></td>
    <td><?=$row['stock_date']?></td>
    <td><?=htmlspecialchars($row['product_name'])?></td>
    <td class="stock-type"><?=thai_type($row['stock_type'])?></td>
    <td><?=number_format($row['quantity'],2)?></td>
    <td><?=htmlspecialchars($row['unit'])?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php else: ?>
<div class="chart-container">
<canvas id="stockChart" width="400" height="200"></canvas>
<script>
const ctx = document.getElementById('stockChart').getContext('2d');
const stockChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?=json_encode(array_map('thai_type', array_keys($graph_data)))?>,
        datasets: [{
            label: 'จำนวนรวม',
            data: <?=json_encode(array_values($graph_data))?>,
            backgroundColor: ['#28a745','#dc3545']
        }]
    },
    options: {responsive:true, plugins:{legend:{display:false}}}
});
</script>
</div>
<?php endif; ?>
</div>

<script src="../js/stock_dashboard.js"></script>
<script>
    // Sidebar toggle functionality
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main');
    const toggleBtn = document.querySelector('.toggle-btn');
    const isMobile = window.matchMedia('(max-width: 768px)');

    function toggleSidebar() {
      sidebar.classList.toggle('hidden');
      if (isMobile.matches) {
        mainContent.classList.toggle('full-width');
      } else {
        mainContent.style.marginLeft = sidebar.classList.contains('hidden') ? '0' : '250px';
      }
    }

    toggleBtn.addEventListener('click', toggleSidebar);

    // Initial state based on screen size
    if (isMobile.matches) {
        sidebar.classList.add('hidden');
        mainContent.classList.add('full-width');
    }
</script>

</body>
</html>
