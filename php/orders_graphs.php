<?php
session_start();
require_once "db.php"; 
require_once __DIR__ . '/includes/auth.php';

// ================== ฟังก์ชันและตัวกรอง ==================
function thai_month($m, $full = true) {
    $months_full = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
    return $months_full[(int)$m] ?? '';
}

// --- รับค่าตัวกรองแบบรวมศูนย์ ---
$selected_year = (int)($_GET['year'] ?? date("Y"));
$selected_month = (int)($_GET['month'] ?? date("m"));

// --- ดึงข้อมูลสำหรับ Dropdown ปี ---
$start_year = 2023; 
$end_year = 2035;
$current_year = (int)date('Y');
$display_years = range(min($start_year, $current_year - 5), max($end_year, $current_year + 5));

// ================== 1. กราฟรายวัน (ใช้ $selected_year, $selected_month) ==================
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
$dailyData = array_fill(1, $daysInMonth, 0);
$sqlDaily = "SELECT DAY(order_date) as d, SUM(total_amount) as total 
             FROM orders 
             WHERE YEAR(order_date) = ? AND MONTH(order_date) = ?
             GROUP BY DAY(order_date)";
$stmtDaily = $conn->prepare($sqlDaily);
$stmtDaily->bind_param("ii", $selected_year, $selected_month);
$stmtDaily->execute();
$resDaily = $stmtDaily->get_result();
while($row = $resDaily->fetch_assoc()){
    $dailyData[(int)$row['d']] = (float)$row['total'];
}
$labelsDay = array_keys($dailyData);
$dailyValues = array_values($dailyData);

// ================== 2. กราฟรายเดือน (ใช้ $selected_year) ==================
$monthlyData = array_fill(1, 12, 0);
$sqlMonthly = "SELECT MONTH(order_date) as m, SUM(total_amount) as total 
               FROM orders 
               WHERE YEAR(order_date) = ?
               GROUP BY MONTH(order_date)";
$stmtMonthly = $conn->prepare($sqlMonthly);
$stmtMonthly->bind_param("i", $selected_year);
$stmtMonthly->execute();
$resMonthly = $stmtMonthly->get_result();
while($row = $resMonthly->fetch_assoc()){
    $monthlyData[(int)$row['m']] = (float)$row['total'];
}
$labelsMonth = array_map(function($m){ return thai_month($m, true); }, array_keys($monthlyData));
$monthlyValues = array_values($monthlyData);

// ================== 3. กราฟรายปี (ไม่ใช้ตัวกรอง) ==================
$yearlyData = [];
$sqlYearly = "SELECT YEAR(order_date) as y, SUM(total_amount) as total 
              FROM orders 
              GROUP BY YEAR(order_date) ORDER BY y ASC";
