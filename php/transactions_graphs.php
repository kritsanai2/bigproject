<?php
require_once "auth.php";
require_once "db.php";

// ฟังก์ชันช่วยแปลภาษาไทย
function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[(int)$month] ?? $month;
}

// --- รับค่าตัวกรองสำหรับกราฟแต่ละส่วน ---
$current_year = (int)date('Y');
$current_month = (int)date('m');

// ตัวกรองสำหรับกราฟรายวัน
$daily_filter_year = isset($_GET['daily_year']) ? (int)$_GET['daily_year'] : $current_year;
$daily_filter_month = isset($_GET['daily_month']) ? (int)$_GET['daily_month'] : $current_month;

// ตัวกรองสำหรับกราฟรายเดือน
$monthly_filter_year = isset($_GET['monthly_year']) ? (int)$_GET['monthly_year'] : $current_year;

// --- กำหนดปีสำหรับ Dropdown ---
$start_year = 2022; 
$end_year = 2035;

// =================== 1. ดึงข้อมูลรายวัน (แก้ไข) ===================
$numDays = cal_days_in_month(CAL_GREGORIAN, $daily_filter_month, $daily_filter_year);
$dailyIncome = array_fill(1, $numDays, 0);
$dailyExpense = array_fill(1, $numDays, 0);

// ดึงข้อมูลจาก transactions
$sqlDaily = "SELECT DAY(transaction_date) AS d, 
                SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END) AS total_income,
                SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END) AS total_expense
             FROM transactions WHERE YEAR(transaction_date)=? AND MONTH(transaction_date)=? GROUP BY d";
$stmtDaily = $conn->prepare($sqlDaily);
$stmtDaily->bind_param("ii", $daily_filter_year, $daily_filter_month);
$stmtDaily->execute();
$resDaily = $stmtDaily->get_result();
while($r = $resDaily->fetch_assoc()){
    $dailyIncome[(int)$r['d']] = (float)$r['total_income'];
    $dailyExpense[(int)$r['d']] = (float)$r['total_expense'];
}

// (เพิ่มใหม่) ดึงข้อมูลเงินเดือนมาบวกเพิ่มในรายจ่ายรายวัน
$sqlDailySalary = "SELECT DAY(pay_month) as d, total_amount FROM salary WHERE YEAR(pay_month)=? AND MONTH(pay_month)=?";
$stmtDailySalary = $conn->prepare($sqlDailySalary);
$stmtDailySalary->bind_param("ii", $daily_filter_year, $daily_filter_month);
$stmtDailySalary->execute();
$resDailySalary = $stmtDailySalary->get_result();
while($r = $resDailySalary->fetch_assoc()){
    $dailyExpense[(int)$r['d']] += (float)$r['total_amount']; // บวกเพิ่มเข้าไป
}

$labelsDay = range(1, $numDays);
$dailyIncomeData = array_values($dailyIncome);
$dailyExpenseData = array_values($dailyExpense);

// =================== 2. ดึงข้อมูลรายเดือน (แก้ไข) ===================
$monthlyIncome = array_fill(0, 12, 0);
$monthlyExpense = array_fill(0, 12, 0);

// ดึงข้อมูลจาก transactions
$sqlMonthly = "SELECT MONTH(transaction_date) AS m, 
                  SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END) AS total_income,
                  SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END) AS total_expense
               FROM transactions WHERE YEAR(transaction_date)=? GROUP BY m";
$stmtMonthly = $conn->prepare($sqlMonthly);
$stmtMonthly->bind_param("i", $monthly_filter_year);
$stmtMonthly->execute();
$resMonthly = $stmtMonthly->get_result();
while($r = $resMonthly->fetch_assoc()){
    $monthlyIncome[(int)$r['m'] - 1] = (float)$r['total_income'];
    $monthlyExpense[(int)$r['m'] - 1] = (float)$r['total_expense'];
}

// (เพิ่มใหม่) ดึงข้อมูลเงินเดือนมาบวกเพิ่มในรายจ่ายรายเดือน
$sqlMonthlySalary = "SELECT MONTH(pay_month) as m, SUM(total_amount) as total_salary FROM salary WHERE YEAR(pay_month)=? GROUP BY m";
$stmtMonthlySalary = $conn->prepare($sqlMonthlySalary);
$stmtMonthlySalary->bind_param("i", $monthly_filter_year);
$stmtMonthlySalary->execute();
$resMonthlySalary = $stmtMonthlySalary->get_result();
while($r = $resMonthlySalary->fetch_assoc()){
    $monthlyExpense[(int)$r['m'] - 1] += (float)$r['total_salary']; // บวกเพิ่มเข้าไป
}

