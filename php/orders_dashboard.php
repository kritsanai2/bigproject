<?php
session_start();
// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once "db.php";
require_once __DIR__ . '/includes/auth.php';

// 2. ฟังก์ชันแปลงเลขเดือนเป็นชื่อเดือนภาษาไทย
function thai_month($month){
    $months = [
        1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
        5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
        9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
    ];
    return $months[intval($month)] ?? '';
}

// --- รับค่าตัวกรอง ---
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0; 

// *** แก้ไข 1: กำหนดค่าเริ่มต้นเป็นปีปัจจุบัน (ค.ศ.) ***
$current_year = intval(date('Y'));
$filter_year  = isset($_GET['year']) && intval($_GET['year']) > 0 ? intval($_GET['year']) : $current_year;


// --- สร้าง SQL Query --- (ใช้ Prepared Statements)
$sql_detailed_sales = "
    SELECT 
        o.order_date, c.full_name AS customer_name, p.product_name, p.unit,
        od.quantity, od.price, (od.quantity * od.price) AS item_total
    FROM order_details od
    JOIN orders o ON od.order_id = o.order_id
    JOIN customers c ON o.customer_id = c.customer_id
    JOIN products p ON od.product_id = p.product_id
";

$params = [];
$types = "";
$where_clauses = [];

if ($filter_month > 0) {
    $where_clauses[] = "MONTH(o.order_date) = ?";
    $params[] = $filter_month;
    $types .= "i";
}
// *** แก้ไข 1.1: หากปีเป็น 0 เราจะไม่กรองปีใด ๆ
if ($filter_year > 0) {
    $where_clauses[] = "YEAR(o.order_date) = ?";
    $params[] = $filter_year;
    $types .= "i";
}

if (!empty($where_clauses)) {
    $sql_detailed_sales .= " WHERE " . implode(" AND ", $where_clauses);
}

// เรียงข้อมูลใหม่ล่าสุดขึ้นก่อนเสมอ
$sql_detailed_sales .= " ORDER BY o.order_date DESC, o.order_id DESC";

