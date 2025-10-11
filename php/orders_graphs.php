<?php
require_once "auth.php";
require_once "db.php";

// ================== ฟังก์ชันและตัวกรอง ==================
function thai_month($m) {
    $months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
    return $months[(int)$m] ?? '';
}

$current_year = (int)date('Y');
$current_month = (int)date('m');

// --- ตัวกรองสำหรับกราฟรายวัน ---
$daily_filter_year = isset($_GET['daily_year']) ? (int)$_GET['daily_year'] : $current_year;
$daily_filter_month = isset($_GET['daily_month']) ? (int)$_GET['daily_month'] : $current_month;

// --- ตัวกรองสำหรับกราฟรายเดือน ---
$monthly_filter_year = isset($_GET['monthly_year']) ? (int)$_GET['monthly_year'] : $current_year;

// --- กำหนดปีสำหรับ Dropdown ---
$start_year = 2022; 
$end_year = 2035;

// ================== 1. กราฟรายวัน ==================
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $daily_filter_month, $daily_filter_year);
$dailyData = array_fill(1, $daysInMonth, 0);
$sqlDaily = "SELECT DAY(order_date) as d, SUM(total_amount) as total 
             FROM orders 
             WHERE YEAR(order_date) = ? AND MONTH(order_date) = ?
             GROUP BY DAY(order_date)";
$stmtDaily = $conn->prepare($sqlDaily);
$stmtDaily->bind_param("ii", $daily_filter_year, $daily_filter_month);
$stmtDaily->execute();
$resDaily = $stmtDaily->get_result();
while($row = $resDaily->fetch_assoc()){
    $dailyData[(int)$row['d']] = (float)$row['total'];
}
$labelsDay = range(1, $daysInMonth);
$dailyValues = array_values($dailyData);

// ================== 2. กราฟรายเดือน ==================
$monthlyData = array_fill(1, 12, 0);
$sqlMonthly = "SELECT MONTH(order_date) as m, SUM(total_amount) as total 
               FROM orders 
               WHERE YEAR(order_date) = ?
               GROUP BY MONTH(order_date)";
$stmtMonthly = $conn->prepare($sqlMonthly);
$stmtMonthly->bind_param("i", $monthly_filter_year);
$stmtMonthly->execute();
$resMonthly = $stmtMonthly->get_result();
while($row = $resMonthly->fetch_assoc()){
    $monthlyData[(int)$row['m']] = (float)$row['total'];
}
$labelsMonth = array_map(fn($m) => thai_month($m), array_keys($monthlyData));
$monthlyValues = array_values($monthlyData);

// ================== 3. กราฟรายปี ==================
$yearlyData = [];
$sqlYearly = "SELECT YEAR(order_date) as y, SUM(total_amount) as total 
              FROM orders 
              GROUP BY YEAR(order_date) ORDER BY y ASC";
$resYearly = $conn->query($sqlYearly);
while($row = $resYearly->fetch_assoc()){
    $yearlyData[$row['y']] = (float)$row['total'];
}
$labelsYear = array_map(fn($y) => $y + 543, array_keys($yearlyData));
$yearlyValues = array_values($yearlyData);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>💧 กราฟยอดขาย</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    /* ====================== Root Colors ====================== */
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

/* ====================== Global Reset ====================== */
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

/* ====================== Sidebar ====================== */
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
.sidebar.hidden { transform: translateX(-100%); }
.sidebar-header { text-align: center; margin-bottom: 2rem; }
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
.sidebar a:hover { background-color: rgba(255,255,255,0.15); transform: translateX(5px); }
.sidebar a.active { background-color: rgba(0,0,0,0.2); font-weight: 500; }

/* ====================== Toggle Button ====================== */
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

/* ====================== Main Content ====================== */
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

/* ====================== Header Main ====================== */
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

/* ====================== Containers ====================== */
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

