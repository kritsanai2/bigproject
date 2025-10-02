<?php
require_once "auth.php";
require_once "db.php";

// ฟังก์ชันช่วยแปลภาษาไทย
function thai_month($month) {
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return $months[(int)$month] ?? '';
}

function thai_type($type) {
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก';
}

// รับค่าตัวกรองจาก URL (แก้ไขค่าเริ่มต้น)
$type_filter = $_GET['type'] ?? '';
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : 0; // Default is All Months
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0; // Default is All Years

// สร้าง Query String สำหรับลิงก์ใน Sidebar เพื่อให้จำฟิลเตอร์ได้
$link_params = [];
if ($month_filter > 0) $link_params['month'] = $month_filter;
if ($filter_year > 0) $link_params['year'] = $filter_year;
$link_query_string = http_build_query($link_params);


// สร้าง SQL Query
$sql = "SELECT s.stock_id, s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit
        FROM stock s
        JOIN products p ON s.product_id = p.product_id";

$where_clauses = [];
$params = [];
$types = '';

if ($type_filter) {
    $where_clauses[] = "s.stock_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}
if ($month_filter > 0) {
    $where_clauses[] = "MONTH(s.stock_date) = ?";
    $params[] = $month_filter;
    $types .= 'i';
}
if ($filter_year > 0) {
    $where_clauses[] = "YEAR(s.stock_date) = ?";
    $params[] = $filter_year;
    $types .= 'i';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

// สร้างหัวข้อรายงานแบบ Dynamic
if ($month_filter == 0 && $filter_year == 0 && empty($type_filter)) {
    $report_title = "รายงานสต็อกทั้งหมด";
} else {
    $report_title = "รายงานสต็อก";
    if (!empty($type_filter)) $report_title .= "ประเภท " . thai_type($type_filter);
    if ($month_filter > 0) $report_title .= " เดือน " . thai_month($month_filter);
    if ($filter_year > 0) $report_title .= " ปี " . ($filter_year + 543);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสต็อก</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
    /* 🎨 Theme Colors */
    --primary-color: #3498db;
    --secondary-color: #2c3e50;
    --light-bg: #f0f8ff;
    --navy-blue: #001f3f;
    --white: #ffffff;
    --light-gray: #f8f9fa;
    --gray-border: #ced4da;
    --text-color: #495057;

    /* ✅ Status Colors */
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
    color: var(--text-color);
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
    transition: transform 0.3s ease-in-out;
    box-shadow: 2px 0 15px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    z-index: 1000;
}
.sidebar.hidden { transform: translateX(-100%); }

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
    transition: all 0.2s ease;
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
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: flex;
    justify-content: center;
    align-items: center;
}

/* ====================== Main Content ====================== */
.main {
    margin-left: 250px;
    padding: 2rem;
    flex-grow: 1;
    transition: margin-left 0.3s ease-in-out;
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

.container {
    background-color: var(--white);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

/* ====================== Filters ====================== */
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
.filter-form label { font-weight: 500; }
.filter-form select {
    padding: 0.6rem 1rem;
    border-radius: 8px;
    border: 1px solid var(--gray-border);
    font-size: 1rem;
}

/* ====================== Action Buttons ====================== */
.action-buttons { display: flex; gap: 0.75rem; }
.action-button {
    padding: 0.7rem 1.2rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    color: white;
    font-size: 1rem;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}
.action-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.btn-filter { background-color: var(--primary-color); }
.btn-pdf    { background-color: var(--danger); }
.btn-excel  { background-color: var(--success); }
.btn-email  { background-color: var(--warning); color: #333; }

/* ====================== Tables ====================== */
table { width: 100%; border-collapse: collapse; }
thead th {
    background-color: var(--navy-blue);
    color: var(--white);
    padding: 15px;
    text-align: center;
}
tbody td {
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
}
tbody tr:nth-child(even) { background-color: var(--light-gray); }
tbody tr:hover { background-color: #d4eaf7; }

.stock-import { color: var(--success); font-weight: bold; text-align: center; }
.stock-remove { color: var(--danger); font-weight: bold; text-align: center; }

/* ====================== Modal ====================== */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
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
    <button class="toggle-btn" id="toggle-sidebar-btn"><i class="fas fa-bars"></i></button>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
            <h2>รายงานคลังสินค้า</h2>
        </div>
        <a href="dashboard.php"><i class="fas fa-home fa-fw"></i>&nbsp; <span>กลับ</span></a>
        <a href="stock_dashboard.php?type=&<?= $link_query_string ?>" class="<?= ($type_filter == '') ? 'active' : '' ?>"><i class="fas fa-list fa-fw"></i>&nbsp; <span>ทั้งหมด</span></a>
        <a href="stock_dashboard.php?type=import&<?= $link_query_string ?>" class="<?= ($type_filter == 'import') ? 'active' : '' ?>"><i class="fas fa-arrow-down fa-fw"></i>&nbsp; <span>รับเข้า</span></a>
        <a href="stock_dashboard.php?type=remove&<?= $link_query_string ?>" class="<?= ($type_filter == 'remove') ? 'active' : '' ?>"><i class="fas fa-arrow-up fa-fw"></i>&nbsp; <span>จ่ายออก</span></a>
        <a href="stock_graphs.php"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
    </div>

    <div class="main" id="main-content">
        <div class="header-main">
            <h1><i class="fas fa-boxes"></i>&nbsp; รายงานคลังสินค้า</h1>
        </div>

        <div class="container">
            <div class="filter-box">
                <form method="get" class="filter-form">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($type_filter) ?>">
                    <label for="month-select">เดือน:</label>
                    <select name="month" id="month-select">
                        <option value="0" <?= ($month_filter == 0) ? 'selected' : '' ?>>ทุกเดือน</option>
                        <?php for ($m = 1; $m <= 12; $m++) : ?>
                            <option value="<?= $m ?>" <?= ($month_filter == $m) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                        <?php endfor; ?>
                    </select>
                    <label for="year-select">ปี:</label>
                    <select name="year" id="year-select">
                        <option value="0" <?= ($filter_year == 0) ? 'selected' : '' ?>>ทุกปี</option>
                        <?php for ($y = 2022; $y <= 2035; $y++) : ?>
                            <option value="<?= $y ?>" <?= ($filter_year == $y) ? 'selected' : '' ?>><?= $y + 543 ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="action-button btn-filter"><i class="fas fa-filter"></i> กรองข้อมูล</button>
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
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>วันที่</th>
                            <th style="text-align:left;">สินค้า</th>
                            <th>ประเภท</th>
                            <th>จำนวน</th>
                            <th>หน่วย</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($rows)) :
                        $i = 1;
                        foreach ($rows as $row) :
                    ?>
                    <tr>
                        <td style="text-align:center;"><?= $i++ ?></td>
                        <td style="text-align:center;"><?= date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543) ?></td>
                        <td style="text-align:left;"><?= htmlspecialchars($row['product_name']) ?></td>
                        <td class="<?= $row['stock_type']=='import' ? 'stock-import' : 'stock-remove' ?>"><?= thai_type($row['stock_type']) ?></td>
                        <td style="text-align:center; font-weight:bold;"><?= number_format($row['quantity']) ?></td>
                        <td style="text-align:center;"><?= htmlspecialchars($row['unit']) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem; color:#7f8c8d;"><i class="fas fa-info-circle"></i> ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                    </tr>
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
                    <label style="font-weight:normal;"><input type="checkbox" name="file_format" value="pdf" checked> PDF</label>
                    <label style="font-weight:normal;"><input type="checkbox" name="file_format" value="excel"> Excel</label>
                </div>
                <label for="recipientEmail">อีเมลผู้รับ:</label>
                <input type="email" id="recipientEmail" placeholder="example@email.com" required>
                <div id="emailStatus" style="margin-top: 1rem; text-align: center;"></div>
            </div>
            <div class="modal-footer">
                <button id="sendEmailButton" class="action-button btn-email" style="color:white">ส่ง</button>
            </div>
        </div>
    </div>

   <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('toggle-sidebar-btn');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('full-width');
            });
        }
        
        if (window.innerWidth <= 768) {
            sidebar.classList.add('hidden');
            mainContent.classList.add('full-width');
        }

        const pdfButton = document.getElementById('pdfButton');
        const excelButton = document.getElementById('excelButton');
        const emailModalButton = document.getElementById('emailModalButton');
        
        function buildExportUrl(baseUrl) {
            const params = new URLSearchParams(window.location.search);
            return `${baseUrl}?${params.toString()}`;
        }

        if(pdfButton) pdfButton.addEventListener('click', () => window.open(buildExportUrl('stock_pdf.php'), '_blank'));
        if(excelButton) excelButton.addEventListener('click', () => window.location.href = buildExportUrl('stock_excel.php'));

        const emailModal = document.getElementById('emailModal');
        if (emailModalButton && emailModal) {
            const sendEmailButton = document.getElementById('sendEmailButton');
            const recipientEmailInput = document.getElementById('recipientEmail');
            const emailStatus = document.getElementById('emailStatus');
            const closeModalBtn = document.getElementById('closeModalBtn');
            
            const openModal = () => {
                emailModal.style.display = 'flex';
                recipientEmailInput.value = '';
                emailStatus.innerHTML = '';
                sendEmailButton.disabled = false;
                sendEmailButton.innerHTML = 'ส่ง';
                const pdfCheckbox = emailModal.querySelector('input[value="pdf"]');
                const excelCheckbox = emailModal.querySelector('input[value="excel"]');
                if(pdfCheckbox) pdfCheckbox.checked = true;
                if(excelCheckbox) excelCheckbox.checked = false;
            };

            const closeModal = () => { emailModal.style.display = 'none'; };

            emailModalButton.addEventListener('click', openModal);
            if(closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
            emailModal.addEventListener('click', (e) => { if (e.target === emailModal) closeModal(); });

            sendEmailButton.addEventListener('click', async function() {
                const email = recipientEmailInput.value.trim();
                if (!email || !/\S+@\S+\.\S+/.test(email)) {
                    emailStatus.innerHTML = `<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>`;
                    return;
                }

                const selectedFormats = Array.from(emailModal.querySelectorAll('input[name="file_format"]:checked')).map(cb => cb.value);
                if (selectedFormats.length === 0) {
                    emailStatus.innerHTML = `<span style="color: var(--danger);">กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์</span>`;
                    return;
                }
                
                this.disabled = true;
                emailStatus.innerHTML = `<span style="color: var(--primary-color);">กำลังส่ง... <i class="fas fa-spinner fa-spin"></i></span>`;

                const formData = new FormData();
                formData.append('email', email);
                
                selectedFormats.forEach(format => formData.append('file_formats[]', format));
                
                const params = new URLSearchParams(window.location.search);
                for (const [key, value] of params) {
                    formData.append(key, value);
                }

                try {
                    // ** เปลี่ยนชื่อไฟล์ที่เรียกใช้ให้ถูกต้อง **
                    const response = await fetch('send_stock_email.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        emailStatus.innerHTML = `<span style="color: var(--success);"><i class="fas fa-check-circle"></i> ${result.message}</span>`;
                        setTimeout(closeModal, 2500);
                    } else {
                        emailStatus.innerHTML = `<span style="color: var(--danger);"><i class="fas fa-times-circle"></i> ผิดพลาด: ${result.message || 'Error'}</span>`;
                        this.disabled = false;
                    }
                } catch (error) {
                    emailStatus.innerHTML = `<span style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> เกิดข้อผิดพลาดในการเชื่อมต่อ</span>`;
                    this.disabled = false;
                }
            });
        }
    });
    </script>
</body>
</html>
