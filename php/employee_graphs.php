<?php
session_start();
require_once "db.php"; 

// ฟังก์ชันแปลงเลขเดือนเป็นชื่อเดือนภาษาไทย
function thai_month($month){
    $months = [
        1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
        5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
        9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
    ];
    return $months[(int)$month] ?? $month;
}

// ===================== Filter =====================
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// ===================== ข้อมูลรายเดือนรวม =====================
$monthlyData = array_fill(1, 12, 0); // Use keys 1-12
$labelsMonth = [];
for($m=1;$m<=12;$m++){
    $labelsMonth[] = thai_month($m);
}

$sqlMonthly = "SELECT MONTH(pay_month) AS m, SUM(amount) AS total
               FROM employee_payments
               WHERE YEAR(pay_month) = ?
               GROUP BY MONTH(pay_month)";
$stmtMonthly = $conn->prepare($sqlMonthly);
$stmtMonthly->bind_param("i", $selected_year);
$stmtMonthly->execute();
$resMonthly = $stmtMonthly->get_result();
while($r = $resMonthly->fetch_assoc()){
    $monthlyData[(int)$r['m']] = (float)$r['total'];
}

// ===================== ข้อมูลรายปีรวม =====================
$yearlyData = [];
$sqlYearly = "SELECT YEAR(pay_month) AS y, SUM(amount) AS total
              FROM employee_payments
              GROUP BY YEAR(pay_month)
              ORDER BY y ASC";
$resYearly = $conn->query($sqlYearly);
if($resYearly) {
    while($r = $resYearly->fetch_assoc()){
        $yearlyData[$r['y'] + 543] = (float)$r['total']; // Convert to Buddhist year
    }
}

// ===================== Dataset สำหรับ Chart.js =====================
$datasetsMonth = [
    [
        'label' => 'เงินเดือนรวม (บาท)',
        'data' => array_values($monthlyData),
        'backgroundColor' => 'rgba(52, 152, 219, 0.7)',
        'borderColor' => 'rgba(52, 152, 219, 1)',
        'borderWidth' => 1,
        'borderRadius' => 5
    ]
];