$resYearly = $conn->query($sqlYearly);
while($row = $resYearly->fetch_assoc()){
    $yearlyData[$row['y']] = (float)$row['total'];
}
$labelsYear = array_map(function($y){ return $y + 543; }, array_keys($yearlyData));
$yearlyValues = array_values($yearlyData);
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
<style>
    :root { 
        --primary-color: #3498db; --secondary-color: #2c3e50; --light-bg: #f5f8fa; 
        --navy-blue: #001f3f; --white: #ffffff; --light-gray: #ecf0f1; 
        --gray-border: #ced4da; --text-color: #34495e; --success: #2ecc71; 
        --danger: #e74c3c; --warning: #f39c12; --info: #9b59b6; 
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', sans-serif; background-color: var(--light-bg); display: flex; color: var(--text-color); }
    .sidebar { width: 250px; background: var(--primary-color); color: white; padding: 1.5rem; height: 100vh; position: fixed; top: 0; left: 0; transition: transform 0.3s; box-shadow: 4px 0 15px rgba(0,0,0,0.15); display: flex; flex-direction: column; z-index: 1000; }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .logo { width: 90px; height: 90px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.5); object-fit: cover; margin-bottom: 1rem; }
    .sidebar-header h3 { color: var(--white); font-weight: 700; font-size: 1.5rem; }
    .sidebar a { color: var(--light-gray); text-decoration: none; font-size: 1.1rem; padding: 0.9rem 1rem; border-radius: 8px; width: 100%; transition: all 0.2s; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.85rem; border-left: 5px solid transparent; }
    .sidebar a:hover { background-color: var(--secondary-color); color: white; }
    .sidebar a.active { background-color: var(--secondary-color); color: white; font-weight: 500; border-left: 5px solid var(--warning); }
    .toggle-btn { position: fixed; top: 1rem; right: 1rem; z-index: 1001; background-color: var(--secondary-color); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; cursor: pointer; display: flex; justify-content: center; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .main { margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s; width: calc(100% - 250px); }
    .main.full-width { margin-left: 0; width: 100%; }
    .header-main { border-bottom: 2px solid var(--secondary-color); padding-bottom: 1.5rem; margin-bottom: 2rem; }
    .header-main h1 { font-size: 2.5rem; color: var(--secondary-color); display: flex; align-items: center; gap: 1rem; }
    .container { background-color: var(--white); padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.07); border: 1px solid #e0e0e0; }
    .container h2 { font-size: 1.8rem; color: var(--secondary-color); margin-bottom: 1rem; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0; }
    .filter-container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
    .filter-form { display: flex; align-items: center; gap: 1rem; }
    .filter-form label { font-weight: 500; }
    .filter-form select { padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; }
    .actions-group { display: flex; gap: 0.5rem; }
    .action-button { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: white; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
    .action-button:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    .btn-pdf { background-color: var(--danger); }
    .btn-email { background-color: var(--warning); color: var(--text-color); }
    .btn-email.send-button { color: white; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 2000; justify-content: center; align-items: center; }
    .modal-content { background-color: var(--white); padding: 30px 40px; border-radius: 15px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); width: 90%; max-width: 500px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    .modal-header h3 { color: var(--secondary-color); margin: 0; font-size: 1.8rem; }
    .close-button { background: none; border: none; font-size: 2rem; cursor: pointer; color: #aaa; transition: all 0.2s ease; }
    .close-button:hover { color: var(--danger); transform: rotate(90deg); }
    .modal-body p { color: var(--text-color); }
    .modal-footer { margin-top: 1.5rem; display: flex; justify-content: flex-end; }
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../img/da.jfif" alt="โลโก้" class="logo">
        <h3>ข้อมูลการขาย</h3>
    </div>
    <a href="orders_dashboard.php"><i class="fas fa-receipt fa-fw"></i>&nbsp; <span>รายงานคำสั่งซื้อ</span></a>
    <a href="orders_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>กราฟยอดขาย</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-line"></i>&nbsp; กราฟสรุปยอดขาย</h1>
    </div>

    <div class="container">
        <div class="filter-container">
            <form method="get" class="filter-form" id="filterForm">
                <label for="month-select">เลือกเดือน/ปี (พ.ศ.):</label>
                <select name="month" id="month-select">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($selected_month == $m) ? 'selected' : '' ?>><?= thai_month($m, true) ?></option>
                    <?php endfor; ?>
                </select>
                <select name="year" id="year-select">
                    <?php foreach ($display_years as $y): ?>
                    <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>><?= $y + 543 ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="actions-group">
                <button type="button" id="pdfButton" class="action-button btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="emailModalButton" class="action-button btn-email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
            </div>
        </div>
    </div>

    <div class="container">
        <h2>ยอดขายรายวัน (<?= thai_month($selected_month, true) ?> <?= $selected_year + 543 ?>)</h2>
        <canvas id="dailyChart" height="120"></canvas>
    </div>
    
    <div class="container">
        <h2>ยอดขายรายเดือน (ปี <?= $selected_year + 543 ?>)</h2>
        <canvas id="monthlyChart" height="120"></canvas>
    </div>

    <div class="container">
        <h2>สรุปยอดขายรายปี</h2>
        <canvas id="yearlyChart" height="120"></canvas>
    </div>
</div>

<div class="modal-overlay" id="emailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-paper-plane"></i> ส่งรายงานทางอีเมล</h3>
            <button class="close-button" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 1rem;">รายงานกราฟยอดขายทั้งหมดจะถูกส่งเป็นไฟล์ PDF</p>
            <label for="recipientEmail">อีเมลผู้รับ:</label>
            <input type="email" id="recipientEmail" placeholder="example@email.com" required style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; margin-top: 5px;">
            <div id="emailStatus" style="margin-top: 1rem; text-align: center;"></div>
        </div>
        <div class="modal-footer">
            <button id="sendEmailButton" class="action-button btn-email send-button"><i class="fas fa-paper-plane"></i> ส่ง</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- Sidebar & Filter Logic ---
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('main');
    const toggleBtn = document.getElementById('toggle-btn');
    const filterForm = document.getElementById('filterForm');

    window.closeModal = () => { document.getElementById('emailModal').style.display = 'none'; };
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            main.classList.toggle('full-width');
        });
    }
    
    filterForm.addEventListener('change', () => {
        filterForm.submit();
    });

    // --- Chart.js Rendering ---
    let dailyChart, monthlyChart, yearlyChart;
    const chartOptions = { responsive: true, animation: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { let label = ' ยอดขาย: '; if (context.parsed.y !== null) { label += new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(context.parsed.y); } return label; } } } }, scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return new Intl.NumberFormat().format(value); } } } } };
    
    dailyChart = new Chart(document.getElementById('dailyChart'), { type: 'bar', data: { labels: <?= json_encode($labelsDay); ?>, datasets: [{ data: <?= json_encode($dailyValues) ?>, backgroundColor: 'rgba(52, 152, 219, 0.8)', borderRadius: 5 }] }, options: chartOptions });
    monthlyChart = new Chart(document.getElementById('monthlyChart'), { type: 'bar', data: { labels: <?= json_encode($labelsMonth); ?>, datasets: [{ data: <?= json_encode($monthlyValues) ?>, backgroundColor: 'rgba(26, 188, 156, 0.8)', borderRadius: 5 }] }, options: chartOptions });
    yearlyChart = new Chart(document.getElementById('yearlyChart'), { type: 'line', data: { labels: <?= json_encode($labelsYear); ?>, datasets: [{ data: <?= json_encode($yearlyValues) ?>, borderColor: 'rgba(155, 89, 182, 1)', backgroundColor: 'rgba(155, 89, 182, 0.2)', fill: true, tension: 0.3 }] }, options: chartOptions });

    // --- Export and Modal Logic ---
    const pdfButton = document.getElementById('pdfButton');
    const emailModalButton = document.getElementById('emailModalButton');
    const emailModal = document.getElementById('emailModal');
    
    async function handleExport(action, recipientEmail = '') {
        const button = (action === 'email') ? document.getElementById('sendEmailButton') : pdfButton;
        const initialText = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังสร้าง...';

        const formData = new FormData();
        formData.append('dailyChartImg', dailyChart.toBase64Image());
        formData.append('monthlyChartImg', monthlyChart.toBase64Image());
        formData.append('yearlyChartImg', yearlyChart.toBase64Image());
        formData.append('year', '<?= $selected_year ?>');
        formData.append('month', '<?= $selected_month ?>');

        if (action === 'download') {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'export_orders_graphs.php';
            form.target = '_blank';
            for (const [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            button.disabled = false;
            button.innerHTML = initialText;
        } else if (action === 'email') {
            formData.append('email', recipientEmail);
            const emailStatus = document.getElementById('emailStatus');
            emailStatus.innerHTML = '<span style="color: var(--warning);"><i class="fas fa-spinner fa-spin"></i> กำลังส่ง...</span>';

            try {
                const response = await fetch('send_orders_graphs_email.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.status === 'success') {
                    emailStatus.innerHTML = `<span style="color: var(--success);"><i class="fas fa-check-circle"></i> ${result.message}</span>`;
                    setTimeout(closeModal, 2500);
                } else {
                    emailStatus.innerHTML = `<span style="color: var(--danger);"><i class="fas fa-times-circle"></i> ผิดพลาด: ${result.message}</span>`;
                }
            } catch (error) {
                emailStatus.innerHTML = `<span style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> ผิดพลาดในการเชื่อมต่อ</span>`;
            } finally {
                button.disabled = false;
                button.innerHTML = initialText;
            }
        }
    }

    if (pdfButton) pdfButton.addEventListener('click', () => handleExport('download'));
    if (emailModalButton) emailModalButton.addEventListener('click', () => { emailModal.style.display = 'flex'; });

    const sendEmailButton = document.getElementById('sendEmailButton');
    if (sendEmailButton) {
        sendEmailButton.addEventListener('click', () => {
            const recipientEmail = document.getElementById('recipientEmail').value;
            if (recipientEmail && /\S+@\S+\.\S+/.test(recipientEmail)) {
                handleExport('email', recipientEmail);
            } else {
                document.getElementById('emailStatus').innerHTML = `<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>`;
            }
        });
    }
});
</script>
</body>
</html>