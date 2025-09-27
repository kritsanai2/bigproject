<?php
session_start();
require_once "db.php"; 
require_once __DIR__ . '/includes/auth.php';

// ฟังก์ชันแปลงประเภทและเดือนเป็นไทย
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}

// --- รับค่าตัวกรอง ---
$type_filter = $_GET['type'] ?? '';
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0; 

// *** กำหนดค่าเริ่มต้นเป็นปีปัจจุบัน (ค.ศ.) ***
$current_year = intval(date('Y'));
$filter_year  = isset($_GET['year']) ? intval($_GET['year']) : $current_year;


// --- สร้าง SQL Query (ใช้โครงสร้างตารางเดิม) ---
$sql = "SELECT t.*, od.product_id, p.product_name
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id";

$params = [];
$types = "";
$where_clauses = [];

if ($type_filter) {
    $where_clauses[] = "t.transaction_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}
if ($filter_month > 0) {
    $where_clauses[] = "MONTH(t.transaction_date) = ?";
    $params[] = $filter_month;
    $types .= "i";
}
if ($filter_year > 0) {
    $where_clauses[] = "YEAR(t.transaction_date) = ?";
    $params[] = $filter_year;
    $types .= "i";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("Database query error: " . $stmt->error);
}

$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

// คำนวณยอดรวมสำหรับ การ์ดสรุปผล
$total_income = 0;
$total_expense = 0;
foreach($rows as $row){
    if($row['transaction_type'] === 'income'){
        $total_income += $row['amount'];
    } else {
        $total_expense += $row['amount'];
    }
}
$balance = $total_income - $total_expense;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>รายงานรายรับ-รายจ่าย</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    :root { --primary-color: #3498db; --secondary-color: #2c3e50; --light-teal-bg: #f0f8ff; --navy-blue: #001f3f; --white: #ffffff; --light-gray: #f8f9fa; --gray-border: #ced4da; --text-color: #495057; --success: #2ecc71; --danger: #e74c3c; --warning: #f39c12; --info: #9b59b6; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', sans-serif; background-color: var(--light-teal-bg); display: flex; }
    .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary-color), #2980b9); color: white; padding: 1.5rem; height: 100vh; position: fixed; top: 0; left: 0; transition: transform 0.3s; box-shadow: 2px 0 15px rgba(0,0,0,0.1); display: flex; flex-direction: column; z-index: 1000; }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .logo { width: 90px; height: 90px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.3); object-fit: cover; margin-bottom: 1rem; }
    .sidebar a { color: white; text-decoration: none; font-size: 1.1rem; padding: 0.8rem 1rem; border-radius: 8px; width: 100%; transition: all 0.2s; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
    .sidebar a:hover { background-color: rgba(255,255,255,0.15); transform: translateX(5px); }
    .sidebar a.active { background-color: rgba(0,0,0,0.2); }
    .toggle-btn { position: fixed; top: 1rem; right: 1rem; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; cursor: pointer; display: flex; justify-content: center; align-items: center; }
    .main { margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s; width: calc(100% - 250px); }
    .main.full-width { margin-left: 0; width: 100%; }
    .header-main { border-bottom: 2px solid var(--primary-color); padding-bottom: 1.5rem; margin-bottom: 2rem; }
    .header-main h1 { font-size: 2.5rem; color: var(--navy-blue); display: flex; align-items: center; gap: 1rem; }
    .container { background-color: var(--white); padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
    .filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; }
    .filter-form label { font-weight: 500; }
    .filter-form select { padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; }
    .action-button { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
    .action-button:hover { transform: translateY(-2px); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
    .btn-pdf { background-color: var(--danger); } .btn-excel { background-color: var(--success); } .btn-email { background-color: var(--warning); color: #333; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background-color: var(--navy-blue); color: var(--white); padding: 15px; text-align: left; }
    tbody td { padding: 15px; border-bottom: 1px solid #e0e0e0; }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }
    td.income { color: var(--success); font-weight: bold; }
    td.expense { color: var(--danger); font-weight: bold; }
    td.amount { text-align: right; }
    td.center { text-align: center; }
    .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .summary-card { background-color: var(--light-gray); border-radius: 10px; padding: 1.5rem; display: flex; align-items: center; gap: 1rem; border-left: 5px solid; }
    .summary-card .icon { font-size: 2.5rem; }
    .summary-card .info h4 { margin: 0; font-size: 1rem; color: var(--secondary-color); }
    .summary-card .info p { margin: 0; font-size: 1.8rem; font-weight: 700; }
    .card-income { border-color: var(--success); }
    .card-income .icon, .card-income .info p { color: var(--success); }
    .card-expense { border-color: var(--danger); }
    .card-expense .icon, .card-expense .info p { color: var(--danger); }
    .card-balance { border-color: var(--primary-color); }
    .card-balance .icon { color: var(--primary-color); }
    .card-balance .info p { color: <?= $balance >= 0 ? 'var(--success)' : 'var(--danger)' ?>; }
    
    /* === CSS สำหรับ Modal (ปรับปรุงใหม่) === */
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
    .modal-body input[type="email"] { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); margin-top: 0.5rem; font-size: 1rem; }
    .modal-footer { margin-top: 1.5rem; display: flex; justify-content: flex-end; }
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h2>รายรับ-รายจ่าย</h2>
    </div>
    <a href="dashboard.php"><i class="fas fa-home fa-fw"></i>&nbsp; <span>กลับ</span></a>
    <a href="transactions_dashboard.php" class="<?= empty($type_filter) ? 'active' : '' ?>"><i class="fas fa-list fa-fw"></i>&nbsp; <span>รายการทั้งหมด</span></a>
    <a href="transactions_dashboard.php?type=income" class="<?= ($type_filter=='income') ? 'active' : '' ?>"><i class="fas fa-arrow-down fa-fw"></i>&nbsp; <span>รายรับ</span></a>
    <a href="transactions_dashboard.php?type=expense" class="<?= ($type_filter=='expense') ? 'active' : '' ?>"><i class="fas fa-arrow-up fa-fw"></i>&nbsp; <span>รายจ่าย</span></a>
    <a href="transactions_graphs.php"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-receipt"></i>&nbsp; รายงานรายรับ-รายจ่าย</h1>
    </div>

    <div class="container">
        <form method="get" class="filter-form">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type_filter) ?>">
            <label for="month-select">เดือน:</label>
            <select name="month" id="month-select" onchange="this.form.submit()">
                <option value="0" <?= ($filter_month == 0) ? 'selected' : '' ?>>ทั้งปี</option>
                <?php for($m=1; $m<=12; $m++): ?>
                <option value="<?= $m ?>" <?= ($m == $filter_month) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                <?php endfor; ?>
            </select>
            
            <label for="year-select">ปี (พ.ศ.):</label>
            <select name="year" id="year-select" onchange="this.form.submit()">
                    <option value="<?= $current_year ?>" <?= (!isset($_GET['year']) || $filter_year == $current_year) ? 'selected' : '' ?>>
                    ปีปัจจุบัน (<?= $current_year + 543 ?>)
                </option>
                <?php 
                // วนลูปปีอื่นๆ ตั้งแต่ 2022 (พ.ศ. 2565) ถึง 2135 (พ.ศ. 2678)
                for($y = 2022; $y <= 2135; $y++): 
                    // ข้ามปีปัจจุบันหากเราใส่ไว้แล้วด้านบน
                    if ($y == $current_year) continue; 
                ?>
                <option value="<?= $y ?>" <?= ($y == $filter_year) ? 'selected' : '' ?>>
                    <?= $y + 543 ?>
                </option>
                <?php endfor; ?>
            </select>

            <div style="margin-left:auto;">
                <button type="button" id="pdfButton" class="action-button btn-pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="excelButton" class="action-button btn-excel"><i class="fas fa-file-excel"></i> Excel</button>
                <button type="button" id="emailModalButton" class="action-button btn-email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
            </div>
        </form>

        <?php if (empty($type_filter)): ?>
        <div class="summary-cards">
            <div class="summary-card card-income">
                <div class="icon"><i class="fas fa-arrow-circle-down"></i></div>
                <div class="info">
                    <h4>ยอดรวมรายรับ</h4>
                    <p><?= number_format($total_income, 2) ?></p>
                </div>
            </div>
            <div class="summary-card card-expense">
                <div class="icon"><i class="fas fa-arrow-circle-up"></i></div>
                <div class="info">
                    <h4>ยอดรวมรายจ่าย</h4>
                    <p><?= number_format($total_expense, 2) ?></p>
                </div>
            </div>
            <div class="summary-card card-balance">
                <div class="icon"><i class="fas fa-wallet"></i></div>
                <div class="info">
                    <h4>ยอดคงเหลือ</h4>
                    <p><?= number_format($balance, 2) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th> <th>รหัส</th> <th>วันที่</th> <th>ประเภท</th>
                        <th>จำนวนเงิน (บาท)</th> <th>รายละเอียด</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)):
                    $i = 1;
                    foreach($rows as $row):
                        // แสดงรายละเอียดการทำรายการ: รายจ่ายแสดง expense_type, รายรับแสดง product_name หรือ 'รายรับจากออเดอร์'
                        $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : (!empty($row['product_name']) ? "ขาย: " . $row['product_name'] : 'รายรับจากออเดอร์');
                ?>
                    <tr>
                        <td class="center"><?= $i++ ?></td>
                        <td class="center"><?= $row['transaction_id'] ?></td>
                        <td class="center"><?= date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543) ?></td>
                        <td class="center <?= $row['transaction_type'] ?>"><?= $row['transaction_type']=='income' ? '<i class="fas fa-plus-circle"></i> ' : '<i class="fas fa-minus-circle"></i> ' ?><?= thai_type($row['transaction_type']) ?></td>
                        <td class="amount <?= $row['transaction_type'] ?>"><?= number_format($row['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($desc) ?></td>
                    </tr>
                <?php 
                    endforeach;
                else:
                    echo '<tr><td colspan="6" style="text-align:center; padding: 2rem;">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>';
                endif;
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="emailModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-paper-plane"></i> ส่งรายงานทางอีเมล</h3>
            <button class="close-button" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <label>เลือกรูปแบบไฟล์:</label>
            <div style="margin-bottom: 1.5rem; display: flex; gap: 2rem;">
                <label><input type="checkbox" name="file_format" value="pdf" checked> PDF</label>
                <label><input type="checkbox" name="file_format" value="excel"> Excel (.csv)</label>
            </div>
            <label for="recipientEmail">อีเมลผู้รับ:</label>
            <input type="email" id="recipientEmail" placeholder="example@email.com" required>
            <div id="emailStatus" style="margin-top: 1rem; text-align: center;"></div>
        </div>
        <div class="modal-footer">
            <button id="sendEmailButton" class="action-button btn-email">ส่ง</button>
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

    // --- Export and Modal Logic ---
    const pdfButton = document.getElementById('pdfButton');
    const excelButton = document.getElementById('excelButton');
    const emailModalButton = document.getElementById('emailModalButton');
    const emailModal = document.getElementById('emailModal');
    const sendEmailButton = document.getElementById('sendEmailButton');
    const recipientEmailInput = document.getElementById('recipientEmail');
    const emailStatus = document.getElementById('emailStatus');
    
    function buildExportUrl(baseUrl) {
        const params = new URLSearchParams(window.location.search);
        return `${baseUrl}?${params.toString()}`;
    }

    if (pdfButton) pdfButton.addEventListener('click', () => {
        window.open(buildExportUrl('export_transactions_pdf.php'), '_blank');
    });

    if (excelButton) excelButton.addEventListener('click', () => {
        window.location.href = buildExportUrl('export_transactions_excel.php');
    });

    // ฟังก์ชัน Modal (ถูกประกาศใน window scope เพื่อให้เรียกใช้ได้จาก onclick ใน HTML)
    window.openModal = () => {
        emailModal.style.display = 'flex';
        recipientEmailInput.value = '';
        emailStatus.innerHTML = '';
        sendEmailButton.disabled = false;
        const pdfCheckbox = document.querySelector('#emailModal input[name="file_format"][value="pdf"]');
        const excelCheckbox = document.querySelector('#emailModal input[name="file_format"][value="excel"]');
        if (pdfCheckbox) pdfCheckbox.checked = true;
        if (excelCheckbox) excelCheckbox.checked = false;
    };

    window.closeModal = () => {
        emailModal.style.display = 'none';
    };

    if (emailModalButton) emailModalButton.addEventListener('click', openModal);

    emailModal.addEventListener('click', (e) => {
        if (e.target === emailModal) {
            closeModal();
        }
    });

    if(sendEmailButton) {
        sendEmailButton.addEventListener('click', async function() {
            const email = recipientEmailInput.value.trim();
            if (!email || !/\S+@\S+\.\S+/.test(email)) {
                emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>';
                return;
            }

            const selectedFormats = Array.from(document.querySelectorAll('#emailModal input[name="file_format"]:checked'))
                .map(cb => cb.value);

            if (selectedFormats.length === 0) {
                emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์</span>';
                return;
            }
            
            this.disabled = true;
            emailStatus.innerHTML = '<span style="color: var(--warning);">กำลังส่ง...</span>';
            
            const formData = new FormData();
            formData.append('email', email);
            
            selectedFormats.forEach(format => {
                formData.append('file_formats[]', format);
            });
            
            const params = new URLSearchParams(window.location.search);
            for (const [key, value] of params) {
                formData.append(key, value);
            }

            try {
                const response = await fetch('send_transactions_email.php', {
                    method: 'POST',
                    body: formData
                });
                
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    const result = await response.json(); 
    
                    if (result.status === 'success') {
                        emailStatus.innerHTML = `<span style="color: var(--success);">${result.message}</span>`;
                        setTimeout(closeModal, 2000); 
                    } else {
                        emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาด: ${result.message || 'เกิดข้อผิดพลาด'}</span>`;
                        this.disabled = false;
                    }
                } else {
                    emailStatus.innerHTML = '<span style="color: var(--danger);">เกิดข้อผิดพลาดจากเซิร์ฟเวอร์</span>';
                    this.disabled = false;
                    console.error('Server returned non-JSON data.');
                }
            } catch (error) {
                console.error('Error sending email:', error);
                emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาด: ${error}</span>`;
                this.disabled = false;
            }
        });
    }
});
</script>

</body>
</html>