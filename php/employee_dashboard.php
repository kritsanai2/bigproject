<?php
session_start();
require_once "db.php"; 
require_once __DIR__ . '/includes/auth.php';

// กำหนดค่าแรงต่อวัน
$full_rate = 300;
$half_rate = 150;

$calculated_data = [];
$selected_month = $_GET['month'] ?? date('Y-m');

// --- คำนวณเงินเดือน ---
$stmt = $conn->prepare("
    SELECT 
        e.employee_id, e.full_name,
        SUM(CASE WHEN a.morning='present' AND a.afternoon='present' THEN 1 ELSE 0 END) AS full_days,
        SUM(CASE WHEN (a.morning='present' AND a.afternoon NOT IN ('present', 'late')) OR (a.morning NOT IN ('present', 'late') AND a.afternoon='present') THEN 1 ELSE 0 END) AS half_days,
        COUNT(DISTINCT CASE WHEN a.morning='late' OR a.afternoon='late' THEN a.attend_date END) AS late_days,
        SUM(CASE WHEN a.morning='leave' AND a.afternoon='leave' THEN 1 ELSE 0 END) AS leave_days,
        SUM(CASE WHEN a.morning='absent' AND a.afternoon='absent' THEN 1 ELSE 0 END) AS absent_days
    FROM employees e
    LEFT JOIN attendances a ON e.employee_id = a.employee_id AND DATE_FORMAT(a.attend_date, '%Y-%m') = ?
    WHERE e.status = 1
    GROUP BY e.employee_id
    ORDER BY e.employee_id
");
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$result = $stmt->get_result();

if($result){
    while($row = $result->fetch_assoc()){
        // การคำนวณ: ถือว่าวันสาย (late) ได้ค่าแรงเต็มวัน แต่ยังคงนับรวมเป็นวันทำงาน
        $work_days_paid = (float)$row['full_days'] + ((float)$row['half_days'] * 0.5) + (float)$row['late_days'];
        $amount = ((int)$row['full_days'] * $full_rate) + ((int)$row['half_days'] * $half_rate) + ((int)$row['late_days'] * $full_rate);
        
        $calculated_data[$row['employee_id']] = [
            'full_name' => $row['full_name'], 'full' => (int)$row['full_days'],
            'half' => (int)$row['half_days'], 'late' => (int)$row['late_days'],
            'leave' => (int)$row['leave_days'], 'absent' => (int)$row['absent_days'],
            'work_days' => $work_days_paid, 'amount' => $amount
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายงานเช็คชื่อและเงินเดือน</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    /* CSS ทั้งหมดเหมือนเดิม */
    :root { --primary-color: #3498db; --secondary-color: #2c3e50; --light-teal-bg: #f0f8ff; --navy-blue: #001f3f; --white: #ffffff; --light-gray: #f8f9fa; --gray-border: #ced4da; --text-color: #495057; --success: #2ecc71; --danger: #e74c3c; --warning: #f39c12; --info: #9b59b6; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', sans-serif; background-color: var(--light-teal-bg); display: flex; }
    .sidebar { width: 250px; background-color: var(--primary-color); color: white; padding: 2rem 1.5rem; height: 100vh; position: fixed; top: 0; left: 0; transition: transform 0.3s; box-shadow: 2px 0 10px rgba(0,0,0,0.1); display: flex; flex-direction: column; align-items: center; z-index: 1000; }
    .sidebar.hidden { transform: translateX(-100%); }
    .logo { width: 100px; height: 100px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.3); object-fit: cover; margin-bottom: 1.5rem; }
    .sidebar h2 { font-size: 1.5rem; margin-bottom: 2rem; }
    .sidebar a { color: white; text-decoration: none; font-size: 1.1rem; padding: 0.8rem 1.5rem; border-radius: 8px; width: 100%; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; transition: all 0.2s; }
    .sidebar a:hover { background-color: rgba(255,255,255,0.2); transform: translateX(5px); }
    .sidebar a.active { background-color: rgba(255,255,255,0.3); }
    .toggle-btn { position: fixed; top: 1rem; right: 1rem; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; cursor: pointer; display: flex; justify-content: center; align-items: center; }
    .main { margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s; width: calc(100% - 250px); }
    .main.full-width { margin-left: 0; width: 100%; }
    .header-main { border-bottom: 2px solid var(--primary-color); padding-bottom: 1.5rem; margin-bottom: 2rem; }
    .header-main h1 { font-size: 2.5rem; color: var(--navy-blue); display: flex; align-items: center; gap: 1rem; }
    .container { background-color: var(--white); padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
    .filter-container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; padding: 1.5rem; background-color: var(--light-gray); border-radius: 12px; }
    .filter-form { display: flex; gap: 1rem; align-items: center; }
    .filter-form label { font-weight: 500; }
    .filter-form input { padding: 10px; border: 1px solid var(--gray-border); border-radius: 8px; font-size: 1rem; }
    .action-button { padding: 10px 20px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; color: white; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .action-button:hover { transform: translateY(-2px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .btn-primary { background-color: var(--primary-color); } .btn-primary:hover { background-color: #2980b9; }
    .btn-pdf { background-color: var(--danger); } .btn-pdf:hover { background-color: #c0392b; }
    .btn-excel { background-color: var(--success); } .btn-excel:hover { background-color: #27ae60; }
    .btn-email { background-color: var(--warning); color: #333; } .btn-email:hover { background-color: #e67e22; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background-color: var(--navy-blue); color: var(--white); padding: 15px; text-align: center; }
    tbody td { padding: 15px; border-bottom: 1px solid #e0e0e0; text-align: center; }
    tbody td:nth-child(3) { text-align: left; }
    tbody td:last-child { font-weight: bold; color: var(--primary-color); }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }
    tfoot td { font-weight: bold; background-color: var(--light-gray); text-align: center; }
    tfoot td:first-child { text-align: right; }
    tfoot td:last-child { color: var(--danger); font-size: 1.2rem; }
    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0,31,63,0.6); backdrop-filter: blur(5px); z-index: 2000;
        justify-content: center; align-items: center;
    }
    .modal-content {
        background-color: var(--white); padding: 30px 40px; border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 500px;
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    .modal-header h3 { margin: 0; color: var(--navy-blue); font-size: 1.8rem; }
    .close-button { background: none; border: none; font-size: 2rem; cursor: pointer; color: #aaa; transition: all 0.2s ease; }
    .close-button:hover { color: var(--danger); transform: rotate(90deg); }
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
  <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
  <h2>ข้อมูลเงินเดือน</h2>
  <a href="dashboard.php"><i class="fas fa-home"></i>&nbsp; <span>กลับ</span></a>
  <a href="employee_dashboard.php" class="active"><i class="fas fa-users"></i>&nbsp; <span>รายงานเงินเดือน</span></a>
  <a href="employee_graphs.php"><i class="fas fa-chart-pie"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-file-invoice-dollar"></i>&nbsp; รายงานเงินเดือนพนักงาน</h1>
    </div>
    
    <div class="container">
        <div class="filter-container">
            <form method="GET" class="filter-form">
                <label>เลือกเดือน:</label>
                <input type="month" name="month" value="<?= $selected_month ?>">
                <button type="submit" class="action-button btn-primary"><i class="fas fa-search"></i> แสดงรายงาน</button>
            </form>
            <div class="export-buttons">
                <button type="button" id="pdfButton" class="action-button btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="excelButton" class="action-button btn-excel"><i class="fas fa-file-excel"></i> Excel</button>
                <button type="button" id="emailModalButton" class="action-button btn-email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
            </div>
            
        </div>

        <?php if(!empty($calculated_data)): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ลำดับ</th> <th>รหัส</th> <th>ชื่อ-สกุล</th> <th>เต็มวัน</th>
                            <th>ครึ่งวัน</th> <th>สาย</th> <th>ลา</th> <th>ขาด</th>
                            <th>รวมวันทำงาน</th> <th>เงินเดือน (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    $totals = array_fill_keys(['full','half','late','leave','absent','work_days','amount'], 0);
                    foreach($calculated_data as $id => $d):
                        foreach($totals as $key => &$value) { $value += $d[$key]; }
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($id) ?></td>
                            <td><?= htmlspecialchars($d['full_name']) ?></td>
                            <td><?= $d['full'] ?></td>
                            <td><?= $d['half'] ?></td>
                            <td><?= $d['late'] ?></td>
                            <td><?= $d['leave'] ?></td>
                            <td><?= $d['absent'] ?></td>
                            <td><?= number_format($d['work_days'],1) ?></td>
                            <td><?= number_format($d['amount'],2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right; padding-right: 1rem;">ยอดรวมทั้งหมด</td>
                            <td><?= number_format($totals['full']) ?></td>
                            <td><?= number_format($totals['half']) ?></td>
                            <td><?= number_format($totals['late']) ?></td>
                            <td><?= number_format($totals['leave']) ?></td>
                            <td><?= number_format($totals['absent']) ?></td>
                            <td><?= number_format($totals['work_days'], 1) ?></td>
                            <td><?= number_format($totals['amount'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
        <p style="text-align: center; color: #7f8c8d; padding: 2rem;">ไม่พบข้อมูลพนักงาน หรือยังไม่ได้เลือกเดือน</p>
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
            <div style="margin-bottom: 1.5rem; display: flex; gap: 2rem;">
                <label><input type="checkbox" name="file_format" value="pdf" checked> PDF</label>
                <label><input type="checkbox" name="file_format" value="excel"> Excel (.csv)</label>
            </div>
            <label for="recipientEmail">อีเมลผู้รับ:</label>
            <input type="email" id="recipientEmail" placeholder="example@email.com" required style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem;">
            <div id="emailStatus" style="margin-top: 1rem; text-align: center;"></div>
        </div>
        <div class="modal-footer" style="margin-top:1.5rem; display:flex; justify-content:flex-end;">
            <button id="sendEmailButton" class="action-button btn-email" style="color:white;">ส่ง</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- 1. การแจ้งเตือน SweetAlert2 ---
    <?php
    if(isset($_SESSION['alert'])){
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

    // --- 3. ส่วนควบคุมปุ่ม Export และ Modal ---
    const pdfButton = document.getElementById('pdfButton');
    const excelButton = document.getElementById('excelButton');
    const emailModalButton = document.getElementById('emailModalButton');
    const emailModal = document.getElementById('emailModal');
    
    // ฟังก์ชันสร้าง URL พร้อมแนบ Filter ปัจจุบัน
    function buildExportUrl(baseUrl) {
        const params = new URLSearchParams(window.location.search);
        return `${baseUrl}?${params.toString()}`;
    }

    // ปุ่มดาวน์โหลด PDF
    if(pdfButton) {
        pdfButton.addEventListener('click', () => {
            window.open(buildExportUrl('export_payroll_pdf.php'), '_blank');
        });
    }

    // ปุ่มดาวน์โหลด Excel
    if(excelButton) {
        excelButton.addEventListener('click', () => {
            window.location.href = buildExportUrl('export_payroll_excel.php');
        });
    }
    
    // --- 4. ส่วนควบคุม Modal การส่งอีเมล ---
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
            // ตั้งค่าเริ่มต้นให้เลือก PDF และเคลียร์ Excel
            const pdfCheckbox = emailModal.querySelector('input[name="file_format"][value="pdf"]');
            const excelCheckbox = emailModal.querySelector('input[name="file_format"][value="excel"]');
            if (pdfCheckbox) pdfCheckbox.checked = true;
            if (excelCheckbox) excelCheckbox.checked = false; 
        };

        const closeModal = () => {
            emailModal.style.display = 'none';
        };

        if(emailModalButton) emailModalButton.addEventListener('click', openModal);
        if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal); 
        emailModal.addEventListener('click', (event) => {
            if (event.target === emailModal) closeModal();
        });

        // ปุ่ม "ส่ง" ใน Modal
        if(sendEmailButton) {
            sendEmailButton.addEventListener('click', async function() {
                const email = recipientEmailInput.value.trim();
                if (!email || !/\S+@\S+\.\S+/.test(email)) {
                    emailStatus.innerHTML = '<span style="color: red;">กรุณากรอกอีเมลให้ถูกต้อง</span>';
                    return;
                }
                
                // *** ดึงค่า Checkbox ทั้งหมดที่ถูกเลือก ***
                const selectedFormats = Array.from(emailModal.querySelectorAll('input[name="file_format"]:checked'))
                    .map(cb => cb.value);

                if (selectedFormats.length === 0) {
                    emailStatus.innerHTML = '<span style="color: red;">กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์</span>';
                    return;
                }
                
                this.disabled = true;
                emailStatus.innerHTML = '<span style="color: blue;">กำลังสร้างไฟล์และส่ง...</span>';

                const formData = new FormData();
                formData.append('email', email);
                
                // *** ส่งรูปแบบไฟล์ทั้งหมดเป็น Array ***
                selectedFormats.forEach(format => {
                    formData.append('file_formats[]', format); 
                });
                
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
                        emailStatus.innerHTML = `<span style="color: green;">${result.message}</span>`;
                        setTimeout(closeModal, 2500);
                    } else {
                        emailStatus.innerHTML = `<span style="color: red;">ผิดพลาด: ${result.message}</span>`;
                        this.disabled = false;
                    }
                } catch (error) {
                    emailStatus.innerHTML = `<span style="color: red;">ผิดพลาดในการเชื่อมต่อ</span>`;
                    this.disabled = false;
                }
            });
        }
    }
});
</script>

</body>
</html>
