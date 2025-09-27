<?php
session_start();
require_once "db.php"; 
require_once __DIR__ . '/includes/auth.php';

// ฟังก์ชันแปลงเลขเดือนเป็นชื่อเดือนภาษาไทย
function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[(int)$month] ?? $month;
}

// ===================== Filter & Year Setup =====================
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// --- กำหนดปี Dropdown (สร้างช่วงปีที่ยืดหยุ่น) ---
$current_year = (int)date('Y');
$start_year = 2023; $end_year = 2035;
$display_years = range($current_year - 5, $current_year + 5); 

// ===================== 1. ดึงข้อมูลรายปี (Yearly Total) =====================
$yearlyDataArray = [];
$sqlYearly = "SELECT YEAR(pay_month) AS y, SUM(amount) AS total
             FROM employee_payments
             GROUP BY YEAR(pay_month)
             ORDER BY y ASC";
$resYearly = $conn->query($sqlYearly);
if($resYearly) {
    while($r = $resYearly->fetch_assoc()){
        $yearlyDataArray[$r['y'] + 543] = (float)$r['total']; // Convert to Buddhist year
    }
}
$labelsYear = array_keys($yearlyDataArray);
$yearlyTotal = array_values($yearlyDataArray);

// ===================== 2. ดึงข้อมูลรายเดือน (Monthly Total for Selected Year) =====================
$monthlyData = array_fill(1, 12, 0); 
$labelsMonth = [];
for($m=1;$m<=12;$m++){ $labelsMonth[] = thai_month($m); }

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
$monthlyTotal = array_values($monthlyData);

