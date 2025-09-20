<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูลจากไฟล์เดียว

// ฟังก์ชันแปลงเลขเดือนเป็นชื่อเดือนภาษาไทย
function thai_month($month){
    $months = [
        1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
        5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
        9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
    ];
    return $months[$month] ?? $month;
}

// ===================== Filter =====================
$selected_year = $_GET['year'] ?? date('Y');

// ===================== ข้อมูลรายเดือนรวม =====================
$monthlyData = [];
$labelsMonth = [];
for($m=1;$m<=12;$m++){
    $monthlyData[$m] = 0;
    $labelsMonth[$m] = thai_month($m);
}

$sql = "SELECT MONTH(pay_month) AS m, SUM(amount) AS total
        FROM employee_payments
        WHERE YEAR(pay_month)=$selected_year
        GROUP BY MONTH(pay_month)";
$res = $conn->query($sql);
while($r = $res->fetch_assoc()){
    $monthlyData[$r['m']] = (float)$r['total'];
}

// ===================== ข้อมูลรายปีรวม =====================
$yearlyData = [];
$sql = "SELECT YEAR(pay_month) AS y, SUM(amount) AS total
        FROM employee_payments
        GROUP BY YEAR(pay_month)";
$res = $conn->query($sql);
while($r = $res->fetch_assoc()){
    $yearlyData[$r['y']] = (float)$r['total'];
}

// ===================== Dataset สำหรับ Chart.js =====================
$datasetsMonth = [
    [
        'label' => 'เงินเดือนรวมบริษัท',
        'data' => array_values($monthlyData),
        'backgroundColor' => 'rgba(39,174,96,0.7)',
        'borderRadius' => 5
    ]
];

$datasetsYear = [
    [
        'label' => 'เงินเดือนรวมบริษัท',
        'data' => array_values($yearlyData),
        'borderColor' => 'rgba(39,174,96,1)',
        'backgroundColor' => 'rgba(39,174,96,0.2)',
        'fill' => true,
        'tension' => 0.3
    ]
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>📊 กราฟเงินเดือนรวมบริษัท</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

/* --- Modal Styles (New) --- */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 200;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: white;
    padding: 2rem;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    position: relative;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    animation: fadeIn 0.3s ease-in-out;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e0e6ea;
    padding-bottom: 1rem;
    margin-bottom: 1.5rem;
}

.modal-header h3 {
    margin: 0;
    color: #3498db;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #7f8c8d;
    transition: color 0.2s ease;
}

.close-btn:hover {
    color: #e74c3c;
}

.modal-body {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.btn-share {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    color: white;
    font-size: 1rem;
    background-color: #9b59b6; /* Purple */
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-share:hover {
    background-color: #8e44ad;
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
/* ==== Canva-like Share Modal ==== */
.modal-content.share-style {
    max-width: 450px;
    padding: 1.5rem;
}

.share-input {
    display: flex;
    gap: 0.5rem;
}
.share-input input {
    flex: 1;
    padding: 0.75rem;
    border: 1px solid #ccc;
    border-radius: 8px;
}
.share-input .add-btn {
    background: #3498db;
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
}
.share-input .add-btn:hover {
    background: #2980b9;
}

.share-access {
    margin: 1rem 0;
}
.share-access select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ccc;
    border-radius: 8px;
}

.btn-copy {
    width: 100%;
    background: #9b59b6;
    color: white;
    padding: 0.9rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    margin-bottom: 1.5rem;
    cursor: pointer;
}
.btn-copy:hover {
    background: #8e44ad;
}

.share-extra {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.share-extra a {
    flex: 1;
    text-align: center;
    background: #f7f7f7;
    border: none;
    border-radius: 8px;
    padding: 0.75rem;
    font-size: 0.85rem;
    cursor: pointer;
    text-decoration: none;
    color: #333;
}
.share-extra a:hover {
    background: #eaeaea;
}

</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
  <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
  <h2>รายงานกราฟเงินเดือน</h2>
  <a href="employee_dashboard.php"><i class="fas fa-users"></i>&nbsp; <span>รายการเช็คชื่อ</span></a>
  <a href="employee_graphs.php" class="active"><i class="fas fa-chart-pie"></i>&nbsp; <span>รายงานกราฟเงินเดือน</span></a>
</div>

<div class="main" id="main">
<h1><i class="fas fa-chart-line"></i>&nbsp; กราฟเงินเดือนรวมบริษัท</h1>

<div class="container">
  <div class="filter">
    <form method="get">
      <label>เลือกปี:</label>
      <select name="year" onchange="this.form.submit()">
        <?php for($y=2023;$y<=2035;$y++): ?>
        <option value="<?=$y?>" <?=($selected_year==$y)?'selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </form>
  </div>
  <h2>เงินเดือนรวมรายเดือน (<?=$selected_year?>)</h2>
  <canvas id="salaryChart" height="150"></canvas>
</div>

<div class="container">
  <h2>เงินเดือนรวมรายปี</h2>
  <canvas id="salaryYearChart" height="150"></canvas>
</div>

<div class="export-group">
    <button type="button" class="btn-share" id="shareButton">
        <i class="fas fa-share-alt"></i> แชร์
    </button>
</div>

<div class="modal-overlay" id="shareModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-file-export"></i> ส่งออกรายงาน</h3>
            <button type="button" class="close-btn">&times;</button>
        <div class="modal-body export-group">
          <button class="file-button" data-file-type="excel">เลือกไฟล์ </button>
            <a href="employee_export.php?format=pdf&year=<?= $selected_year ?>" class="btn-export pdf"><i class="fas fa-file-pdf"></i>&nbsp; ส่งออก PDF</a>
            <a href="employee_export.php?format=excel&year=<?= $selected_year ?>" class="btn-export excel"><i class="fas fa-file-excel"></i>&nbsp; ส่งออก Excel</a>
        </div>
         <div class="action-buttons">
                <button id="downloadButton">ดาวน์โหลด</button>
                <button id="emailButton">อีเมล</button>
                <button id="messengerButton">Messenger</button>
            </div>
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

document.addEventListener('DOMContentLoaded', () => {
    const salaryChart = new Chart(document.getElementById('salaryChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_values($labelsMonth)); ?>,
            datasets: <?= json_encode($datasetsMonth); ?>
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    const salaryYearChart = new Chart(document.getElementById('salaryYearChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_keys($yearlyData)); ?>,
            datasets: <?= json_encode($datasetsYear); ?>
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { y: { beginAtZero: true } }
        }
    });
});

// --- Modal Functionality for Sharing (New) ---
const shareButton = document.getElementById('shareButton');
const shareModal = document.getElementById('shareModal');
const closeBtn = shareModal.querySelector('.close-btn');

// Open the modal
shareButton.addEventListener('click', () => {
    shareModal.style.display = 'flex';
});

// Close the modal
closeBtn.addEventListener('click', () => {
    shareModal.style.display = 'none';
});

// Close the modal if clicking outside the content
shareModal.addEventListener('click', (event) => {
    if (event.target === shareModal) {
        shareModal.style.display = 'none';
    }
});
</script>
</body>
</html>