$datasetsYear = [
    [
        'label' => 'เงินเดือนรวม (บาท)',
        'data' => array_values($yearlyData),
        'borderColor' => 'rgba(26, 188, 156, 1)',
        'backgroundColor' => 'rgba(26, 188, 156, 0.2)',
        'fill' => true,
        'tension' => 0.3,
        'pointBackgroundColor' => 'rgba(26, 188, 156, 1)',
        'pointBorderColor' => '#fff',
        'pointHoverRadius' => 7,
        'pointHoverBackgroundColor' => '#fff',
        'pointHoverBorderColor' => 'rgba(26, 188, 156, 1)'
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
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --light-teal-bg: #eaf6f6;
        --navy-blue: #001f3f;
        --gold-accent: #fca311;
        --white: #ffffff;
        --light-gray: #f8f9fa;
        --gray-border: #ced4da;
        --text-color: #495057;
        --success: #2ecc71;
        --danger: #e74c3c;
        --warning: #f39c12;
        --info: #9b59b6;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Sarabun', sans-serif;
        background-color: var(--light-teal-bg);
        color: var(--text-color);
        display: flex;
    }

    .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary-color), #2980b9); color: white; padding: 1.5rem; height: 100vh; position: fixed; top: 0; left: 0; transition: transform 0.3s ease-in-out; box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; z-index: 1000; }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .logo { width: 90px; height: 90px; border-radius: 50%; border: 4px solid rgba(255, 255, 255, 0.3); object-fit: cover; margin-bottom: 1rem; }
    .sidebar-header h2 { font-size: 1.5rem; font-weight: 700; margin: 0; }
    .sidebar a { color: white; text-decoration: none; font-size: 1.1rem; padding: 0.8rem 1rem; border-radius: 8px; width: 100%; text-align: left; transition: background-color 0.2s ease, transform 0.2s ease; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
    .sidebar a:hover { background-color: rgba(255, 255, 255, 0.15); transform: translateX(5px); }
    .sidebar a.active { background-color: rgba(0, 0, 0, 0.2); font-weight: 500; }
    .toggle-btn { position: fixed; top: 1rem; right: 1rem; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); display: flex; justify-content: center; align-items: center; }

    .main { margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s ease-in-out; width: calc(100% - 250px); }
    .main.full-width { margin-left: 0; width: 100%; }
    
    .header-main { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border-bottom: 2px solid var(--primary-color); padding-bottom: 1.5rem; margin-bottom: 2rem; }
    .header-main h1 { font-family: 'Playfair Display', serif; font-size: 2.5rem; color: var(--navy-blue); margin: 0; border: none; display: flex; align-items: center; gap: 1rem; }
    #shareButton { background-color: var(--info); padding: 0.75rem 1.5rem; border-radius: 8px; color: white; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
    #shareButton:hover { transform: translateY(-2px); background-color: #8e44ad; }
    
    .container { background: var(--white); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 1.5rem; margin-bottom: 2rem; }
    .container-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; }
    .container-header h2 { font-size: 1.8rem; margin: 0; color: var(--navy-blue); }
    .filter-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem; }
    .filter-group label { font-weight: 500; color: var(--text-color); margin-bottom: 0.25rem; font-size: 0.9rem;}
    .filter-group select { padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 0.9rem; font-family: 'Sarabun', sans-serif; }
    
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 31, 63, 0.6); backdrop-filter: blur(5px); z-index: 2000; justify-content: center; align-items: center; }
    .modal-content { background-color: var(--white); padding: 30px 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 500px; position: relative; animation: fadeInScale 0.4s ease-out; }
    @keyframes fadeInScale { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    .modal-header h3 { margin: 0; color: var(--navy-blue); font-size: 1.8rem; }
    .close-button { background: none; border: none; font-size: 2rem; cursor: pointer; color: #aaa; transition: color 0.2s ease, transform 0.2s ease; }
    .close-button:hover { color: var(--danger); transform: rotate(90deg); }
    .modal-body label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
    .modal-body select { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); margin-bottom: 1.5rem; font-size: 1rem; }
    .action-buttons { display: flex; gap: 1rem; }
    .action-buttons button { flex-grow: 1; padding: 0.8rem 1rem; border-radius: 8px; border: none; color: white; font-weight: 500; cursor: pointer; transition: all 0.2s; }
    .action-buttons button:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    #downloadButton { background-color: var(--success); }
    #emailButton { background-color: var(--warning); color: #212529;}
    #messengerButton { background-color: var(--primary-color); }
    .action-buttons button:disabled { background-color: #bdc3c7; cursor: not-allowed; opacity: 0.7; transform: none; box-shadow: none; }

</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../img/da.jfif" alt="โลโก้" class="logo">
        <h2>รายงานเงินเดือน</h2>
    </div>
    <a href="employee_dashboard.php"><i class="fas fa-users fa-fw"></i>&nbsp; <span>รายการเช็คชื่อ</span></a>
    <a href="employee_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>กราฟเงินเดือน</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-line"></i>&nbsp; กราฟเงินเดือนรวมบริษัท</h1>
        <button type="button" id="shareButton"><i class="fas fa-share-alt"></i>&nbsp; แชร์หน้านี้</button>
    </div>

    <div class="container">
        <div class="container-header">
             <h2>เงินเดือนรวมรายเดือน</h2>
            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label for="year-select">ปี (พ.ศ.):</label>
                    <select name="year" id="year-select" onchange="this.form.submit()">
                        <?php 
                            $all_years = array_keys($yearlyData);
                            $min_year_buddhist = !empty($all_years) ? min($all_years) : date('Y') + 543 - 5;
                            $max_year_buddhist = !empty($all_years) ? max($all_years) : date('Y') + 543;
                            for($y_buddhist = $max_year_buddhist; $y_buddhist >= $min_year_buddhist; $y_buddhist--): 
                                $y_christian = $y_buddhist - 543;
                        ?>
                        <option value="<?= $y_christian ?>" <?= ($selected_year == $y_christian) ? 'selected' : '' ?>><?= $y_buddhist ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>
         <p style="text-align:center; margin-bottom:1rem; color:#555;">(ปี พ.ศ. <?= $selected_year + 543 ?>)</p>
        <canvas id="salaryChart" height="120"></canvas>
    </div>

    <div class="container">
         <h2>สรุปเงินเดือนรวมรายปี</h2>
        <canvas id="salaryYearChart" height="120"></canvas>
    </div>
</div>

<div id="shareModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-share-alt"></i> แชร์รายงาน</h3>
            <button class="close-button" onclick="closeModal('shareModal')">&times;</button>
        </div>
        <div class="modal-body">
            <label for="fileSelect">เลือกรูปแบบไฟล์เพื่อส่งออก:</label>
            <select id="fileSelect">
                <option value="">-- กรุณาเลือก --</option>
                <option value="pdf">PDF</option>
            </select>
            <div class="action-buttons">
                <button id="downloadButton" disabled><i class="fas fa-download"></i> ดาวน์โหลด</button>
                <button id="emailButton" disabled><i class="fas fa-envelope"></i> อีเมล</button>
                <button id="messengerButton" disabled><i class="fab fa-facebook-messenger"></i> Messenger</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- Sidebar Toggle ---
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('main');
    const toggleBtn = document.getElementById('toggle-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            main.classList.toggle('full-width');
        });
    }
    if (window.matchMedia('(max-width: 768px)').matches) {
        sidebar.classList.add('hidden');
        main.classList.add('full-width');
    }
    
    // --- Chart Config ---
    const chartOptions = {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) { label += ': '; }
                        if (context.parsed.y !== null) {
                            label += new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(context.parsed.y);
                        }
                        return label;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value) => new Intl.NumberFormat().format(value)
                }
            }
        }
    };

    // --- Monthly Salary Chart ---
    new Chart(document.getElementById('salaryChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsMonth); ?>,
            datasets: <?= json_encode($datasetsMonth); ?>
        },
        options: chartOptions
    });

    // --- Yearly Salary Chart ---
    new Chart(document.getElementById('salaryYearChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_keys($yearlyData)); ?>,
            datasets: <?= json_encode($datasetsYear); ?>
        },
        options: chartOptions
    });

    // --- Share Modal ---
    const shareButton = document.getElementById('shareButton');
    const modal = document.getElementById('shareModal');
    if (shareButton && modal) {
        const fileSelect = modal.querySelector('#fileSelect');
        const actionButtons = modal.querySelectorAll('.action-buttons button');
        
        window.openModal = (id) => document.getElementById(id).style.display = 'flex';
        window.closeModal = (id) => {
            document.getElementById(id).style.display = 'none';
            fileSelect.value = '';
            actionButtons.forEach(btn => btn.disabled = true);
        };
        
        shareButton.addEventListener('click', () => openModal('shareModal'));
        modal.querySelector('.close-button').addEventListener('click', () => closeModal('shareModal'));
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal('shareModal'); });

        fileSelect.addEventListener('change', () => {
            const disabled = fileSelect.value === "";
            actionButtons.forEach(btn => btn.disabled = disabled);
        });
        
        const currentParams = new URLSearchParams(window.location.search);
        const reportTitle = "รายงานกราฟเงินเดือน";
        const exportFileName = 'employee_graphs_export.php';

        modal.querySelector('#downloadButton').addEventListener('click', () => {
             const fileType = fileSelect.value;
             if (!fileType) return;
             const exportUrl = `${exportFileName}?format=${fileType}&${currentParams.toString()}`;
             window.open(exportUrl, '_blank');
             closeModal('shareModal');
        });
    }
});
</script>

</body>
</html>