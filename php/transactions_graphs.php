<?php
session_start();
require_once "db.php"; 

// --- ฟังก์ชันเดือนภาษาไทย ---
function thai_month($m, $full = false){
    $months_full = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    $months_short = ["","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค."];
    return $full ? $months_full[(int)$m] : $months_short[(int)$m];
}

// --- รับค่าตัวกรองแยกสำหรับแต่ละกราฟ ---
$year_daily = $_GET['year_daily'] ?? date("Y");
$month_daily = $_GET['month_daily'] ?? date("m");
$year_monthly = $_GET['year_monthly'] ?? date("Y");
$type_yearly = $_GET['type_yearly'] ?? '';

// =================== กราฟรายวัน ===================
$dailyData = [];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month_daily, $year_daily);
for($d=1; $d<=$daysInMonth; $d++) {
    $dailyData[$d] = ['income'=>0, 'expense'=>0];
}
$sqlDaily = "SELECT DAY(transaction_date) as d, transaction_type, SUM(amount) as total
             FROM transactions
             WHERE YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?
             GROUP BY d, transaction_type";
$stmtDaily = $conn->prepare($sqlDaily);
$stmtDaily->bind_param("ii", $year_daily, $month_daily);
$stmtDaily->execute();
$resDaily = $stmtDaily->get_result();
while($row = $resDaily->fetch_assoc()){
    $dailyData[(int)$row['d']][$row['transaction_type']] = (float)$row['total'];
}

// =================== กราฟรายเดือน ===================
$monthlyData = [];
for($m=1; $m<=12; $m++) {
    $monthlyData[$m] = ['income'=>0, 'expense'=>0];
}
$sqlMonthly = "SELECT MONTH(transaction_date) as m, transaction_type, SUM(amount) as total
               FROM transactions
               WHERE YEAR(transaction_date) = ?
               GROUP BY m, transaction_type";
$stmtMonthly = $conn->prepare($sqlMonthly);
$stmtMonthly->bind_param("i", $year_monthly);
$stmtMonthly->execute();
$resMonthly = $stmtMonthly->get_result();
while($row=$resMonthly->fetch_assoc()){
    $monthlyData[(int)$row['m']][$row['transaction_type']] = (float)$row['total'];
}

// =================== กราฟรายปี ===================
$yearlyData = [];
$sqlYearly = "SELECT YEAR(transaction_date) as y,
                SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END) as expense
              FROM transactions
              " . ($type_yearly ? "WHERE transaction_type = ?" : "") . "
              GROUP BY y ORDER BY y";
