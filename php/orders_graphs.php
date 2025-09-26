<?php
session_start();
require_once "db.php"; 

// ================== ฟังก์ชันและตัวกรอง ==================
function thai_month($m, $full = false) {
    $months_full = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
    $months_short = ["","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
    return $full ? $months_full[(int)$m] : $months_short[(int)$m];
}

$filter_year_daily = $_GET['year_daily'] ?? date("Y");
$filter_month_daily = $_GET['month_daily'] ?? date("n");
$filter_year_monthly = $_GET['year_monthly'] ?? date("Y");

// ================== กราฟรายวัน (Daily) ==================
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $filter_month_daily, $filter_year_daily);
$dailyData = array_fill(1, $daysInMonth, 0);
$sqlDaily = "SELECT DAY(order_date) as d, SUM(total_amount) as total 
             FROM orders 
             WHERE YEAR(order_date) = ? AND MONTH(order_date) = ?
             GROUP BY DAY(order_date)";
$stmtDaily = $conn->prepare($sqlDaily);
$stmtDaily->bind_param("ii", $filter_year_daily, $filter_month_daily);
$stmtDaily->execute();
$resDaily = $stmtDaily->get_result();
while($row = $resDaily->fetch_assoc()){
    $dailyData[(int)$row['d']] = (float)$row['total'];
}

// ================== กราฟรายเดือน (Monthly) ==================
$monthlyData = array_fill(1, 12, 0);
$sqlMonthly = "SELECT MONTH(order_date) as m, SUM(total_amount) as total 
               FROM orders 
               WHERE YEAR(order_date) = ?
               GROUP BY MONTH(order_date)";
$stmtMonthly = $conn->prepare($sqlMonthly);
$stmtMonthly->bind_param("i", $filter_year_monthly);
$stmtMonthly->execute();
$resMonthly = $stmtMonthly->get_result();
while($row = $resMonthly->fetch_assoc()){
    $monthlyData[(int)$row['m']] = (float)$row['total'];
}

// ================== กราฟรายปี (Yearly) ==================
$yearlyData = [];
$sqlYearly = "SELECT YEAR(order_date) as y, SUM(total_amount) as total 
              FROM orders 
              GROUP BY YEAR(order_date) ORDER BY y ASC";
