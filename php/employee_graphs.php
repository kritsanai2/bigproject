<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "auth.php";
require_once "db.php";

// ฟังก์ชันช่วยแปลภาษาไทย
function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[(int)$month] ?? $month;
}

// --- รับค่าตัวกรอง ---
$current_year = (int)date('Y');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
$start_year = 2022; $end_year = 2035;

// --- 1. ดึงข้อมูลรายปี (Yearly Total) ---
$yearlyDataArray = [];
// *** แก้ไขจุดที่ 1: เปลี่ยน SUM(amount) เป็น SUM(salary) ***
$sqlYearly = "SELECT YEAR(pay_month) AS y, SUM(salary) AS total
              FROM employee_payments
              GROUP BY YEAR(pay_month)
              ORDER BY y ASC";
$resYearly = $conn->query($sqlYearly);
if($resYearly) {
    while($r = $resYearly->fetch_assoc()){
        $yearlyDataArray[$r['y'] + 543] = (float)$r['total'];
    }
}
$labelsYear = !empty($yearlyDataArray) ? array_keys($yearlyDataArray) : [];
$yearlyTotal = !empty($yearlyDataArray) ? array_values($yearlyDataArray) : [];

// --- 2. ดึงข้อมูลรายเดือน (Monthly Total for Selected Year) ---
$monthlyData = array_fill(0, 12, 0);
$labelsMonth = [];
for($m=1;$m<=12;$m++){ $labelsMonth[] = thai_month($m); }

if ($selected_year > 0) {
    // *** แก้ไขจุดที่ 2: เปลี่ยน SUM(amount) เป็น SUM(salary) ***
    $sqlMonthly = "SELECT MONTH(pay_month) AS m, SUM(salary) AS total
                   FROM employee_payments
                   WHERE YEAR(pay_month) = ?
                   GROUP BY MONTH(pay_month)";
    $stmtMonthly = $conn->prepare($sqlMonthly);
    $stmtMonthly->bind_param("i", $selected_year);
    $stmtMonthly->execute();
    $resMonthly = $stmtMonthly->get_result();
    while($r = $resMonthly->fetch_assoc()){
        $monthlyData[(int)$r['m'] - 1] = (float)$r['total'];
    }
}
$monthlyTotal = array_values($monthlyData);