$stmt = $conn->prepare($sql_detailed_sales);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result_sales = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>รายงานการขายรายสินค้า</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
<style>
    :root { 
        --primary-color: #3498db; --secondary-color: #2c3e50; 
        --light-teal-bg: #eaf6f6; --navy-blue: #001f3f;
        --white: #ffffff; --light-gray: #f8f9fa;
        --gray-border: #ced4da; --text-color: #495057;
        --success: #2ecc71; --danger: #e74c3c;
        --warning: #f39c12; --info: #9b59b6;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', sans-serif; background-color: var(--light-teal-bg); display: flex; }
    .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary-color), #2980b9); color: white; padding: 1.5rem; height: 100vh; position: fixed; top: 0; left: 0; transition: transform 0.3s; box-shadow: 2px 0 15px rgba(0,0,0,0.1); display: flex; flex-direction: column; z-index: 1000; }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .logo { width: 90px; height: 90px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.3); object-fit: cover; margin-bottom: 1rem; }
    .sidebar-header h2 { font-size: 1.5rem; }
    .sidebar a { color: white; text-decoration: none; font-size: 1.1rem; padding: 0.8rem 1rem; border-radius: 8px; width: 100%; transition: all 0.2s; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
    .sidebar a:hover { background-color: rgba(255,255,255,0.15); transform: translateX(5px); }
    .sidebar a.active { background-color: rgba(0,0,0,0.2); }
    .toggle-btn { position: fixed; top: 1rem; right: 1rem; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex; justify-content: center; align-items: center; }
    .main { margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s; width: calc(100% - 250px); }
    .main.full-width { margin-left: 0; width:100%; }
    .header-main { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--primary-color); padding-bottom: 1.5rem; margin-bottom: 2rem; }
    .header-main h1 { font-size: 2.5rem; color: var(--navy-blue); display: flex; align-items: center; gap: 1rem; }
    .container { background: var(--white); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 1.5rem; margin-bottom: 2rem; }
    .filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; }
    .filter-form label { font-weight: 500; }
    .filter-form select { padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; }
    .action-button { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; }
    .action-button.pdf { background-color: var(--danger); }
    .action-button.excel { background-color: var(--success); }
    .action-button.email { background-color: var(--warning); color:#333; }
    .action-button:hover { transform: translateY(-2px); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
    table { width: 100%; border-collapse: collapse; }
    thead th { background-color: var(--navy-blue); color: var(--white); padding: 15px; text-align: left; }
    tbody td { padding: 15px; border-bottom: 1px solid #e0e0e0; }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }
    th:first-child, td:first-child { text-align: center; }
    th:nth-child(5), td:nth-child(5), th:nth-child(6), td:nth-child(6), th:nth-child(7), td:nth-child(7) { text-align: right; }
    td:nth-child(7) { color: var(--success); font-weight: bold; }
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,31,63,0.6); backdrop-filter: blur(5px); z-index: 2000; justify-content: center; align-items: center; }
    .modal-content { background-color: var(--white); padding: 30px 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 500px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    .modal-header h3 { margin: 0; color: var(--navy-blue); font-size: 1.8rem; }
    .close-button { background: none; border: none; font-size: 2rem; cursor: pointer; color: #aaa; transition: all 0.2s; }
    .close-button:hover { color: var(--danger); transform: rotate(90deg); }
    .modal-footer { margin-top: 1.5rem; display: flex; justify-content: flex-end; }
    .modal-body input[type="email"] { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); margin-top: 0.5rem; font-size: 1rem; }
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h2>ข้อมูลการขาย</h2>
    </div>
    <a href="dashboard.php"><i class="fas fa-home fa-fw"></i>&nbsp; <span>หน้าหลัก</span></a>
    <a href="orders_dashboard.php" class="active"><i class="fas fa-receipt fa-fw"></i>&nbsp; <span>รายงานการขาย</span></a>
    <a href="orders_graphs.php"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>กราฟยอดขาย</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-file-invoice-dollar"></i>&nbsp; รายงานการขาย</h1>
    </div>

    <div class="container">
        <form method="get" class="filter-form">
            <label for="month-select">เดือน:</label>
            <select name="month" id="month-select" onchange="this.form.submit()">
                <option value="0" <?= ($filter_month == 0) ? 'selected' : '' ?>>ทั้งหมด</option>
                <?php for($m=1; $m<=12; $m++): ?>
                <option value="<?= $m ?>" <?= ($m == $filter_month) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                <?php endfor; ?>
            </select>
            
            <label for="filter-year">ปี (พ.ศ.):</label>
            <select name="year" id="filter-year" onchange="this.form.submit()"> 
        <option value="<?= $current_year ?>" <?= (intval($filter_year) == intval($current_year)) ? 'selected' : '' ?>>ปีปัจจุบัน (<?= $current_year + 543 ?>)</option>
    <?php 
    // วนลูปตั้งแต่ปี ค.ศ. 2022 (พ.ศ. 2565) ถึง ค.ศ. 2135 (พ.ศ. 2678)
    for($y = 2022; $y <= 2135; $y++): 
        if ($y == $current_year) continue; // ไม่แสดงปีปัจจุบันซ้ำ
    ?>
        <option value="<?= $y ?>" <?= (intval($y) == intval($filter_year)) ? 'selected' : '' ?>>
        <?= $y + 543 ?>
    </option>
    <?php endfor; ?>
        <option value="0" <?= (intval($filter_year) == 0) ? 'selected' : '' ?>>ทั้งหมด (ทุกปี)</option>
</select>
            
            <div style="margin-left:auto;">
                <button type="button" id="pdfButton" class="action-button pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" id="excelButton" class="action-button excel"><i class="fas fa-file-excel"></i> Excel</button>
                <button type="button" id="emailModalButton" class="action-button email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
            </div>
        </form>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>วันที่</th>
                        <th>ชื่อลูกค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th>จำนวน</th>
                        <th>ราคา/หน่วย</th>
                        <th>ราคารวม (บาท)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result_sales && $result_sales->num_rows > 0): ?>
                    <?php 
                    $rows = $result_sales->fetch_all(MYSQLI_ASSOC);
                    $i = 1;
                    $grand_total = 0; 
                    foreach($rows as $row):
                        $grand_total += $row['item_total'];
                    ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td style="text-align:center;"><?= date('d/m/', strtotime($row['order_date'])) . (date('Y', strtotime($row['order_date'])) + 543) ?></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td style="text-align:right;"><?= number_format($row['quantity']) . ' ' . htmlspecialchars($row['unit']) ?></td>
                        <td><?= number_format($row['price'], 2) ?></td>
                        <td><?= number_format($row['item_total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding: 2rem;">ไม่พบข้อมูลการขายตามเงื่อนไขที่เลือก</td></tr>
                <?php endif; ?>
                </tbody>
                            </table>
        </div>
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
        <div class="modal-footer">
            <button id="sendEmailButton" class="action-button email">ส่ง</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main');
    const toggleBtn = document.getElementById('toggle-btn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('full-width');
        });
    }

    const pdfButton = document.getElementById('pdfButton');
    const excelButton = document.getElementById('excelButton');
    const emailModalButton = document.getElementById('emailModalButton');
    const emailModal = document.getElementById('emailModal');
    const sendEmailButton = document.getElementById('sendEmailButton');
    const recipientEmailInput = document.getElementById('recipientEmail');
    const emailStatus = document.getElementById('emailStatus');
    const closeModalBtn = document.getElementById('closeModalBtn');
    
    function buildExportUrl(baseUrl) {
        const params = new URLSearchParams(window.location.search);
        return `${baseUrl}?${params.toString()}`;
    }

    if (pdfButton) {
        pdfButton.addEventListener('click', function() {
            // ควรตรวจสอบว่าไฟล์ export_sales_pdf.php มีอยู่จริง
            window.open(buildExportUrl('export_sales_pdf.php'), '_blank');
        });
    }

    if (excelButton) {
        excelButton.addEventListener('click', function() {
            // ควรตรวจสอบว่าไฟล์ export_sales_excel.php มีอยู่จริง
            window.location.href = buildExportUrl('export_sales_excel.php');
        });
    }

    function openModal() {
        if(emailModal) emailModal.style.display = 'flex';
        if(recipientEmailInput) recipientEmailInput.value = '';
        if(emailStatus) emailStatus.innerHTML = '';
        if(sendEmailButton) sendEmailButton.disabled = false;
        // *** แก้ไข: ปรับ Logic การตั้งค่าเริ่มต้นสำหรับ Checkbox ***
        const pdfCheckbox = emailModal.querySelector('input[name="file_format"][value="pdf"]');
        const excelCheckbox = emailModal.querySelector('input[name="file_format"][value="excel"]');
        if (pdfCheckbox) pdfCheckbox.checked = true;
        if (excelCheckbox) excelCheckbox.checked = false;
    };
    function closeModal() {
        if(emailModal) emailModal.style.display = 'none';
    };

    if (emailModalButton) emailModalButton.addEventListener('click', openModal);
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (emailModal) {
        emailModal.addEventListener('click', (event) => {
            if (event.target === emailModal) closeModal();
        });
    }

    if (sendEmailButton) {
        sendEmailButton.addEventListener('click', async function() {
            const email = recipientEmailInput.value.trim();
            if (!email || !/\S+@\S+\.\S+/.test(email)) {
                emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>';
                return;
            }
            
            // *** จุดแก้ไข 1: ดึงค่า Checkbox ทั้งหมดที่ถูกเลือก ***
            const selectedFormats = Array.from(emailModal.querySelectorAll('input[name="file_format"]:checked'))
                .map(cb => cb.value);

            if (selectedFormats.length === 0) {
                emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์</span>';
                return;
            }

            this.disabled = true;
            emailStatus.innerHTML = '<span style="color: var(--warning);">กำลังส่ง...</span>';

            const formData = new FormData();
            formData.append('email', email);
            
            // *** จุดแก้ไข 2: ส่งรูปแบบไฟล์ทั้งหมดเป็น Array (ใช้ file_formats[]) ***
            selectedFormats.forEach(format => {
                formData.append('file_formats[]', format);
            });
            
            const params = new URLSearchParams(window.location.search);
            for (const [key, value] of params) {
                formData.append(key, value);
            }

            try {
                // ควรตรวจสอบว่าไฟล์ send_sales_email.php มีอยู่จริง
                const response = await fetch('send_sales_email.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.status === 'success') {
                    emailStatus.innerHTML = `<span style="color: var(--success);">${result.message}</span>`;
                    setTimeout(closeModal, 2000);
                } else {
                    emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาด: ${result.message || 'เกิดข้อผิดพลาดไม่ทราบสาเหตุ'}</span>`;
                    this.disabled = false;
                }
            } catch (error) {
                emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาดในการเชื่อมต่อ</span>`;
                this.disabled = false;
            }
        });
    }
});
</script>

</body>
</html>
