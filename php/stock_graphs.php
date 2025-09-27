<?php
session_start();
require_once "db.php"; 
require_once __DIR__ . '/includes/auth.php';

function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[(int)$month] ?? $month;
}

// --- ตัวกรองรายวัน/เดือน/ปี ---
$daily_filter_year = (int)($_GET['daily_year'] ?? date('Y'));
$daily_filter_month = (int)($_GET['daily_month'] ?? date('m'));
$selected_year = (int)($_GET['year'] ?? date('Y'));

// --- กำหนดปี Dropdown ---
$start_year = 2023; $end_year = 2035;
$current_year = (int)date('Y');
$display_years = range(min($start_year, $current_year - 5), max($end_year, $current_year + 5)); // ขยายช่วงเล็กน้อยเพื่อรองรับข้อมูลจริง

// --- ดึงข้อมูลรายปี ---
$yearlyImport = []; $yearlyRemove = []; $labelsYear = [];
$sqlYearly = "SELECT YEAR(stock_date) AS y, 
    SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END) AS total_import,
    SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END) AS total_remove
    FROM stock GROUP BY YEAR(stock_date) ORDER BY y ASC";
$resYearly = $conn->query($sqlYearly);
while($r = $resYearly->fetch_assoc()){
    $labelsYear[] = (int)$r['y'] + 543;
    $yearlyImport[] = (float)$r['total_import'];
    $yearlyRemove[] = (float)$r['total_remove'];
}

// --- ดึงข้อมูลรายเดือน ---
$monthlyImport = array_fill(0,12,0);
$monthlyRemove = array_fill(0,12,0);
$labelsMonth = [];
for($m=1;$m<=12;$m++) $labelsMonth[] = thai_month($m);

$sqlMonthly = "SELECT MONTH(stock_date) AS m, 
    SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END) AS total_import,
    SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END) AS total_remove
    FROM stock WHERE YEAR(stock_date)=? GROUP BY MONTH(stock_date)";
$stmt = $conn->prepare($sqlMonthly);
$stmt->bind_param("i", $selected_year);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()){
    $monthlyImport[$r['m']-1] = (float)$r['total_import'];
    $monthlyRemove[$r['m']-1] = (float)$r['total_remove'];
}

// --- ดึงข้อมูลรายวัน ---
$numDays = cal_days_in_month(CAL_GREGORIAN,$daily_filter_month,$daily_filter_year);
$dailyData = array_fill(1,$numDays,['import'=>0,'remove'=>0]);
$sqlDaily = "SELECT DAY(stock_date) AS d, 
    SUM(CASE WHEN stock_type='import' THEN quantity ELSE 0 END) AS total_import,
    SUM(CASE WHEN stock_type='remove' THEN quantity ELSE 0 END) AS total_remove
    FROM stock WHERE YEAR(stock_date)=? AND MONTH(stock_date)=? GROUP BY DAY(stock_date)";
$stmtDaily = $conn->prepare($sqlDaily);
$stmtDaily->bind_param("ii",$daily_filter_year,$daily_filter_month);
$stmtDaily->execute();
$resDaily = $stmtDaily->get_result();
while($r=$resDaily->fetch_assoc()){
    $dailyData[$r['d']]['import']=(float)$r['total_import'];
    $dailyData[$r['d']]['remove']=(float)$r['total_remove'];
}
$labelsDay = []; $dailyImport=[]; $dailyRemove=[];
for($d=1;$d<=$numDays;$d++){
    $labelsDay[]=$d;
    $dailyImport[]=$dailyData[$d]['import'];
    $dailyRemove[]=$dailyData[$d]['remove'];
}

// --- Chart Datasets ---
$datasetsDay=[['label'=>'รับเข้า','data'=>$dailyImport,'backgroundColor'=>'rgba(46,204,113,0.8)','borderRadius'=>5],
    ['label'=>'จ่ายออก','data'=>$dailyRemove,'backgroundColor'=>'rgba(231,76,60,0.8)','borderRadius'=>5]];
$datasetsMonth=[['label'=>'รับเข้า','data'=>$monthlyImport,'backgroundColor'=>'rgba(46,204,113,0.8)','borderRadius'=>5],
    ['label'=>'จ่ายออก','data'=>$monthlyRemove,'backgroundColor'=>'rgba(231,76,60,0.8)','borderRadius'=>5]];
