<?php
require_once "auth.php";
require_once "db.php";

// --- ค่าคงที่และตัวแปรเริ่มต้น ---
define('FULL_RATE', 300);
define('HALF_RATE', 150);

// --- รับค่า Filter จาก GET Parameter ---
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year_filter  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// --- ฟังก์ชันช่วยเหลือ ---
function thai_month($month_num) {
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    return $months[intval($month_num)] ?? '';
}

// --- เตรียม SQL Query แบบ dynamic filter ---
$sql = "
   SELECT 
    e.employee_id,
    e.full_name,
    p.full_days,
    p.half_days,
    p.late_days,
    p.leave_days,
    p.absent_days,
    p.work_days,
    p.salary
FROM employees e
LEFT JOIN employee_payments p
    ON e.employee_id = p.employee_id
    AND (? = 0 OR MONTH(p.pay_month) = ?)
    AND (? = 0 OR YEAR(p.pay_month) = ?)
WHERE e.status = 1
ORDER BY e.employee_id ASC

";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $month_filter, $month_filter, $year_filter, $year_filter);
$stmt->execute();
$result = $stmt->get_result();

// --- ประมวลผลข้อมูล ---
$calculated_data = [];
while ($row = $result->fetch_assoc()) {
    $work_days_paid = (float)$row['full_days'] + ((float)$row['half_days'] * 0.5) + ((float)$row['late_days'] * 0.5);
    $salary = ((int)$row['full_days'] * FULL_RATE) 
            + ((int)$row['half_days'] * HALF_RATE) 
            + ((int)$row['late_days'] * HALF_RATE);
    
    $calculated_data[$row['employee_id']] = [
        'full_name' => $row['full_name'],
        'full'      => (int)$row['full_days'],
        'half'      => (int)$row['half_days'],
        'late'      => (int)$row['late_days'],
        'leave'     => (int)$row['leave_days'],
        'absent'    => (int)$row['absent_days'],
        'work_days' => $work_days_paid,
        'salary'    => $salary
    ];
}