/* ====================== Filters & Actions ====================== */
.filter-controls { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1.5rem; }
.filter-group { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
.filter-item { display: flex; align-items: center; gap: 0.5rem; }
.filter-item label { font-weight: 500; }
.filter-item select {
    padding: 0.6rem 1rem;
    border-radius: 8px;
    border: 1px solid var(--gray-border);
    font-size: 1rem;
}
.actions-group { display: flex; gap: 0.5rem; }
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
.btn-pdf { background-color: var(--danger); } 
.btn-email { background-color: var(--warning); color: #333; }

/* ====================== Modal ====================== */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
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
.close-button:hover { color: var(--danger); transform: rotate(90deg); }
.modal-body p { color: var(--text-color); }
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
    <a href="orders_dashboard.php"><i class="fas fa-receipt fa-fw"></i>&nbsp; <span>รายงานการขาย</span></a>
    <a href="orders_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>กราฟยอดขาย</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-line"></i>&nbsp; กราฟสรุปยอดขาย</h1>
    </div>

    <div class="container">
        <h2>ยอดขายรายวัน (เดือน <?= thai_month($daily_filter_month) ?> ปี พ.ศ. <?= $daily_filter_year + 543 ?>)</h2>
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
                <input type="hidden" name="monthly_year" value="<?= htmlspecialchars($monthly_filter_year) ?>">
                <button type="submit" class="action-button btn-filter"><i class="fas fa-sync-alt"></i> แสดงผล</button>
            </form>
            <div class="actions-group">
                <button type="button" id="pdfButton" class="action-button btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="emailModalButton" class="action-button btn-email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
            </div>
        </div>
        <canvas id="dailyChart" height="120" style="margin-top: 1.5rem;"></canvas>
    </div>

    <div class="container">
        <h2>ยอดขายรายเดือน (ปี พ.ศ. <?= $monthly_filter_year + 543 ?>)</h2>
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
                <input type="hidden" name="daily_year" value="<?= htmlspecialchars($daily_filter_year) ?>">
                <input type="hidden" name="daily_month" value="<?= htmlspecialchars($daily_filter_month) ?>">
                <button type="submit" class="action-button btn-filter"><i class="fas fa-sync-alt"></i> แสดงผล</button>
            </form>
        </div>
        <canvas id="monthlyChart" height="120" style="margin-top: 1.5rem;"></canvas>
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
            legend: { display: false }, 
            tooltip: { 
                callbacks: { 
                    label: function(context) { 
                        let label = ' ยอดขาย: '; 
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
    
    dailyChart = new Chart(document.getElementById('dailyChart'), { type: 'bar', data: { labels: <?= json_encode($labelsDay); ?>, datasets: [{ data: <?= json_encode($dailyValues) ?>, backgroundColor: 'rgba(52, 152, 219, 0.8)', borderRadius: 5 }] }, options: chartOptions });
    monthlyChart = new Chart(document.getElementById('monthlyChart'), { type: 'bar', data: { labels: <?= json_encode($labelsMonth); ?>, datasets: [{ data: <?= json_encode($monthlyValues) ?>, backgroundColor: 'rgba(26, 188, 156, 0.8)', borderRadius: 5 }] }, options: chartOptions });
    yearlyChart = new Chart(document.getElementById('yearlyChart'), { type: 'line', data: { labels: <?= json_encode($labelsYear); ?>, datasets: [{ data: <?= json_encode($yearlyValues) ?>, borderColor: 'rgba(155, 89, 182, 1)', backgroundColor: 'rgba(155, 89, 182, 0.2)', fill: true, tension: 0.3 }] }, options: chartOptions });

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
                const response = await fetch('export_orders_graphs.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`Server error: ${response.statusText}`);
                
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = `sales_graphs_report_${Date.now()}.pdf`;
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

        window.openModal = () => { 
            emailModal.style.display = 'flex'; 
            recipientEmailInput.value = ''; 
            emailStatus.innerHTML = ''; 
            sendEmailButton.disabled = false; 
        };
        
        window.closeModal = () => { emailModal.style.display = 'none'; };
        
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
                emailStatus.innerHTML = `<span style="color: var(--warning);"><i class="fas fa-spinner fa-spin"></i> กำลังสร้างและส่ง...</span>`;
                formData.append('email', email);

                try {
                    const response = await fetch('send_orders_graphs_email.php', { method: 'POST', body: formData });
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
                     this.innerHTML = '<i class="fas fa-paper-plane"></i> ส่ง';
                }
            });
        }
    }
});
</script>
</body>
</html>