// ===================== Chart Datasets =====================
$datasetsMonth=[['label'=>'เงินเดือนรวม (บาท)','data'=>$monthlyTotal,'backgroundColor'=>'rgba(52, 152, 219, 0.8)','borderRadius'=>5]];
$datasetsYear=[['label'=>'เงินเดือนรวม (บาท)','data'=>$yearlyTotal,'borderColor'=>'#1abc9c','backgroundColor'=>'rgba(26, 188, 156, 0.2)','fill'=>true,'tension'=>0.3]];
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📊 กราฟรายงานเงินเดือน</title>
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
        background-color: rgba(0,0,0,0.2); 
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
        <img src="../img/da.jfif" alt="โลโก้" class="logo">
        <h3>รายงานเงินเดือน</h3>
    </div>
    <a href="employee_dashboard.php"><i class="fas fa-users fa-fw"></i>&nbsp; <span>รายการเช็คชื่อ</span></a>
    <a href="employee_graphs.php" class="active"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>กราฟเงินเดือน</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-chart-line"></i>&nbsp; กราฟเงินเดือนรวมบริษัท</h1>
    </div>

        
    <div class="container">
        <div class="filter-controls">
            <form method="get" class="filter-group">
                 <p style="font-weight: 700; color: var(--secondary-color);">ตัวกรองกราฟ:</p>
                <label for="year-select">ปี (พ.ศ.):</label>
                <select name="year" id="year-select" onchange="this.form.submit()">
                    <?php 
                    foreach ($display_years as $y_christian): 
                        $y_buddhist = $y_christian + 543;
                        $is_current = ($y_christian == $current_year);
                        $is_filtered = ($y_christian == (int)$selected_year);
                    ?>
                    <option value="<?= $y_christian ?>" <?= ($is_filtered) ? 'selected' : '' ?>>
                        <?= $y_buddhist ?>
                        <?= ($is_current) ? ' (ปัจจุบัน)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <div style="margin-left:auto;">
                 <button type="button" id="pdfButton" class="action-button btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="emailModalButton" class="action-button btn-email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
            </div>
            </form>
        </div>

        <h2>สรุปยอดเงินเดือนรายเดือน (ปี พ.ศ. <?= $selected_year + 543 ?>)</h2>
        <canvas id="stockChartMonth" height="120"></canvas>
    </div>

    <div class="container">
        <h2>สรุปยอดเงินเดือนรวมรายปี</h2>
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
    
    // ประกาศฟังก์ชัน closeModal (Global)
    window.closeModal = () => { document.getElementById('emailModal').style.display = 'none'; }; 
    
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
                    callback: (value) => new Intl.NumberFormat().format(value)
                }
            }
        }
    };
    
    // Monthly Chart
    const monthlyCtx = document.getElementById('stockChartMonth');
    if (monthlyCtx) { monthlyChart = new Chart(monthlyCtx, { type: 'bar', data: { labels: <?= json_encode($labelsMonth); ?>, datasets: <?= json_encode($datasetsMonth); ?> }, options: chartOptions }); }
    
    // Yearly Chart
    const yearlyCtx = document.getElementById('stockChartYear');
    if (yearlyCtx) { yearlyChart = new Chart(yearlyCtx, { type: 'line', data: { labels: <?= json_encode(array_keys($yearlyDataArray)); ?>, datasets: <?= json_encode($datasetsYear); ?> }, options: chartOptions }); }

    // --- Export and Modal Logic ---
    const pdfButton = document.getElementById('pdfButton');
    const emailModalButton = document.getElementById('emailModalButton');
    const emailModal = document.getElementById('emailModal');
    const sendEmailButton = document.getElementById('sendEmailButton');
    const recipientEmailInput = document.getElementById('recipientEmail');
    const emailStatus = document.getElementById('emailStatus');
    
    // ฟังก์ชัน Global สำหรับเปิด Modal
    window.openModal = () => { emailModal.style.display = 'flex'; recipientEmailInput.value = ''; emailStatus.innerHTML = ''; sendEmailButton.disabled = false; };
    
    // PDF Button (Download)
    if(pdfButton) {
        pdfButton.addEventListener('click', async () => {
            if (!monthlyChart || !yearlyChart) return alert('ไม่สามารถสร้างกราฟได้ครบทุกประเภท');
            
            pdfButton.disabled = true;
            pdfButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังสร้าง...';

            const formData = new FormData();
            // ส่งเฉพาะข้อมูลกราฟที่ใช้
            formData.append('monthlyChartImg', monthlyChart.toBase64Image());
            formData.append('yearlyChartImg', yearlyChart.toBase64Image());
            formData.append('year', <?= $selected_year ?>);

            try {
                // (ใช้ export_employee_graphs.php ที่สร้างให้)
                const response = await fetch('export_employee_graphs.php', { method: 'POST', body: formData });
                if (!response.ok) throw new Error(`Server error: ${response.statusText}`);
                
                const blob = await response.blob();
                if (blob.type !== 'application/pdf') throw new Error('Invalid file type received from server.');

                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = `salary_graphs_report_<?= $selected_year ?>.pdf`;
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

    // Email Button
    if(emailModalButton) emailModalButton.addEventListener('click', openModal);

    if(sendEmailButton) {
        sendEmailButton.addEventListener('click', async function() {
            const email = document.getElementById('recipientEmail').value.trim();
            if (!email || !/\S+@\S+\.\S+/.test(email)) {
                emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>';
                return;
            }
            if (!monthlyChart || !yearlyChart) return alert('ไม่สามารถสร้างกราฟได้ครบทุกประเภท');

            this.disabled = true;
            emailStatus.innerHTML = '<span style="color: var(--warning);"><i class="fas fa-spinner fa-spin"></i> กำลังสร้างไฟล์และส่ง...</span>';

            const formData = new FormData();
            // ส่งเฉพาะข้อมูลกราฟที่ใช้
            formData.append('monthlyChartImg', monthlyChart.toBase64Image());
            formData.append('yearlyChartImg', yearlyChart.toBase64Image());
            formData.append('year', <?= $selected_year ?>);
            formData.append('email', email);

            try {
                // (ใช้ send_employee_graphs_email.php ที่สร้างให้)
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
            } finally {
                sendEmailButton.disabled = false;
            }
        });
    }
    // ปิด Modal เมื่อคลิกที่พื้นที่ด้านนอก Modal
    emailModal.addEventListener('click', (event) => { if (event.target === emailModal) closeModal(); });
});
</script>
</body>
</html>