$stmtYearly = $conn->prepare($sqlYearly);
if ($type_yearly) {
    $stmtYearly->bind_param("s", $type_yearly);
}
$stmtYearly->execute();
$resYearly = $stmtYearly->get_result();
while($row=$resYearly->fetch_assoc()){
    $yearlyData[(int)$row['y']] = ['income' => (float)$row['income'], 'expense'=> (float)$row['expense']];
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>📊 กราฟรายงานรายรับ-รายจ่าย</title>
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
            --info: #9b59b6;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Sarabun', sans-serif; background-color: var(--light-teal-bg); color: var(--text-color); display: flex; }
        
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
        .sidebar-header h2 { font-size: 1.5rem; font-weight: 700; margin: 0; }
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
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
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
        
        #globalShareButton {
            background-color: var(--info); padding: 0.75rem 1.5rem; border-radius: 8px;
            color: white; font-weight: 600; border: none; cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
        }
        #globalShareButton:hover { transform: translateY(-2px); background-color: #8e44ad; }
        
        .container {
            background: var(--card-bg); border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 1.5rem; margin-bottom: 2rem;
        }
        .container-header {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;
        }
        .container-header h2 { font-size: 1.8rem; margin: 0; color: var(--navy-blue); }
        .filter-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 1rem; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-form label { font-weight: 500; color: var(--text-color); margin-bottom: 0.25rem; font-size: 0.9rem;}
        .filter-form select, .filter-form button {
            padding: 0.6rem 1rem; border: 1px solid var(--gray-border);
            border-radius: 8px; font-size: 0.9rem; font-family: 'Sarabun', sans-serif;
        }
        .filter-form button {
            border: none; font-weight: 500; cursor: pointer; color: white;
            background-color: var(--primary-color); transition: all 0.2s;
        }
        .filter-form button:hover { background-color: #2980b9; transform: translateY(-2px); }

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
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h2>รายรับ-รายจ่าย</h2>
    </div>
    <a href="transactions_dashboard.php"><i class="fas fa-list fa-fw"></i>&nbsp; <span>รายการทั้งหมด</span></a>
    <a href="transactions_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-bar"></i>&nbsp; กราฟรายงานรายรับ-รายจ่าย</h1>
        <button type="button" id="globalShareButton"><i class="fas fa-share-alt"></i>&nbsp; แชร์หน้านี้</button>
    </div>

    <div class="container">
        <div class="container-header">
            <h2>กราฟรายวัน</h2>
            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label for="year-daily">ปี (พ.ศ.):</label>
                    <select name="year_daily" id="year-daily">
                        <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= ($year_daily == $y) ? 'selected' : ''?>><?= $y + 543 ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="month-daily">เดือน:</label>
                    <select name="month_daily" id="month-daily">
                        <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= ($month_daily == $m) ? 'selected' : ''?>><?= thai_month($m, true) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
            </form>
        </div>
        <p style="text-align:center; margin-bottom:1rem; color:#555;">(เดือน <?= thai_month($month_daily, true) ?> ปี พ.ศ. <?= $year_daily + 543 ?>)</p>
        <canvas id="dailyChart" height="120"></canvas>
    </div>

    <div class="container">
        <div class="container-header">
            <h2>กราฟรายเดือน</h2>
             <form method="get" class="filter-form">
                <div class="filter-group">
                    <label for="year-monthly">ปี (พ.ศ.):</label>
                    <select name="year_monthly" id="year-monthly">
                        <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= ($year_monthly == $y) ? 'selected' : ''?>><?= $y + 543 ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
            </form>
        </div>
        <p style="text-align:center; margin-bottom:1rem; color:#555;">(ปี พ.ศ. <?= $year_monthly + 543 ?>)</p>
        <canvas id="monthlyChart" height="120"></canvas>
    </div>

    <div class="container">
        <div class="container-header">
            <h2>กราฟรายปี</h2>
            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label for="type-yearly">ประเภท:</label>
                    <select name="type_yearly" id="type-yearly">
                        <option value="" <?= $type_yearly==''?'selected':'' ?>>ทั้งหมด</option>
                        <option value="income" <?= $type_yearly=='income'?'selected':'' ?>>เฉพาะรายรับ</option>
                        <option value="expense" <?= $type_yearly=='expense'?'selected':'' ?>>เฉพาะรายจ่าย</option>
                    </select>
                </div>
                <button type="submit"><i class="fas fa-filter"></i>&nbsp; กรอง</button>
            </form>
        </div>
        <canvas id="yearlyChart" height="120"></canvas>
    </div>
</div>

<div id="shareModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-share-alt"></i> แชร์รายงานหน้านี้</h3>
            <button class="close-button" onclick="closeModal('shareModal')">&times;</button>
        </div>
        <div class="modal-body">
            <label for="fileSelect">เลือกรูปแบบไฟล์เพื่อดาวน์โหลด:</label>
            <select id="fileSelect">
                <option value="">-- ไม่ต้องดาวน์โหลดไฟล์ --</option>
                <option value="pdf">PDF</option>
            </select>
            <div class="action-buttons" style="margin-top:1.5rem;">
                <button id="downloadButton"><i class="fas fa-download"></i> ดาวน์โหลด</button>
                <button id="emailButton"><i class="fas fa-envelope"></i> อีเมล</button>
                <button id="messengerButton"><i class="fab fa-facebook-messenger"></i> Messenger</button>
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
        responsive:true, 
        plugins:{ legend:{position:'top', labels: {font: {family:'Sarabun'}}}}, 
        scales: { y: { beginAtZero: true } } 
    };

    // กราฟรายวัน
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar', data: { labels: <?= json_encode(array_keys($dailyData)) ?>,
            datasets:[
                { label:'รายรับ', data: <?= json_encode(array_column($dailyData,'income')) ?>, backgroundColor:'rgba(46, 204, 113, 0.7)', borderRadius:5 },
                { label:'รายจ่าย', data: <?= json_encode(array_column($dailyData,'expense')) ?>, backgroundColor:'rgba(231, 76, 60, 0.7)', borderRadius:5 }
            ]
        }, options: chartOptions
    });

    // กราฟรายเดือน
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar', data: { labels: <?= json_encode(array_map(function($m){ return thai_month($m, true); }, array_keys($monthlyData))) ?>,
            datasets:[
                { label:'รายรับ', data: <?= json_encode(array_column($monthlyData,'income')) ?>, backgroundColor:'rgba(46, 204, 113, 0.7)', borderRadius:5 },
                { label:'รายจ่าย', data: <?= json_encode(array_column($monthlyData,'expense')) ?>, backgroundColor:'rgba(231, 76, 60, 0.7)', borderRadius:5 }
            ]
        }, options: chartOptions
    });

    // กราฟรายปี
    new Chart(document.getElementById('yearlyChart'), {
        type: 'line', data: { labels: <?= json_encode(array_map(function($y){ return $y + 543; }, array_keys($yearlyData))) ?>,
            datasets:[
                { label:'รายรับ', data: <?= json_encode(array_column($yearlyData,'income')) ?>, borderColor:'rgba(46, 204, 113, 1)', backgroundColor:'rgba(46, 204, 113, 0.2)', fill:true, tension:0.3 },
                { label:'รายจ่าย', data: <?= json_encode(array_column($yearlyData,'expense')) ?>, borderColor:'rgba(231, 76, 60, 1)', backgroundColor:'rgba(231, 76, 60, 0.2)', fill:true, tension:0.3 }
            ]
        }, options: chartOptions
    });

    // --- Share Modal Logic ---
    const shareButton = document.getElementById('globalShareButton');
    const modal = document.getElementById('shareModal');
    if (shareButton && modal) {
        const fileSelect = document.getElementById('fileSelect');
        const downloadBtn = document.getElementById('downloadButton');
        const emailBtn = document.getElementById('emailButton');
        const messengerBtn = document.getElementById('messengerButton');

        window.openModal = (id) => document.getElementById(id).style.display = 'flex';
        window.closeModal = (id) => document.getElementById(id).style.display = 'none';

        shareButton.addEventListener('click', () => openModal('shareModal'));
        modal.querySelector('.close-button').addEventListener('click', () => closeModal('shareModal'));
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal('shareModal');
        });

        const reportTitle = "รายงานกราฟรายรับ-รายจ่าย";

        downloadBtn.addEventListener('click', function() {
            const fileType = fileSelect.value;
            if (!fileType) {
                alert('กรุณาเลือกรูปแบบไฟล์ที่ต้องการดาวน์โหลด');
                return;
            }
            const exportUrl = `transactions_graphs_export.php?format=${fileType}&${new URLSearchParams(window.location.search).toString()}`;
            window.open(exportUrl, '_blank');
            closeModal('shareModal');
        });

        emailBtn.addEventListener('click', function() {
            const fileType = fileSelect.value;
            const pageUrl = window.location.href;
            let body = `สวัสดี,\n\nนี่คือลิงก์สำหรับรายงานกราฟรายรับ-รายจ่าย:\n${pageUrl}`;
            if(fileType){
                 const exportUrl = `${window.location.origin}${window.location.pathname.replace('transactions_graphs.php', '')}transactions_graphs_export.php?format=${fileType}&${new URLSearchParams(window.location.search).toString()}`;
                 body += `\n\nและนี่คือลิงก์สำหรับดาวน์โหลดไฟล์ ${fileType.toUpperCase()}:\n${exportUrl}`;
            }
            body += `\n\nขอบคุณ`;
            const mailtoLink = `mailto:?subject=${encodeURIComponent(reportTitle)}&body=${encodeURIComponent(body)}`;
            window.location.href = mailtoLink;
            closeModal('shareModal');
        });

        messengerBtn.addEventListener('click', function() {
            const appId = 'YOUR_APP_ID';
            if (appId === 'YOUR_APP_ID') {
                alert('กรุณาตั้งค่า Facebook App ID ในไฟล์โค้ดก่อนใช้งานฟังก์ชันนี้');
                return;
            }
            const pageUrl = encodeURIComponent(window.location.href);
            const messengerLink = `https://www.facebook.com/dialog/send?link=${pageUrl}&app_id=${appId}&redirect_uri=${pageUrl}`;
            window.open(messengerLink, '_blank');
            closeModal('shareModal');
        });
    }
});
</script>
</body>
</html>