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
$selected_year = $_GET['year'] ?? date('Y');

// ===================== ข้อมูลสต็อกรายเดือน (Import vs Remove) =====================
$monthlyImport = array_fill(0, 12, 0);
$monthlyRemove = array_fill(0, 12, 0);
$labelsMonth = [];
for($m=1;$m<=12;$m++){
    $labelsMonth[] = thai_month($m);
}

$sqlMonthly = "SELECT 
                    MONTH(stock_date) AS m,
                    SUM(CASE WHEN stock_type = 'import' THEN quantity ELSE 0 END) AS total_import,
                    SUM(CASE WHEN stock_type = 'remove' THEN quantity ELSE 0 END) AS total_remove
                FROM stock
                WHERE YEAR(stock_date) = ?
                GROUP BY MONTH(stock_date)";
$stmt = $conn->prepare($sqlMonthly);
$stmt->bind_param("i", $selected_year);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()){
    $monthlyImport[$r['m']-1] = (float)$r['total_import'];
    $monthlyRemove[$r['m']-1] = (float)$r['total_remove'];
}

// ===================== ข้อมูลสต็อกรายปี (Import vs Remove) =====================
$yearlyImport = [];
$yearlyRemove = [];
$labelsYear = [];

$sqlYearly = "SELECT 
                YEAR(stock_date) AS y,
                SUM(CASE WHEN stock_type = 'import' THEN quantity ELSE 0 END) AS total_import,
                SUM(CASE WHEN stock_type = 'remove' THEN quantity ELSE 0 END) AS total_remove
            FROM stock
            GROUP BY YEAR(stock_date)
            ORDER BY y ASC";
$resYearly = $conn->query($sqlYearly);
while($r = $resYearly->fetch_assoc()){
    $labelsYear[] = $r['y'] + 543; // Convert to Buddhist year for display
    $yearlyImport[] = (float)$r['total_import'];
    $yearlyRemove[] = (float)$r['total_remove'];
}

// ===================== Dataset สำหรับ Chart.js =====================
$datasetsMonth = [
    [
        'label' => 'รับเข้า',
        'data' => $monthlyImport,
        'backgroundColor' => 'rgba(46, 204, 113, 0.7)',
        'borderRadius' => 5
    ],
    [
        'label' => 'จ่ายออก',
        'data' => $monthlyRemove,
        'backgroundColor' => 'rgba(231, 76, 60, 0.7)',
        'borderRadius' => 5
    ]
];

$datasetsYear = [
    [
        'label' => 'รับเข้า',
        'data' => $yearlyImport,
        'borderColor' => 'rgba(46, 204, 113, 1)',
        'backgroundColor' => 'rgba(46, 204, 113, 0.2)',
        'fill' => true,
        'tension' => 0.3
    ],
    [
        'label' => 'จ่ายออก',
        'data' => $yearlyRemove,
        'borderColor' => 'rgba(231, 76, 60, 1)',
        'backgroundColor' => 'rgba(231, 76, 60, 0.2)',
        'fill' => true,
        'tension' => 0.3
    ]
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📊 กราฟรายงานสต็อก</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');

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

    .sidebar {
        width: 250px;
        background: linear-gradient(180deg, var(--primary-color), #2980b9);
        color: white;
        padding: 1.5rem;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        transition: transform 0.3s ease-in-out;
        box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        z-index: 1000;
    }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .logo {
        width: 90px; height: 90px; border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.3);
        object-fit: cover; margin-bottom: 1rem;
    }
    .sidebar-header h3 { font-size: 1.5rem; font-weight: 700; margin: 0; }
    .sidebar a {
        color: white; text-decoration: none; font-size: 1.1rem;
        padding: 0.8rem 1rem; border-radius: 8px;
        width: 100%; text-align: left;
        transition: background-color 0.2s ease, transform 0.2s ease;
        margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem;
    }
    .sidebar a:hover { background-color: rgba(255, 255, 255, 0.15); transform: translateX(5px); }
    .sidebar a.active { background-color: rgba(0, 0, 0, 0.2); font-weight: 500; }
    .toggle-btn {
        position: fixed; top: 1rem; right: 1rem; z-index: 1001;
        background-color: var(--primary-color); color: white; border: none;
        border-radius: 50%; width: 40px; height: 40px;
        font-size: 1.5rem; cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        display: flex; justify-content: center; align-items: center;
    }

    .main {
        margin-left: 250px;
        padding: 2rem;
        flex-grow: 1;
        transition: margin-left 0.3s ease-in-out;
        width: calc(100% - 250px);
    }
    .main.full-width { margin-left: 0; width: 100%; }
    
    .header-main {
        border-bottom: 2px solid var(--primary-color);
        padding-bottom: 1.5rem;
        margin-bottom: 2rem;
    }
    .header-main h1 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem; color: var(--navy-blue);
        margin: 0; border: none;
        display: flex; align-items: center; gap: 1rem;
    }
    
    .container {
        background-color: var(--white);
        padding: 25px; border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    .container h2 {
        font-size: 1.8rem;
        color: var(--dark-teal, var(--navy-blue));
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .filter-controls {
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;
        margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--gray-border);
    }
    .filter-group { display: flex; align-items: center; gap: 0.5rem; }
    .filter-group label { font-weight: 500; }
    .filter-group select {
        padding: 0.6rem 1rem; border-radius: 8px;
        border: 1px solid var(--gray-border); font-size: 1rem;
        font-family: 'Sarabun', sans-serif;
    }
    #shareButton {
        padding: 0.7rem 1.5rem; border: none; border-radius: 8px;
        font-weight: 500; cursor: pointer; color: white; font-size: 1rem;
        background-color: var(--info); transition: all 0.2s ease;
    }
    #shareButton:hover { background-color: #8e44ad; transform: translateY(-2px); }

    /* Modal Styles */
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
        <h3>รายงานสต็อก</h3>
    </div>
    <a href="stock_dashboard.php"><i class="fas fa-table fa-fw"></i>&nbsp; <span>ตารางสต็อก</span></a>
    <a href="stock_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-bar"></i>&nbsp; กราฟรายงานสต็อก</h1>
    </div>

    <div class="container">
        <div class="filter-controls">
            <form method="get" class="filter-group">
                <label for="year-select">เลือกปี (พ.ศ.):</label>
                <select name="year" id="year-select" onchange="this.form.submit()">
                    <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?=$y?>" <?=($selected_year == $y) ? 'selected' : ''?>><?= $y + 543 ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            <button type="button" id="shareButton"><i class="fas fa-share-alt"></i>&nbsp; ส่งออกรายงาน</button>
        </div>

        <h2>สรุปยอดรับเข้า-จ่ายออกรายเดือน (ปี พ.ศ. <?= $selected_year + 543 ?>)</h2>
        <canvas id="stockChartMonth" height="120"></canvas>
    </div>

    <div class="container">
        <h2>สรุปยอดรับเข้า-จ่ายออกรายปี</h2>
        <canvas id="stockChartYear" height="120"></canvas>
    </div>