$labelsMonth = [];
for($m=1;$m<=12;$m++) $labelsMonth[] = thai_month($m);

// =================== 3. ดึงข้อมูลรายปี (แก้ไข) ===================
$yearlyData = [];

// ดึงข้อมูลจาก transactions
$sqlYearly = "SELECT YEAR(transaction_date) AS y, 
                     SUM(CASE WHEN transaction_type='income' THEN amount ELSE 0 END) AS total_income,
                     SUM(CASE WHEN transaction_type='expense' THEN amount ELSE 0 END) AS total_expense
              FROM transactions GROUP BY y ORDER BY y ASC";
$resYearly = $conn->query($sqlYearly);
while($r = $resYearly->fetch_assoc()){
    $yearlyData[(int)$r['y']] = ['income' => (float)$r['total_income'], 'expense' => (float)$r['total_expense']];
}

// (เพิ่มใหม่) ดึงข้อมูลเงินเดือนมาบวกเพิ่มในรายจ่ายรายปี
$sqlYearlySalary = "SELECT YEAR(pay_month) as y, SUM(total_amount) as total_salary FROM salary GROUP BY y";
$resYearlySalary = $conn->query($sqlYearlySalary);
while($r = $resYearlySalary->fetch_assoc()){
    $year_key = (int)$r['y'];
    if (isset($yearlyData[$year_key])) {
        $yearlyData[$year_key]['expense'] += (float)$r['total_salary']; // บวกเพิ่มเข้าไป
    } else {
        // กรณีมีแต่ข้อมูลเงินเดือนในปีนั้นๆ
        $yearlyData[$year_key] = ['income' => 0, 'expense' => (float)$r['total_salary']];
    }
}
ksort($yearlyData); // จัดเรียงปีให้ถูกต้อง

$labelsYear = array_map(fn($y) => $y + 543, array_keys($yearlyData));
$yearlyIncome = array_column($yearlyData, 'income');
$yearlyExpense = array_column($yearlyData, 'expense');
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💧 กราฟรายงานรายรับ-รายจ่าย</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* 🎨 CSS Variables */
:root { 
    --primary-color: #3498db; 
    --secondary-color: #2c3e50; 
    --light-bg: #f0f8ff; 
    --navy-blue: #001f3f;
    --white: #ffffff; 
    --light-gray: #f8f9fa;
    --gray-border: #ced4da; 
    --text-color: #495057;
    --success: #2ecc71; 
    --danger: #e74c3c; 
    --warning: #f39c12; 
}

/* 🌍 Global Reset */
* { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
}

body { 
    font-family: 'Sarabun', sans-serif; 
    background-color: var(--light-bg); 
    display: flex; 
    color: var(--text-color); 
}