// --- สร้างหัวข้อรายงานแบบ Dynamic ---
$report_title = "รายงานเงินเดือน";
if ($month_filter > 0) {
    $report_title .= " เดือน " . thai_month($month_filter);
}
if ($year_filter > 0) {
    $report_title .= " ปี " . ($year_filter + 543);
} else {
    $report_title .= " ทั้งหมด";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายงานเงินเดือนพนักงาน</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
<style>
   /* ====================== Root Variables ====================== */
:root {
    --primary-color: #3498db;
    --secondary-color: #2c3e50;
    --light-teal-bg: #f0f8ff;
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

/* ====================== Global Reset ====================== */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Sarabun', sans-serif;
    background-color: var(--light-teal-bg);
    display: flex;
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

.sidebar a:hover { 
    background-color: rgba(255,255,255,0.15); 
    transform: translateX(5px); 
}

.sidebar a.active { background-color: rgba(0,0,0,0.2); }

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
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

/* ====================== Filters & Actions ====================== */
.filter-box {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.filter-form label,
.filter-form select {
    font-size: 1rem;
}

.filter-form select {
    padding: 10px;
    border: 1px solid var(--gray-border);
    border-radius: 8px;
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
}

.action-button {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    text-decoration: none;
}

.action-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.btn-filter { background-color: var(--primary-color); }
.btn-pdf { background-color: var(--danger); }
.btn-excel { background-color: var(--success); }
.btn-email { background-color: var(--warning); color: #333; }

/* ====================== Table ====================== */
table {
    width: 100%;
    border-collapse: collapse;
}

thead th {
    background-color: var(--navy-blue);
    color: var(--white);
    padding: 15px;
    text-align: center;
}

tbody td {
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
    text-align: center;
}

tbody td:nth-child(3) { text-align: left; }

tbody td:last-child {
    font-weight: bold;
    color: var(--primary-color);
}

tbody tr:nth-child(even) { background-color: var(--light-gray); }

tbody tr:hover { background-color: #d4eaf7; }

tfoot td {
    font-weight: bold;
    background-color: var(--light-gray);
    text-align: center;
}

tfoot td:first-child { text-align: right; padding-right: 1rem; }

tfoot td:last-child {
    color: var(--danger);
    font-size: 1.2rem;
}

/* ====================== Modal ====================== */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,31,63,0.6);
    backdrop-filter: blur(5px);
    z-index: 2000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: var(--white);
    padding: 30px 40px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
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
    margin: 0;
    color: var(--navy-blue);
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
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h2>ข้อมูลเงินเดือน</h2>
    </div>
    <a href="dashboard.php"><i class="fas fa-home fa-fw"></i>&nbsp; <span>กลับ</span></a>
    <a href="employee_dashboard.php" class="active"><i class="fas fa-users fa-fw"></i>&nbsp; <span>รายงานเงินเดือน</span></a>
    <a href="employee_graphs.php"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-file-invoice-dollar"></i>&nbsp; รายงานเงินเดือนพนักงาน</h1>
    </div>

    
<div class="container">
    <div class="filter-box">
        <form method="get" class="filter-form">
            <label for="month-select">เดือน:</label>
            <select name="month" id="month-select">
                <?php for ($m = 1; $m <= 12; $m++) : ?>
                    <option value="<?= $m ?>" <?= ($month_filter == $m) ? 'selected' : '' ?>>
                        <?= thai_month($m) ?>
                    </option>
                <?php endfor; ?>
            </select>

            <label for="year-select">ปี:</label>
            <select name="year" id="year-select">
                <?php for ($y = 2022; $y <= 2035; $y++) : ?>
                    <option value="<?= $y ?>" <?= ($year_filter == $y) ? 'selected' : '' ?>>
                        <?= $y + 543 ?>
                    </option>
                <?php endfor; ?>
            </select>

            <button type="submit" class="action-button btn-filter">
                <i class="fas fa-filter"></i> กรองข้อมูล
            </button>
        </form>
            <div class="action-buttons">
                <button type="button" id="pdfButton" class="action-button btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="excelButton" class="action-button btn-excel"><i class="fas fa-file-excel"></i> Excel</button>
                <button type="button" id="emailModalButton" class="action-button btn-email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
            </div>
        </div>
    </div>

    <div class="container">
        <h2 style="margin-bottom: 1.5rem; text-align:center; color: var(--secondary-color);"><?= htmlspecialchars($report_title) ?></h2>
        
        <?php if (!empty($calculated_data)) : ?>
            <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th><th>รหัส</th><th>ชื่อ-สกุล</th><th>เต็มวัน</th>
                        <th>ครึ่งวัน</th><th>สาย</th><th>ลา</th><th>ขาด</th>
                        <th>รวมวันทำงาน</th><th>เงินเดือน (บาท)</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $i = 1; 
                $totals = array_fill_keys(['full', 'half', 'late', 'leave', 'absent', 'work_days', 'salary'], 0);
                foreach ($calculated_data as $id => $d) : 
                    foreach ($totals as $key => &$value) { $value += $d[$key]; }
                ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($id) ?></td>
                        <td style="text-align:left;"><?= htmlspecialchars($d['full_name']) ?></td>
                        <td><?= $d['full'] ?></td>
                        <td><?= $d['half'] ?></td>
                        <td><?= $d['late'] ?></td>
                        <td><?= $d['leave'] ?></td>
                        <td><?= $d['absent'] ?></td>
                        <td><?= number_format($d['work_days'], 1) ?></td>
                        <td><?= number_format($d['salary'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">รวมทั้งหมด</td>
                        <td><?= number_format($totals['full']) ?></td>
                        <td><?= number_format($totals['half']) ?></td>
                        <td><?= number_format($totals['late']) ?></td>
                        <td><?= number_format($totals['leave']) ?></td>
                        <td><?= number_format($totals['absent']) ?></td>
                        <td><?= number_format($totals['work_days'], 1) ?></td>
                        <td><?= number_format($totals['salary'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        <?php else : ?>
            <p style="text-align:center; padding: 2rem; color: #7f8c8d; font-size: 1.2rem;">
                <i class="fas fa-info-circle"></i> 
                <?php echo (isset($_GET['month']) || isset($_GET['year'])) ? 'ไม่พบข้อมูลตามเงื่อนไขที่เลือก' : 'กรุณาเลือกเดือนและปีก่อน'; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="emailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-paper-plane"></i> ส่งรายงานทางอีเมล</h3>
            <button class="close-button" id="closeModalBtn">&times;</button>
        </div>
        <div class="modal-body">
            <label>เลือกรูปแบบไฟล์:</label>
            <div style="margin: 0.5rem 0 1.5rem; display: flex; gap: 2rem;">
                <label><input type="checkbox" name="file_format" value="pdf" checked> PDF</label>
                <label><input type="checkbox" name="file_format" value="excel"> Excel (.csv)</label>
            </div>
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
    // --- 1. การแจ้งเตือน SweetAlert2 ---
    <?php
    if (isset($_SESSION['alert'])) {
        echo "Swal.fire({
            toast: true,
            position: 'top-end',
            icon: '{$_SESSION['alert']['type']}',
            title: '{$_SESSION['alert']['message']}',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });";
        unset($_SESSION['alert']);
    }
    ?>

    // --- 2. ส่วนควบคุม Sidebar ---
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

    // --- 3. ส่วนควบคุมปุ่ม Export ---
    const pdfButton = document.getElementById('pdfButton');
    const excelButton = document.getElementById('excelButton');
    
    function buildExportUrl(baseUrl) {
        const params = new URLSearchParams(window.location.search);
        return `${baseUrl}?${params.toString()}`;
    }

    if (pdfButton) {
        pdfButton.addEventListener('click', () => window.open(buildExportUrl('export_payroll_pdf.php'), '_blank'));
    }
    if (excelButton) {
        excelButton.addEventListener('click', () => window.location.href = buildExportUrl('export_payroll_excel.php'));
    }
    
    // --- 4. ส่วนควบคุม Modal การส่งอีเมล ---
    const emailModal = document.getElementById('emailModal');
    const emailModalButton = document.getElementById('emailModalButton');

    if (emailModal && emailModalButton) {
        const sendEmailButton = document.getElementById('sendEmailButton');
        const recipientEmailInput = document.getElementById('recipientEmail');
        const emailStatus = document.getElementById('emailStatus');
        const closeModalBtn = document.getElementById('closeModalBtn');

        const openModal = () => {
            emailModal.style.display = 'flex';
            recipientEmailInput.value = '';
            emailStatus.innerHTML = '';
            sendEmailButton.disabled = false;
            emailModal.querySelector('input[value="pdf"]').checked = true;
            emailModal.querySelector('input[value="excel"]').checked = false;
        };

        const closeModal = () => { emailModal.style.display = 'none'; };

        emailModalButton.addEventListener('click', openModal);
        closeModalBtn.addEventListener('click', closeModal); 
        emailModal.addEventListener('click', (event) => {
            if (event.target === emailModal) closeModal();
        });

        if (sendEmailButton) {
            sendEmailButton.addEventListener('click', async function() {
                const email = recipientEmailInput.value.trim();
                if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                    emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>';
                    return;
                }
                
                const selectedFormats = Array.from(emailModal.querySelectorAll('input[name="file_format"]:checked')).map(cb => cb.value);
                if (selectedFormats.length === 0) {
                    emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์</span>';
                    return;
                }
                
                this.disabled = true;
                emailStatus.innerHTML = '<span style="color: var(--primary-color);">กำลังสร้างไฟล์และส่ง... <i class="fas fa-spinner fa-spin"></i></span>';

                const formData = new FormData();
                formData.append('email', email);
                selectedFormats.forEach(format => formData.append('file_formats[]', format));
                
                const params = new URLSearchParams(window.location.search);
                for (const [key, value] of params) {
                    formData.append(key, value);
                }

                try {
                    const response = await fetch('send_payroll_email.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (result.status === 'success') {
                        emailStatus.innerHTML = `<span style="color: var(--success);">${result.message}</span>`;
                        setTimeout(closeModal, 2500);
                    } else {
                        emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาด: ${result.message}</span>`;
                        this.disabled = false;
                    }
                } catch (error) {
                    emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาดในการเชื่อมต่อกับ Server</span>`;
                    this.disabled = false;
                }
            });
        }
    }
});
</script>

</body>
</html>