</div>

<div id="shareModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-share-alt"></i> ส่งออกรายงาน</h3>
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
    
    const chartOptions = {
        responsive: true,
        plugins: { 
            legend: { 
                position: 'top',
                labels: { font: { family: 'Sarabun', size: 14 } }
            } 
        },
        scales: { 
            y: { 
                beginAtZero: true,
                ticks: { callback: value => new Intl.NumberFormat().format(value) }
            } 
        }
    };

    const monthlyCtx = document.getElementById('stockChartMonth');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labelsMonth); ?>,
                datasets: <?= json_encode($datasetsMonth); ?>
            },
            options: chartOptions
        });
    }

    const yearlyCtx = document.getElementById('stockChartYear');
    if (yearlyCtx) {
        new Chart(yearlyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labelsYear); ?>,
                datasets: <?= json_encode($datasetsYear); ?>
            },
            options: chartOptions
        });
    }

    const shareButton = document.getElementById('shareButton');
    const modal = document.getElementById('shareModal');
    const fileSelect = document.getElementById('fileSelect');
    const downloadBtn = document.getElementById('downloadButton');
    const emailBtn = document.getElementById('emailButton');
    const messengerBtn = document.getElementById('messengerButton');
    const actionButtons = [downloadBtn, emailBtn, messengerBtn];
    
    window.openModal = (id) => document.getElementById(id).style.display = 'flex';
    window.closeModal = (id) => document.getElementById(id).style.display = 'none';

    shareButton.addEventListener('click', () => openModal('shareModal'));
    modal.addEventListener('click', e => { if (e.target === modal) closeModal('shareModal'); });
    
    function toggleActionButtons(disabled) {
        actionButtons.forEach(btn => btn.disabled = disabled);
    }

    fileSelect.addEventListener('change', function() {
        toggleActionButtons(this.value === '');
    });

    const currentParams = new URLSearchParams(window.location.search);
    const reportTitle = "รายงานกราฟสต็อกสินค้า";
    const selectedYear = <?= $selected_year ?>;

    downloadBtn.addEventListener('click', function() {
        const fileType = fileSelect.value;
        if (!fileType) return;
        const exportUrl = `stock_graphs_export.php?format=${fileType}&${currentParams.toString()}`;
        const a = document.createElement('a');
        a.href = exportUrl;
        a.download = `stock_graph_report_${selectedYear}.${fileType}`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        closeModal('shareModal');
    });

    emailBtn.addEventListener('click', function() {
        const fileType = fileSelect.value;
        if (!fileType) return;
        const exportUrl = `${window.location.origin}${window.location.pathname.replace('stock_graphs.php', '')}stock_graphs_export.php?format=${fileType}&${currentParams.toString()}`;
        const body = `สวัสดี,\n\nนี่คือลิงก์สำหรับรายงานกราฟสต็อกสินค้า:\n${exportUrl}\n\nขอบคุณ`;
        const mailtoLink = `mailto:?subject=${encodeURIComponent(reportTitle)}&body=${encodeURIComponent(body)}`;
        window.location.href = mailtoLink;
        closeModal('shareModal');
    });

    messengerBtn.addEventListener('click', function() {
        const fileType = fileSelect.value;
        if (!fileType) return;
        const appId = 'YOUR_APP_ID'; 
        if (appId === 'YOUR_APP_ID') {
            alert('กรุณาตั้งค่า Facebook App ID ก่อนใช้งาน');
            return;
        }
        const exportUrl = encodeURIComponent(`${window.location.origin}${window.location.pathname.replace('stock_graphs.php', '')}stock_graphs_export.php?format=${fileType}&${currentParams.toString()}`);
        const messengerLink = `https://www.facebook.com/dialog/send?link=${exportUrl}&app_id=${appId}&redirect_uri=${encodeURIComponent(window.location.href)}`;
        window.open(messengerLink, '_blank');
        closeModal('shareModal');
    });
});
</script>
</body>
</html>