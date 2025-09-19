<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว

// ฟังก์ชันแปลงประเภทเป็นไทย
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}

// กรองประเภท
$type_filter = $_GET['type'] ?? ''; // ''=ทั้งหมด, 'income', 'expense'

// ดึงข้อมูลธุรกรรม
$sql = "SELECT t.*, od.product_id, p.product_name
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id
        WHERE t.deleted = 0";

if ($type_filter) {
    $sql .= " AND t.transaction_type = '".$conn->real_escape_string($type_filter)."'";
}
$sql .= " ORDER BY t.transaction_date DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>รายงานรายรับ-รายจ่าย</title>
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
    }
}
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
  <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
  <h2>ข้อมูลรายรับ-รายจ่าย</h2>
  <a href="dashboard.php"><i class="fas fa-home"></i>&nbsp; <span>กลับ</span></a>
  <a href="transactions_dashboard.php?type=" class="<?=($type_filter=='')?'active':''?>"><i class="fas fa-list"></i>&nbsp; <span>ทั้งหมด</span></a>
  <a href="transactions_dashboard.php?type=income" class="<?=($type_filter=='income')?'active':''?>"><i class="fas fa-arrow-down"></i>&nbsp; <span>รายรับ</span></a>
  <a href="transactions_dashboard.php?type=expense" class="<?=($type_filter=='expense')?'active':''?>"><i class="fas fa-arrow-up"></i>&nbsp; <span>รายจ่าย</span></a>
  <a href="transactions_graphs.php"><i class="fas fa-chart-pie"></i>&nbsp; <span>รายงานรายรับ-รายจ่าย</span></a>
</div>


<div class="main" id="main">
<h1><i class="fas fa-receipt"></i>&nbsp; รายงานรายรับ-รายจ่าย</h1>
<!-- ปุ่มส่งออก -->
<div class="export-group">
    <a href="transactions_export.php?type=<?= $type_filter ?>&format=pdf" class="btn-export pdf"><i class="fas fa-file-pdf"></i>&nbsp; ส่งออก PDF</a>
    <a href="transactions_export.php?type=<?= $type_filter ?>&format=excel" class="btn-export excel"><i class="fas fa-file-excel"></i>&nbsp; ส่งออก Excel</a>
</div>

<table>
<thead>
<tr>
    <th>ลำดับ</th>
    <th>รหัสรายรับ-รายจ่าย</th>
    <th>วันที่</th>
    <th>ประเภท</th>
    <th>จำนวนเงิน (บาท)</th>
    <th>รายละเอียด</th>
    <th>รหัสสั่งซื้อ</th>
</tr>
</thead>
<tbody>
<?php
// เก็บข้อมูลทั้งหมดก่อน
$rows = [];
while($row = $result->fetch_assoc()){
    $rows[] = $row;
}

// เริ่มเลขลำดับจากจำนวนแถวทั้งหมด
$i = count($rows);

foreach($rows as $row):
    $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : ($row['product_name'] ?? '-');
    $order_id_display = $row['order_detail_id'] ?? '-';
?>
<tr>
    <td><?= $i-- ?></td> <!-- เลข 1 จะอยู่ด้านล่าง -->
    <td><?= $row['transaction_id'] ?></td>
    <td><?= $row['transaction_date'] ?></td>
    <td><?= thai_type($row['transaction_type']) ?></td>
    <td><?= number_format($row['amount'],2) ?></td>
    <td><?= htmlspecialchars($desc) ?></td>
    <td><?= htmlspecialchars($order_id_display) ?></td>
</tr>
<?php endforeach; ?>
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