/* 📂 Sidebar */
.sidebar { 
    width: 250px; 
    background: linear-gradient(180deg, var(--primary-color), #2980b9); 
    color: white; 
    padding: 1.5rem; 
    height: 100vh; 
    position: fixed; 
    top: 0; 
    left: 0; 
    transition: transform 0.3s; 
    box-shadow: 2px 0 15px rgba(0,0,0,0.1); 
    display: flex; 
    flex-direction: column; 
    z-index: 1000; 
}

.sidebar.hidden { 
    transform: translateX(-100%); 
}

.sidebar-header { 
    text-align: center; 
    margin-bottom: 2rem; 
}

.logo { 
    width: 90px; 
    height: 90px; 
    border-radius: 50%; 
    border: 4px solid rgba(255,255,255,0.3); 
    object-fit: cover; 
    margin-bottom: 1rem; 
}

.sidebar a { 
    color: white; 
    text-decoration: none; 
    font-size: 1.1rem; 
    padding: 0.8rem 1rem; 
    border-radius: 8px; 
    width: 100%; 
    transition: all 0.2s; 
    margin-bottom: 0.5rem; 
    display: flex; 
    align-items: center; 
    gap: 0.75rem; 
}

.sidebar a:hover { 
    background-color: rgba(255,255,255,0.15); 
    transform: translateX(5px); 
}

.sidebar a.active { 
    background-color: rgba(0,0,0,0.2); 
    font-weight: 500; 
}

/* 🔘 Sidebar Toggle Button */
.toggle-btn { 
    position: fixed; 
    top: 1rem; 
    right: 1rem; 
    z-index: 1001; 
    background-color: var(--primary-color); 
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
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* 📑 Main Content */
.main { 
    margin-left: 250px; 
    padding: 2rem; 
    flex-grow: 1; 
    transition: margin-left 0.3s; 
    width: calc(100% - 250px); 
}

.main.full-width { 
    margin-left: 0; 
    width: 100%; 
}

.header-main { 
    border-bottom: 2px solid var(--primary-color); 
    padding-bottom: 1.5rem; 
    margin-bottom: 2rem; 
}

.header-main h1 { 
    font-size: 2.5rem; 
    color: var(--navy-blue); 
    display: flex; 
    align-items: center; 
    gap: 1rem; 
}

/* 📦 Container */
.container { 
    background-color: var(--white); 
    padding: 25px; 
    border-radius: 12px; 
    margin-bottom: 30px; 
    box-shadow: 0 4px 20px rgba(0,0,0,0.07); 
}

.container h2 { 
    font-size: 1.6rem; 
    color: var(--secondary-color); 
    margin-bottom: 20px; 
    padding-bottom: 10px; 
    border-bottom: 1px solid var(--light-gray); 
}

/* 🔎 Filter Controls */
.filter-controls { 
    display: flex; 
    flex-wrap: wrap; 
    justify-content: space-between; 
    align-items: center; 
    gap: 1.5rem; 
}

.filter-group { 
    display: flex; 
    flex-wrap: wrap; 
    align-items: center; 
    gap: 1rem; 
}

.filter-item { 
    display: flex; 
    align-items: center; 
    gap: 0.5rem; 
}

.filter-item label { 
    font-weight: 500; 
}

.filter-item select { 
    padding: 0.6rem 1rem; 
    border-radius: 8px; 
    border: 1px solid var(--gray-border); 
    font-size: 1rem; 
}

/* 🔘 Action Buttons */
.action-buttons { 
    display: flex; 
    gap: 0.75rem; 
}

.action-button { 
    padding: 0.7rem 1.2rem; 
    border: none; 
    border-radius: 8px; 
    font-weight: 600; 
    cursor: pointer; 
    color: white; 
    text-decoration: none; 
    display: inline-flex; 
    align-items: center; 
    gap: 0.5rem; 
    transition: all 0.2s;
}

.action-button:hover:not(:disabled) { 
    transform: translateY(-2px); 
    box-shadow: 0 4px 10px rgba(0,0,0,0.15); 
}

.btn-filter { background-color: var(--primary-color); }
.btn-pdf    { background-color: var(--danger); } 
.btn-email  { background-color: var(--warning); color: #333; }

/* 📌 Modal */
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
    width: 90%; 
    max-width: 500px; 
}

.modal-header { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    border-bottom: 1px solid #e0e0e0; 
    padding-bottom: 1rem; 
    margin-bottom: 1.5rem; 
}

.modal-header h3 { 
    color: var(--secondary-color); 
    margin: 0; 
    font-size: 1.8rem; 
}

.close-button { 
    background: none; 
    border: none; 
    font-size: 2rem; 
    cursor: pointer; 
    color: #aaa; 
    transition: all 0.2s ease; 
}

.close-button:hover { 
    color: var(--danger); 
    transform: rotate(90deg); 
}

.modal-body p { 
    color: var(--text-color); 
}

.modal-body input[type="email"] { 
    width: 100%; 
    padding: 0.75rem; 
    border-radius: 8px; 
    border: 1px solid var(--gray-border); 
    margin-top: 0.5rem; 
    font-size: 1rem; 
}

.modal-footer { 
    margin-top: 1.5rem; 
    display: flex; 
    justify-content: flex-end; 
}

    </style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../img/da.jfif" alt="โลโก้" class="logo">
        <h2>ระบบจัดการ</h2>
    </div>
    <a href="transactions_dashboard.php"><i class="fas fa-list fa-fw"></i>&nbsp; <span>รายการทั้งหมด</span></a>
    <a href="transactions_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-bar"></i>&nbsp; รายงานกราฟรายรับ-รายจ่าย</h1>
    </div>

    <div class="container">
        <h2>สรุปยอดรายวัน (เดือน <?= thai_month($daily_filter_month) ?> ปี พ.ศ. <?= $daily_filter_year + 543 ?>)</h2>
        <div class="filter-controls">
            <form method="get" class="filter-group">
                <div class="filter-item">
                    <label for="daily_month-select">เดือน:</label>
                    <select name="daily_month" id="daily_month-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ($daily_filter_month == $m) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-item">
                    <label for="daily_year-select">ปี:</label>
                    <select name="daily_year" id="daily_year-select">
                        <?php for($y = $start_year; $y <= $end_year; $y++): ?>
                        <option value="<?= $y ?>" <?= ($daily_filter_year == $y) ? 'selected' : '' ?>><?= $y + 543 ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <!-- Hidden input for other chart's filter -->
                <input type="hidden" name="monthly_year" value="<?= htmlspecialchars($monthly_filter_year) ?>">
                <button type="submit" class="action-button btn-filter"><i class="fas fa-sync-alt"></i> แสดงผล</button>
            </form>
            <div class="action-buttons">
                <button type="button" id="pdfButton" class="action-button btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="emailModalButton" class="action-button btn-email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
            </div>
        </div>
        <canvas id="dailyChart" height="120" style="margin-top: 1.5rem;"></canvas>
    </div>

    <div class="container">
        <h2>สรุปยอดรายเดือน (ปี พ.ศ. <?= $monthly_filter_year + 543 ?>)</h2>
        <div class="filter-controls">
            <form method="get" class="filter-group">
                <div class="filter-item">
                    <label for="monthly_year-select">ปี:</label>
                    <select name="monthly_year" id="monthly_year-select">
                        <?php for($y = $start_year; $y <= $end_year; $y++): ?>
                        <option value="<?= $y ?>" <?= ($monthly_filter_year == $y) ? 'selected' : '' ?>><?= $y + 543 ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <!-- Hidden inputs for other chart's filters -->
                <input type="hidden" name="daily_year" value="<?= htmlspecialchars($daily_filter_year) ?>">
                <input type="hidden" name="daily_month" value="<?= htmlspecialchars($daily_filter_month) ?>">
                <button type="submit" class="action-button btn-filter"><i class="fas fa-sync-alt"></i> แสดงผล</button>
            </form>
        </div>
        <canvas id="monthlyChart" height="120" style="margin-top: 1.5rem;"></canvas>
    </div>

    <div class="container">
        <h2>สรุปยอดรายปี</h2>
        <canvas id="yearlyChart" height="120"></canvas>
    </div>
</div>

<div class="modal-overlay" id="emailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-paper-plane"></i> ส่งรายงานทางอีเมล</h3>
            <button class="close-button" id="closeModalBtn">&times;</button>
        </div>
        <div class="modal-body">
            <p>รายงานกราฟทั้งหมดจะถูกส่งเป็นไฟล์ PDF</p>
            <label for="recipientEmail">อีเมลผู้รับ:</label>
            <input type="email" id="recipientEmail" placeholder="example@email.com" required>
            <div id="emailStatus" style="margin-top: 1rem; text-align: center;"></div>
        </div>
        <div class="modal-footer">
            <button id="sendEmailButton" class="action-button btn-email" style="color:white;">ส่ง</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- Sidebar Logic ---
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('main');
    const toggleBtn = document.getElementById('toggle-btn');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            main.classList.toggle('full-width');
        });
    }
    if (window.innerWidth <= 768) {
        sidebar.classList.add('hidden');
        main.classList.add('full-width');
    }

    // --- Chart.js Rendering ---
    let dailyChart, monthlyChart, yearlyChart;
    const chartOptions = {
        responsive: true,
        plugins: {
            legend: { display: true, position: 'top', labels: { font: { family: 'Sarabun', size: 14 } } },
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
        scales: { y: { beginAtZero: true, ticks: { callback: (value) => new Intl.NumberFormat('th-TH').format(value) } } }
    };
    
    const dailyCtx = document.getElementById('dailyChart');
    if(dailyCtx) dailyChart = new Chart(dailyCtx, { type: 'bar', data: { labels: <?= json_encode($labelsDay); ?>, datasets: [ { label:'รายรับ', data: <?= json_encode($dailyIncomeData) ?>, backgroundColor:'rgba(46, 204, 113, 0.8)', borderRadius:5 }, { label:'รายจ่าย', data: <?= json_encode($dailyExpenseData) ?>, backgroundColor:'rgba(231, 76, 60, 0.8)', borderRadius:5 } ] }, options: chartOptions });
    
    const monthlyCtx = document.getElementById('monthlyChart');
    if(monthlyCtx) monthlyChart = new Chart(monthlyCtx, { type: 'bar', data: { labels: <?= json_encode($labelsMonth); ?>, datasets: [ { label:'รายรับ', data: <?= json_encode($monthlyIncome) ?>, backgroundColor:'rgba(46, 204, 113, 0.8)', borderRadius:5 }, { label:'รายจ่าย', data: <?= json_encode($monthlyExpense) ?>, backgroundColor:'rgba(231, 76, 60, 0.8)', borderRadius:5 } ] }, options: chartOptions });
    
    const yearlyCtx = document.getElementById('yearlyChart');
    if(yearlyCtx) yearlyChart = new Chart(yearlyCtx, { type: 'line', data: { labels: <?= json_encode($labelsYear); ?>, datasets: [ { label:'รายรับ', data: <?= json_encode($yearlyIncome) ?>, borderColor:'#2ecc71', backgroundColor:'rgba(46, 204, 113, 0.2)', fill:true, tension:0.3 }, { label:'รายจ่าย', data: <?= json_encode($yearlyExpense) ?>, borderColor:'#e74c3c', backgroundColor:'rgba(231, 76, 60, 0.2)', fill:true, tension:0.3 } ] }, options: chartOptions });

    // --- Export and Modal Logic ---
    const pdfButton = document.getElementById('pdfButton');
    const emailModalButton = document.getElementById('emailModalButton');
    const emailModal = document.getElementById('emailModal');
    
    const getChartFormData = () => {
        if (!dailyChart || !monthlyChart || !yearlyChart) {
            alert('ไม่สามารถสร้างข้อมูลกราฟได้ครบถ้วน');
            return null;
        }
        const formData = new FormData();
        formData.append('dailyChartImg', dailyChart.toBase64Image());
        formData.append('monthlyChartImg', monthlyChart.toBase64Image());
        formData.append('yearlyChartImg', yearlyChart.toBase64Image());
        formData.append('daily_year', '<?= $daily_filter_year ?>');
        formData.append('daily_month', '<?= $daily_filter_month ?>');
        formData.append('monthly_year', '<?= $monthly_filter_year ?>');
        return formData;
    };
    
    if(pdfButton) {
        pdfButton.addEventListener('click', async () => {
            const formData = getChartFormData();
            if (!formData) return;
            
            pdfButton.disabled = true;
            pdfButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังสร้าง...';

            try {
                const response = await fetch('export_transactions_graphs.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`Server error: ${response.statusText}`);
                
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = `transactions_graphs_report_${Date.now()}.pdf`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } catch (error) {
                console.error('Error exporting PDF:', error);
                alert('เกิดข้อผิดพลาดในการสร้าง PDF');
            } finally {
                pdfButton.disabled = false;
                pdfButton.innerHTML = '<i class="fas fa-file-pdf"></i> PDF';
            }
        });
    }

    if (emailModal) {
        const sendEmailButton = emailModal.querySelector('#sendEmailButton');
        const recipientEmailInput = emailModal.querySelector('#recipientEmail');
        const emailStatus = emailModal.querySelector('#emailStatus');
        const closeModalBtn = emailModal.querySelector('.close-button');

        const openModal = () => { 
            emailModal.style.display = 'flex'; 
            recipientEmailInput.value = ''; 
            emailStatus.innerHTML = ''; 
            sendEmailButton.disabled = false; 
            sendEmailButton.innerHTML = 'ส่ง';
        };
        
        const closeModal = () => { emailModal.style.display = 'none'; };
        
        if(emailModalButton) emailModalButton.addEventListener('click', openModal);
        if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
        emailModal.addEventListener('click', (event) => { if (event.target === emailModal) closeModal(); });

        if(sendEmailButton) {
            sendEmailButton.addEventListener('click', async function() {
                const email = recipientEmailInput.value.trim();
                if (!email || !/\S+@\S+\.\S+/.test(email)) {
                    emailStatus.innerHTML = `<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>`;
                    return;
                }
                
                const formData = getChartFormData();
                if (!formData) return;

                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังส่ง...';
                emailStatus.innerHTML = `<span style="color: var(--primary-color);">กำลังสร้างและส่ง...</span>`;
                formData.append('email', email);

                try {
                    const response = await fetch('send_transactions_graphs_email.php', { method: 'POST', body: formData });
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
                } finally {
                    this.innerHTML = 'ส่ง';
                }
            });
        }
    }
});
</script>

</body>
</html>