// --- Chart Datasets ---
$datasetsMonth = [['label'=>'เงินเดือนรวม (บาท)','data'=>$monthlyTotal,'backgroundColor'=>'rgba(52, 152, 219, 0.8)','borderRadius'=>5]];
$datasetsYear = [['label'=>'เงินเดือนรวม (บาท)','data'=>$yearlyTotal,'borderColor'=>'#27ae60','backgroundColor'=>'rgba(46, 204, 113, 0.2)','fill'=>true,'tension'=>0.3]];
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>💧 กราฟรายงานเงินเดือน</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
    :root {
        --primary-color: #3498db; --secondary-color: #2c3e50;
        --light-bg: #f0f8ff; --navy-blue: #001f3f; --white: #ffffff;
        --light-gray: #f8f9fa; --gray-border: #ced4da; --text-color: #495057;
        --success: #2ecc71; --danger: #e74c3c; --warning: #f39c12;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', sans-serif; background-color: var(--light-bg); color: var(--text-color); display: flex; }
    .sidebar {
        width: 250px; background: linear-gradient(180deg, var(--primary-color), #2980b9); color: white;
        padding: 1.5rem; height: 100vh; position: fixed; top: 0; left: 0;
        transition: transform 0.3s ease-in-out; box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        display: flex; flex-direction: column; z-index: 1000;
    }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .logo { width: 90px; height: 90px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.3); object-fit: cover; margin-bottom: 1rem; }
    .sidebar a {
        color: white; text-decoration: none; font-size: 1.1rem; padding: 0.8rem 1rem;
        border-radius: 8px; width: 100%; transition: all 0.2s ease; margin-bottom: 0.5rem;
        display: flex; align-items: center; gap: 0.75rem;
    }
    .sidebar a:hover { background-color: rgba(255,255,255,0.15); transform: translateX(5px); }
    .sidebar a.active { background-color: rgba(0,0,0,0.2); font-weight: 500; }
    .toggle-btn {
        position: fixed; top: 1rem; right: 1rem; z-index: 1001; background-color: var(--primary-color);
        color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem;
        cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex;
        justify-content: center; align-items: center;
    }
    .main {
        margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s ease-in-out;
        width: calc(100% - 250px);
    }
    .main.full-width { margin-left: 0; width: 100%; }
    .header-main { border-bottom: 2px solid var(--primary-color); padding-bottom: 1.5rem; margin-bottom: 2rem; }
    .header-main h1 { font-size: 2.5rem; color: var(--navy-blue); display: flex; align-items: center; gap: 1rem; }
    .container { background-color: var(--white); padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
    .container h2 { font-size: 1.6rem; color: var(--secondary-color); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0; }
    .filter-controls { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1.5rem; }
    .filter-group { display: flex; align-items: center; gap: 1rem; }
    .filter-group label { font-weight: 500; }
    .filter-group select { padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; }
    .action-button {
        padding: 0.7rem 1.2rem; border: none; border-radius: 8px; font-weight: 600;
        cursor: pointer; color: white; text-decoration: none; display: inline-flex;
        align-items: center; gap: 0.5rem; transition: all 0.2s;
    }
    .action-button:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    .btn-filter { background-color: var(--primary-color); }
    .btn-pdf { background-color: var(--danger); }
    .btn-email { background-color: var(--warning); color: #333; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 2000; justify-content: center; align-items: center; }
    .modal-content { background-color: var(--white); padding: 30px 40px; border-radius: 15px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); width: 90%; max-width: 500px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    .modal-header h3 { color: var(--secondary-color); margin: 0; font-size: 1.8rem; }
    .close-button { background: none; border: none; font-size: 2rem; cursor: pointer; color: #aaa; transition: all 0.2s ease; }
    .close-button:hover { color: var(--danger); transform: rotate(90deg); }
    .modal-body p { color: var(--text-color); }
    .modal-body input[type="email"] { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); margin-top: 0.5rem; font-size: 1rem; }
    .modal-footer { margin-top: 1.5rem; display: flex; justify-content: flex-end; }
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../img/da.jfif" alt="โลโก้" class="logo">
       <h2>ระบบจัดการ</h2>
    </div>
    <a href="employee_dashboard.php"><i class="fas fa-users fa-fw"></i>&nbsp; <span>รายการเงินเดือน</span></a>
    <a href="employee_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>กราฟเงินเดือน</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-line"></i>&nbsp; กราฟเงินเดือนรวมบริษัท</h1>
    </div>

    <div class="container">
        <div class="filter-controls">
            <form method="get" class="filter-group">
                <label for="year-select"><strong>เลือกปีเพื่อดูกราฟรายเดือน:</strong></label>
                <select name="year" id="year-select">
                    <?php for($y = $start_year; $y <= $end_year; $y++): ?>
                    <option value="<?= $y ?>" <?= ($selected_year == $y) ? 'selected' : '' ?>>
                        <?= $y + 543 ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="action-button btn-filter"><i class="fas fa-sync-alt"></i> แสดงผล</button>
            </form>
            <div class="action-buttons">
               <button type="button" id="pdfButton" class="action-button btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
               <button type="button" id="emailModalButton" class="action-button btn-email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
           </div>
        </div>
    </div>

    <div class="container">
        <h2>สรุปยอดเงินเดือนรายเดือน (ปี พ.ศ. <?= $selected_year + 543 ?>)</h2>
        <canvas id="monthlyChart" height="120"></canvas>
    </div>

    <div class="container">
        <h2>สรุปยอดเงินเดือนรวมรายปี</h2>
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
            <p style="margin-bottom: 1rem;">รายงานกราฟทั้งหมดจะถูกส่งเป็นไฟล์ PDF</p>
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

    let monthlyChart, yearlyChart;
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
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: (value) => new Intl.NumberFormat('th-TH').format(value)
                }
            }
        }
    };

    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) { monthlyChart = new Chart(monthlyCtx, { type: 'bar', data: { labels: <?= json_encode($labelsMonth); ?>, datasets: <?= json_encode($datasetsMonth); ?> }, options: chartOptions }); }

    const yearlyCtx = document.getElementById('yearlyChart');
    if (yearlyCtx) { yearlyChart = new Chart(yearlyCtx, { type: 'line', data: { labels: <?= json_encode($labelsYear); ?>, datasets: <?= json_encode($datasetsYear); ?> }, options: chartOptions }); }

    const pdfButton = document.getElementById('pdfButton');
    const emailModalButton = document.getElementById('emailModalButton');
    const emailModal = document.getElementById('emailModal');

    const getChartFormData = () => {
        if (!monthlyChart || !yearlyChart) {
            alert('ไม่สามารถสร้างข้อมูลกราฟได้ครบถ้วน');
            return null;
        }
        const formData = new FormData();
        formData.append('monthlyChartImg', monthlyChart.toBase64Image());
        formData.append('yearlyChartImg', yearlyChart.toBase64Image());
        formData.append('year', '<?= $selected_year ?>');
        return formData;
    };

    if(pdfButton) {
        pdfButton.addEventListener('click', async () => {
            const formData = getChartFormData();
            if (!formData) return;

            pdfButton.disabled = true;
            pdfButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังสร้าง...';

            try {
                const response = await fetch('export_employee_graphs.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`Server error: ${response.statusText}`);

                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = `salary_graphs_report_<?= date('Y-m-d') ?>.pdf`;
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
        const closeModalBtn = emailModal.querySelector('#closeModalBtn');

        const openModal = () => {
            emailModal.style.display = 'flex';
            recipientEmailInput.value = '';
            emailStatus.innerHTML = '';
            sendEmailButton.disabled = false;
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
                emailStatus.innerHTML = `<span style="color: var(--warning);"><i class="fas fa-spinner fa-spin"></i> กำลังสร้างและส่ง...</span>`;
                formData.append('email', email);

                try {
                    const response = await fetch('send_employee_graphs_email.php', { method: 'POST', body: formData });
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