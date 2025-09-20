<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว

$full_rate = 300;
$half_rate = 150; // ไม่ได้ใช้ในข้อมูล attendance แต่เก็บไว้
$late_rate = 300;

// เลือกเดือน/ปี จาก GET ถ้าไม่มีใช้เดือนปัจจุบัน
$selected_month = isset($_GET['month']) ? date('Y-m', strtotime($_GET['month'])) : date('Y-m');

$calculated_data = [];

// ดึงข้อมูล attendance ของเดือนที่เลือก
$sql = "
SELECT e.employee_id, e.full_name, a.attend_date, a.morning, a.afternoon
FROM employees e
LEFT JOIN attendances a 
    ON e.employee_id = a.employee_id
    AND DATE_FORMAT(a.attend_date,'%Y-%m') = '{$selected_month}'
WHERE e.deleted = 0
ORDER BY e.employee_id, a.attend_date
";

$result = $conn->query($sql);
$temp = [];

while($row = $result->fetch_assoc()){
    $emp = $row['employee_id'];
    if(!isset($temp[$emp])){
        $temp[$emp] = [
            'full_name' => $row['full_name'],
            'full' => 0,
            'half' => 0, // ไม่ได้ใช้
            'late' => 0,
            'leave' => 0,
            'absent' => 0,
            'work_days' => 0
        ];
    }

    $morning = $row['morning'];
    $afternoon = $row['afternoon'];
    
    // นับตามสถานะของแต่ละช่วงเวลา
    if($morning === 'present' && $afternoon === 'present') {
        $temp[$emp]['full']++;
    } else {
        if($morning === 'present' || $afternoon === 'present') {
            $temp[$emp]['half']++;
        }
        if($morning === 'late' || $afternoon === 'late') {
            $temp[$emp]['late']++;
        }
        if($morning === 'leave' || $afternoon === 'leave') {
            $temp[$emp]['leave']++;
        }
        if($morning === 'absent' || $afternoon === 'absent') {
            $temp[$emp]['absent']++;
        }
    }
    $temp[$emp]['work_days'] = $temp[$emp]['full'] + ($temp[$emp]['half'] * 0.5);

}

// คำนวณเงินเดือน
foreach($temp as $emp => $data){
    $amount = ($data['full']*$full_rate) + ($data['half']*$half_rate) + ($data['late']*$late_rate);
    $calculated_data[$emp] = $data;
    $calculated_data[$emp]['amount'] = $amount;
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>รายงานเช็คชื่อและเงินเดือน</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
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
  margin-bottom: 1rem;
  font-weight: 700;
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

/* --- Filter & Table Container --- */
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
  margin-bottom: 1.5rem;
}

.filter label {
  font-weight: 500;
  color: #7f8c8d;
}

.filter select,
.filter input,
.filter button {
  padding: 0.75rem;
  border: 1px solid #e0e6ea;
  border-radius: 8px;
  font-family: 'Sarabun', sans-serif;
  font-size: 1rem;
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
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 1rem;
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

table {
  width: 100%;
  border-collapse: collapse;
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
  <h2>ข้อมูลเงินเดือน</h2>
  <a href="dashboard.php"><i class="fas fa-home"></i>&nbsp; <span>กลับ</span></a>
  <a href="employee_dashboard.php" class="active"><i class="fas fa-users"></i>&nbsp; <span>รายการเช็คชื่อ</span></a>
  <a href="employee_graphs.php"><i class="fas fa-chart-pie"></i>&nbsp; <span>รายงานกราฟเงิน </span></a>
</div>

<div class="main" id="main">
<h1><i class="fas fa-user-check"></i>&nbsp; รายงานเช็คชื่อและเงินเดือน </h1>

<div class="container">
  <div class="filter">
    <form method="GET">
      <label>เลือกเดือน:</label>
      <input type="month" name="month" value="<?= $selected_month ?>" onchange="this.form.submit()">
    </form>
  </div>

  <?php if(!empty($calculated_data)): ?>
  <table>
    <thead>
      <tr>
        <th>ลำดับ</th>
        <th>รหัสพนักงาน</th>
        <th>ชื่อ-สกุล</th>
        <th>วันเต็ม</th>
        <th>ครึ่งวัน</th>
        <th>วันสาย</th>
        <th>วันลา</th>
        <th>วันขาด</th>
        <th>จำนวนวันทำงาน</th>
        <th>เงินเดือน (บาท)</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $rows = $calculated_data;
      $i = count($rows);
      foreach($rows as $id=>$d):
      ?>
      <tr>
        <td><?= $i-- ?></td>
        <td><?= $id ?></td>
        <td><?= $d['full_name'] ?></td>
        <td><?= $d['full'] ?></td>
        <td><?= $d['half'] ?></td>
        <td><?= $d['late'] ?></td>
        <td><?= $d['leave'] ?></td>
        <td><?= $d['absent'] ?></td>
        <td><?= number_format($d['work_days'],1) ?></td>
        <td><?= number_format($d['amount'],2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p style="text-align: center; color: #7f8c8d; padding: 2rem;">ไม่มีข้อมูลในเดือนนี้</p>
  <?php endif; ?>

  <div class="export-group">
      <a href="employee_export.php?month=<?= $selected_month ?>&format=pdf" class="btn-export pdf"><i class="fas fa-file-pdf"></i>&nbsp; ส่งออก PDF</a>
      <a href="employee_export.php?month=<?= $selected_month ?>&format=excel" class="btn-export excel"><i class="fas fa-file-excel"></i>&nbsp; ส่งออก Excel</a>
  </div>
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
</script>

</body>
</html>