$resYearly = $conn->query($sqlYearly);
while($row = $resYearly->fetch_assoc()){
    $yearlyData[$row['y'] + 543] = (float)$row['total'];
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
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
    :root { 
        --primary-color: #3498db; 
        --secondary-color: #2c3e50; 
        --light-teal-bg: #eaf6f6;
        --navy-blue: #001f3f;
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
    body { font-family: 'Sarabun', sans-serif; background-color: var(--light-teal-bg); color: var(--text-color); display: flex; }
    
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
    
    .container { background: var(--white); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 1.5rem 2rem; margin-bottom: 2rem; }
    .container-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
    .container-header h2 { font-size: 1.8rem; margin: 0; color: var(--navy-blue); }
    .filter-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem; }
    .filter-group { display: flex; flex-direction: column; }
    .filter-form label { font-weight: 500; color: var(--text-color); margin-bottom: 0.25rem; font-size: 0.9rem;}
    .filter-form select, .filter-form button { padding: 0.6rem 1rem; border: 1px solid var(--gray-border); border-radius: 8px; font-size: 0.9rem; font-family: 'Sarabun', sans-serif; }
    .filter-form button { border: none; font-weight: 500; cursor: pointer; color: white; background-color: var(--primary-color); transition: all 0.2s; }
    .filter-form button:hover { background-color: #2980b9; transform: translateY(-2px); }

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
        <h2>ข้อมูลการขาย</h2>
    </div>
    <a href="orders_dashboard.php"><i class="fas fa-receipt fa-fw"></i>&nbsp; <span>รายงานคำสั่งซื้อ</span></a>
    <a href="orders_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>กราฟยอดขาย</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-line"></i>&nbsp; กราฟสรุปยอดขาย</h1>
        <button type="button" id="shareButton"><i class="fas fa-share-alt"></i>&nbsp; ส่งออกรายงาน</button>
    </div>

    <div class="container">
        <div class="container-header">
            <h2>ยอดขายรายวัน</h2>
            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label for="month-select-daily">เดือน:</label>
                    <select name="month_daily" id="month-select-daily" onchange="this.form.submit()">
                        <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?= $m ?>" <?= ($m == $filter_month_daily) ? 'selected' : '' ?>><?= thai_month($m, true) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="year-select-daily">ปี (พ.ศ.):</label>
                    <select name="year_daily" id="year-select-daily" onchange="this.form.submit()">
                        <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= ($y == $filter_year_daily) ? 'selected' : '' ?>><?= $y + 543 ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>
        <p style="color:#555; margin-bottom:1rem; text-align:center;">(เดือน <?= thai_month($filter_month_daily, true) ?> ปี พ.ศ. <?= $filter_year_daily + 543 ?>)</p>
        <canvas id="dailyChart"></canvas>
    </div>

    <div class="container">
         <div class="container-header">
            <h2>ยอดขายรายเดือน</h2>
            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label for="year-select-monthly">ปี (พ.ศ.):</label>
                    <select name="year_monthly" id="year-select-monthly" onchange="this.form.submit()">
                        <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= ($y == $filter_year_monthly) ? 'selected' : '' ?>><?= $y + 543 ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>
        <p style="color:#555; margin-bottom:1rem; text-align:center;">(ปี พ.ศ. <?= $filter_year_monthly + 543 ?>)</p>
        <canvas id="monthlyChart"></canvas>
    </div>

    <div class="container">
        <div class="container-header">
            <h2>สรุปยอดขายรายปี</h2>
        </div>
        <canvas id="yearlyChart"></canvas>
    </div>
</div>

<div id="shareModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-share-alt"></i> ส่งออกรายงาน</h3>
            <button class="close-button" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <label for="fileSelect">เลือกรูปแบบไฟล์เพื่อส่งออก:</label>
            <select id="fileSelect">
                <option value="">-- กรุณาเลือก --</option>
                <option value="pdf">PDF</option>
                <option value="excel">Excel</option>
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
                    callback: function(value) { return new Intl.NumberFormat().format(value); }
                }
            }
        }
    };

    // --- Daily Chart ---
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: { 
            labels: <?= json_encode(array_keys($dailyData)) ?>,
            datasets: [{ 
                label: 'ยอดขาย', 
                data: <?= json_encode(array_values($dailyData)) ?>, 
                backgroundColor: 'rgba(52, 152, 219, 0.7)', 
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1, borderRadius: 5 
            }]
        }, 
        options: chartOptions
    });

    // --- Monthly Chart ---
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: { 
            labels: <?= json_encode(array_map(function($m){ return thai_month($m, true); }, array_keys($monthlyData))) ?>,
            datasets: [{ 
                label: 'ยอดขาย', 
                data: <?= json_encode(array_values($monthlyData)) ?>, 
                backgroundColor: 'rgba(26, 188, 156, 0.7)',
                borderColor: 'rgba(26, 188, 156, 1)',
                borderWidth: 1, borderRadius: 5 
            }]
        }, 
        options: chartOptions
    });

    // --- Yearly Chart ---
    new Chart(document.getElementById('yearlyChart'), {
        type: 'line',
        data: { 
            labels: <?= json_encode(array_keys($yearlyData)) ?>,
            datasets: [{ 
                label: 'ยอดขาย', 
                data: <?= json_encode(array_values($yearlyData)) ?>, 
                borderColor: 'rgba(155, 89, 182, 1)', 
                backgroundColor: 'rgba(155, 89, 182, 0.2)', 
                fill: true, tension:0.3 
            }]
        }, 
        options: chartOptions
    });
    
    // --- Share Modal Logic (เพิ่มกลับเข้ามา) ---
    const shareButton = document.getElementById('shareButton');
    const modal = document.getElementById('shareModal');
    if (shareButton && modal) {
        const fileSelect = modal.querySelector('#fileSelect');
        const downloadBtn = modal.querySelector('#downloadButton');
        const emailBtn = modal.querySelector('#emailButton');
        const messengerBtn = modal.querySelector('#messengerButton');
        const actionButtons = [downloadBtn, emailBtn, messengerBtn];

        window.openModal = () => modal.style.display = 'flex';
        window.closeModal = () => {
            modal.style.display = 'none';
            if(fileSelect) fileSelect.value = '';
            toggleActionButtons(true);
        };
        
        shareButton.addEventListener('click', openModal);
        modal.querySelector('.close-button').addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });

        const toggleActionButtons = (disabled) => {
            actionButtons.forEach(button => {
                if(button) button.disabled = disabled;
            });
        };

        if(fileSelect) {
            fileSelect.addEventListener('change', function() {
                toggleActionButtons(this.value === '');
            });
        }
        
        const currentParams = new URLSearchParams(window.location.search);
        const reportTitle = "รายงานกราฟยอดขาย";
        const exportFileName = 'orders_graphs_export.php'; 

        if(downloadBtn) {
            downloadBtn.addEventListener('click', function() {
                const fileType = fileSelect.value;
                if (!fileType) return;
                const exportUrl = `${exportFileName}?format=${fileType}&${currentParams.toString()}`;
                window.open(exportUrl, '_blank');
                closeModal();
            });
        }
        if(emailBtn) {
            emailBtn.addEventListener('click', function() {
                const fileType = fileSelect.value;
                if (!fileType) return;
                const exportUrl = `${window.location.origin}${window.location.pathname.replace('orders_graphs.php', '')}${exportFileName}?format=${fileType}&${currentParams.toString()}`;
                const body = `สวัสดี,\n\nนี่คือลิงก์สำหรับรายงานกราฟยอดขาย:\n${exportUrl}\n\nขอบคุณ`;
                const mailtoLink = `mailto:?subject=${encodeURIComponent(reportTitle)}&body=${encodeURIComponent(body)}`;
                window.location.href = mailtoLink;
                closeModal();
            });
        }
        if(messengerBtn) {
            messengerBtn.addEventListener('click', function() {
                const fileType = fileSelect.value;
                if (!fileType) return;
                const appId = 'YOUR_APP_ID';
                if (appId === 'YOUR_APP_ID') {
                    alert('กรุณาตั้งค่า Facebook App ID ในไฟล์โค้ดก่อนใช้งานฟังก์ชันนี้');
                    return;
                }
                const exportUrl = encodeURIComponent(`${window.location.origin}${window.location.pathname.replace('orders_graphs.php', '')}${exportFileName}?format=${fileType}&${currentParams.toString()}`);
                const messengerLink = `https://www.facebook.com/dialog/send?link=${exportUrl}&app_id=${appId}&redirect_uri=${encodeURIComponent(window.location.href)}`;
                window.open(messengerLink, '_blank');
                closeModal();
            });
        }
    }
});
</script>

</body>
</html>