$datasetsYear=[['label'=>'รับเข้า','data'=>$yearlyImport,'borderColor'=>'#2ecc71','backgroundColor'=>'rgba(46,204,113,0.2)','fill'=>true,'tension'=>0.3],
    ['label'=>'จ่ายออก','data'=>$yearlyRemove,'borderColor'=>'#e74c3c','backgroundColor'=>'rgba(231,76,60,0.2)','fill'=>true,'tension'=>0.3]];
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
    /* ปรับโทนสีให้เป็นสีน้ำเงินสะอาดตา */
    :root { 
        --primary-color: #3498db; /* Blue: สีหลัก */
        --secondary-color: #2980b9; /* Darker Blue */
        --light-bg: #f5f8fa; /* Light background */
        --navy-blue: #001f3f; /* Text color */
        --white: #ffffff; 
        --light-gray: #ecf0f1; /* Sidebar hover/inactive text */
        --gray-border: #ced4da; 
        --text-color: #34495e; /* Body text color */
        --success: #2ecc71; 
        --danger: #e74c3c; 
        --warning: #f39c12; 
        --info: #9b59b6; 
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', sans-serif; background-color: var(--light-bg); display: flex; color: var(--text-color); }
    
    /* ปรับ Sidebar ให้ใช้สีหลักและโทนสว่าง */
    .sidebar { 
        width: 250px; 
        background: var(--primary-color); 
        color: white; 
        padding: 1.5rem; 
        height: 100vh; 
        position: fixed; 
        top: 0; 
        left: 0; 
        transition: transform 0.3s; 
        box-shadow: 4px 0 15px rgba(0,0,0,0.15); 
        display: flex; 
        flex-direction: column; 
        z-index: 1000; 
    }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .logo { width: 90px; height: 90px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.5); object-fit: cover; margin-bottom: 1rem; }
    .sidebar-header h3 { color: var(--white); font-weight: 700; font-size: 1.5rem; }

    /* ปรับ Link ใน Sidebar */
    .sidebar a { 
        color: var(--light-gray); 
        text-decoration: none; 
        font-size: 1.1rem; 
        padding: 0.9rem 1rem; 
        border-radius: 8px; 
        width: 100%; 
        transition: all 0.2s; 
        margin-bottom: 0.5rem; 
        display: flex; 
        align-items: center; 
        gap: 0.85rem; 
        border-left: 5px solid transparent; 
    }
    .sidebar a:hover { 
        background-color: var(--secondary-color); 
        color: white; 
    }
    .sidebar a.active { 
        background-color: var(--secondary-color); 
        color: white; 
        font-weight: 500; 
        border-left: 5px solid var(--warning); 
    }

    /* ปรับ Toggle Button */
    .toggle-btn { 
        position: fixed; 
        top: 1rem; 
        right: 1rem; 
        z-index: 1001; 
        background-color: var(--secondary-color); 
        color: white; 
        border: none; 
        border-radius: 50%; 
        width: 40px; 
        height: 40px; 
        font-size: 1.5rem; 
        cursor: pointer; 
        display: flex; 
        justify-content: center; 
        align-items: center; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .main { margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s; width: calc(100% - 250px); }
    .main.full-width { margin-left: 0; width: 100%; }
    
    /* ปรับ Header */
    .header-main { border-bottom: 2px solid var(--secondary-color); padding-bottom: 1.5rem; margin-bottom: 2rem; }
    .header-main h1 { font-size: 2.5rem; color: var(--secondary-color); display: flex; align-items: center; gap: 1rem; }
    
    /* ปรับ Container */
    .container { 
        background-color: var(--white); 
        padding: 25px; 
        border-radius: 12px; 
        margin-bottom: 30px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.07); 
        border: 1px solid #e0e0e0;
    }
    .container h2 { font-size: 1.8rem; color: var(--secondary-color); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0; }
    
    /* ปรับ Filter Controls ให้รองรับการแยก Form */
    .filter-controls { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
    .filter-group { display: flex; align-items: center; gap: 0.5rem; }
    .filter-group label { font-weight: 500; }
    .filter-group select { padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; transition: border-color 0.2s; }
    .filter-group select:focus { border-color: var(--primary-color); outline: none; }
    
    /* จัดกลุ่ม Form */
    .chart-filter-group { display: flex; flex-direction: column; gap: 1rem; }

    /* ปรับ Action Buttons */
    .action-button { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: white; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
    .action-button:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    .btn-pdf { background-color: var(--danger); } 
    .btn-email { background-color: var(--warning); color: var(--text-color); } 
    .btn-email:hover:not(:disabled) { background-color: #f1c40f; color: var(--text-color); } 
    .btn-email.send-button { color: white; } /* บังคับให้ปุ่มส่งใน modal เป็นสีขาว */

    /* Modal Styling */
    .modal-overlay { 
        display: none; 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background-color: rgba(0,0,0,0.5); 
        backdrop-filter: blur(4px); 
        z-index: 2000; 
        justify-content: center; 
        align-items: center; 
    }
    .modal-content { 
        background-color: var(--white); 
        padding: 30px 40px; 
        border-radius: 15px; 
        box-shadow: 0 15px 40px rgba(0,0,0,0.3); 
        width: 90%; max-width: 500px; 
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    .modal-header h3 { color: var(--secondary-color); margin: 0; font-size: 1.8rem; }
    .close-button { background: none; border: none; font-size: 2rem; cursor: pointer; color: #aaa; transition: all 0.2s ease; }
    .close-button:hover { color: var(--danger); transform: rotate(90deg); }
    .modal-body input[type="email"] { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); margin-top: 0.5rem; font-size: 1rem; }
    .modal-body p { color: var(--text-color); }
    .modal-footer { margin-top: 1.5rem; display: flex; justify-content: flex-end; }
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h3>รายงานคลังสินค้า</h3>
    </div>
    <a href="stock_dashboard.php"><i class="fas fa-table fa-fw"></i>&nbsp; <span>ตารางคลัง</span></a>
    <a href="stock_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-bar"></i>&nbsp; กราฟรายงานคลังสินค้า</h1>
    </div>

    <div class="container">
        <div class="filter-controls">
            <form method="get" class="filter-group">
                <p style="font-weight: 700; color: var(--secondary-color);">ตัวกรองกราฟรายวัน:</p>
                <label for="daily-year-select">ปี (พ.ศ.):</label>
                <select name="daily_year" id="daily-year-select" onchange="this.form.submit()">
                    <?php 
                    foreach ($display_years as $y_christian): 
                        if ($y_christian < $start_year || $y_christian > $end_year) {
                            if ($y_christian != $current_year && $y_christian != (int)$daily_filter_year) continue;
                        }
                        $y_buddhist = $y_christian + 543;
                    ?>
                    <option value="<?= $y_christian ?>" <?= ((int)$daily_filter_year == $y_christian) ? 'selected' : '' ?>>
                        <?= $y_buddhist ?>
                        <?= ($y_christian == $current_year) ? ' (ปัจจุบัน)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="daily-month-select">เดือน:</label>
                <select name="daily_month" id="daily-month-select" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ((int)$daily_filter_month == $m) ? 'selected' : '' ?>>
                            <?= thai_month($m) ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <input type="hidden" name="year" value="<?= htmlspecialchars($selected_year) ?>">
            </form>
            <div style="margin-left:auto;">
                 <button type="button" id="pdfButton" class="action-button btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="emailModalButton" class="action-button btn-email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
            </div>
        </div>
        
        <h2>สรุปยอดรับเข้า-จ่ายออกรายวัน (เดือน <?= thai_month($daily_filter_month) ?> ปี พ.ศ. <?= $daily_filter_year + 543 ?>)</h2>
        <canvas id="stockChartDay" height="120"></canvas>
    </div>
    <hr style="margin-bottom: 2rem; border-color: #e0e0e0;"> 
    
    <div class="container">
        <div class="filter-controls">
            <form method="get" class="filter-group">
                 <p style="font-weight: 700; color: var(--secondary-color);">ตัวกรองกราฟรายเดือน/รายปี:</p>
                <label for="year-select">ปี (พ.ศ.):</label>
                <select name="year" id="year-select" onchange="this.form.submit()">
                    <?php 
                    foreach ($display_years as $y_christian): 
                        if ($y_christian < $start_year || $y_christian > $end_year) {
                            if ($y_christian != $current_year && $y_christian != (int)$selected_year) continue;
                        }
                        $y_buddhist = $y_christian + 543;
                    ?>
                    <option value="<?= $y_christian ?>" <?= ((int)$selected_year == $y_christian) ? 'selected' : '' ?>>
                        <?= $y_buddhist ?>
                        <?= ($y_christian == $current_year) ? ' (ปัจจุบัน)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="daily_year" value="<?= htmlspecialchars($daily_filter_year) ?>">
                <input type="hidden" name="daily_month" value="<?= htmlspecialchars($daily_filter_month) ?>">
            </form>
        </div>

        <h2>สรุปยอดรับเข้า-จ่ายออกรายเดือน (ปี พ.ศ. <?= $selected_year + 543 ?>)</h2>
        <canvas id="stockChartMonth" height="120"></canvas>
    </div>

    <div class="container">
        <h2>สรุปยอดรับเข้า-จ่ายออกรายปี</h2>
        <canvas id="stockChartYear" height="120"></canvas>
    </div>
</div>

<div class="modal-overlay" id="emailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-paper-plane"></i> ส่งรายงานทางอีเมล</h3>
            <button class="close-button" id="closeModalBtn" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <label>รูปแบบไฟล์:</label>
            <p style="margin-bottom: 1rem;">รายงานกราฟทั้งหมดจะถูกส่งเป็นไฟล์ ** PDF **</p>
            
            <label for="recipientEmail">อีเมลผู้รับ:</label>
            <input type="email" id="recipientEmail" placeholder="example@email.com" required style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; margin-top: 5px;">
            <div id="emailStatus" style="margin-top: 1rem; text-align: center;"></div>
        </div>
        <div class="modal-footer" style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
            <button id="sendEmailButton" class="action-button btn-email send-button">
                <i class="fas fa-paper-plane"></i> ส่ง
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- Sidebar Logic ---
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('main');
    const toggleBtn = document.getElementById('toggle-btn');
    
    window.closeModal = () => { document.getElementById('emailModal').style.display = 'none'; }; // ฟังก์ชัน Global
    
    // อัปเดตการแสดงผล sidebar เมื่อโหลดหน้า
    if (window.innerWidth <= 768) {
        sidebar.classList.add('hidden');
        main.classList.add('full-width');
    }
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            main.classList.toggle('full-width');
        });
    }

    // --- Chart.js Rendering ---
    let dailyChart, monthlyChart, yearlyChart;
    const chartOptions = { responsive: true, plugins: { legend: { position: 'top', labels: { font: { family: 'Sarabun', size: 14 } } } }, scales: { y: { beginAtZero: true, ticks: { callback: value => new Intl.NumberFormat().format(value) } } } };
    
    // Daily Chart
    const dailyCtx = document.getElementById('stockChartDay');
    if (dailyCtx) { dailyChart = new Chart(dailyCtx, { type: 'bar', data: { labels: <?= json_encode($labelsDay); ?>, datasets: <?= json_encode($datasetsDay); ?> }, options: chartOptions }); }

    // Monthly Chart
    const monthlyCtx = document.getElementById('stockChartMonth');
    if (monthlyCtx) { monthlyChart = new Chart(monthlyCtx, { type: 'bar', data: { labels: <?= json_encode($labelsMonth); ?>, datasets: <?= json_encode($datasetsMonth); ?> }, options: chartOptions }); }
    
    // Yearly Chart
    const yearlyCtx = document.getElementById('stockChartYear');
    if (yearlyCtx) { yearlyChart = new Chart(yearlyCtx, { type: 'line', data: { labels: <?= json_encode($labelsYear); ?>, datasets: <?= json_encode($datasetsYear); ?> }, options: chartOptions }); }

    // --- Export and Modal Logic ---
    const pdfButton = document.getElementById('pdfButton');
    const emailModalButton = document.getElementById('emailModalButton');
    const emailModal = document.getElementById('emailModal');
    const sendEmailButton = document.getElementById('sendEmailButton');
    
    // PDF Button
    if(pdfButton) {
        pdfButton.addEventListener('click', async () => {
            if (!dailyChart || !monthlyChart || !yearlyChart) return alert('ไม่สามารถสร้างกราฟได้ครบทุกประเภท');
            
            pdfButton.disabled = true;
            pdfButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังสร้าง...';

            const formData = new FormData();
            formData.append('dailyChartImg', dailyChart.toBase64Image());
            formData.append('monthlyChartImg', monthlyChart.toBase64Image());
            formData.append('yearlyChartImg', yearlyChart.toBase64Image());
            formData.append('daily_year', <?= $daily_filter_year ?>);
            formData.append('daily_month', <?= $daily_filter_month ?>);
            formData.append('year', <?= $selected_year ?>);

            try {
                // (ต้องมั่นใจว่า export_stock_graphs.php รองรับการรับข้อมูลกราฟทั้งสาม)
                const response = await fetch('export_stock_graphs.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`Server error: ${response.statusText}`);
                
                const blob = await response.blob();
                if (blob.type !== 'application/pdf') throw new Error('Invalid file type received from server.');

                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = `stock_graph_report_<?= $selected_year ?>.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } catch (error) {
                console.error('Error exporting PDF:', error);
                alert('เกิดข้อผิดพลาดในการสร้าง PDF กรุณาตรวจสอบ Console');
            } finally {
                pdfButton.disabled = false;
                pdfButton.innerHTML = '<i class="fas fa-file-pdf"></i> PDF';
            }
        });
    }

    // Email Modal Logic
    if (emailModal) {
        const sendEmailButton = document.getElementById('sendEmailButton');
        const recipientEmailInput = document.getElementById('recipientEmail');
        const emailStatus = document.getElementById('emailStatus');
        const closeModalBtn = document.getElementById('closeModalBtn');

        const openModal = () => { 
            emailModal.style.display = 'flex'; 
            recipientEmailInput.value = ''; 
            emailStatus.innerHTML = ''; 
            sendEmailButton.disabled = false; 
        };
        
        if(emailModalButton) emailModalButton.addEventListener('click', openModal);
        if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
        emailModal.addEventListener('click', (event) => { if (event.target === emailModal) closeModal(); });

        if(sendEmailButton) {
            sendEmailButton.addEventListener('click', async function() {
                const email = recipientEmailInput.value.trim();
                if (!email || !/\S+@\S+\.\S+/.test(email)) {
                    emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>';
                    return;
                }
                if (!dailyChart || !monthlyChart || !yearlyChart) return alert('ไม่สามารถสร้างกราฟได้ครบทุกประเภท');

                this.disabled = true;
                emailStatus.innerHTML = '<span style="color: var(--warning);"><i class="fas fa-spinner fa-spin"></i> กำลังสร้างไฟล์และส่ง...</span>';

                const formData = new FormData();
                formData.append('dailyChartImg', dailyChart.toBase64Image());
                formData.append('monthlyChartImg', monthlyChart.toBase64Image());
                formData.append('yearlyChartImg', yearlyChart.toBase64Image());
                formData.append('daily_year', <?= $daily_filter_year ?>);
                formData.append('daily_month', <?= $daily_filter_month ?>);
                formData.append('year', <?= $selected_year ?>);
                formData.append('email', email);

                try {
                    // (ต้องมั่นใจว่า send_stock_graphs_email.php รองรับการรับข้อมูลกราฟทั้งสาม)
                    const response = await fetch('send_stock_graphs_email.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.status === 'success') {
                        emailStatus.innerHTML = `<span style="color: var(--success);"><i class="fas fa-check-circle"></i> ${result.message}</span>`;
                        setTimeout(closeModal, 2500);
                    } else {
                        emailStatus.innerHTML = `<span style="color: var(--danger);"><i class="fas fa-times-circle"></i> ผิดพลาด: ${result.message}</span>`;
                        this.disabled = false;
                    }
                } catch (error) {
                    emailStatus.innerHTML = `<span style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> ผิดพลาดในการเชื่อมต่อ</span>`;
                    this.disabled = false;
                }
            });
        }
    }
});
</script>
</body>
</html>
