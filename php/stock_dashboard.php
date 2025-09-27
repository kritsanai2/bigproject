<?php
session_start();
// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once "db.php";
require_once __DIR__ . '/includes/auth.php';

// 2. ฟังก์ชันช่วยแปลภาษาไทย
function thai_month($month) {
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return $months[(int)$month] ?? '';
}

function thai_type($type) {
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก';
}

// 3. รับค่าตัวกรองจาก URL
$type_filter = $_GET['type'] ?? '';
$month_filter = $_GET['month'] ?? 0;

// *** กำหนดค่าเริ่มต้นเป็นปีปัจจุบัน (ค.ศ.) ***
$current_year = date('Y');
$filter_year = isset($_GET['year']) && intval($_GET['year']) > 0 ? intval($_GET['year']) : intval($current_year);
$page = $_GET['page'] ?? 'table';

// 4. ดึงข้อมูลสำหรับตาราง (ใช้ Prepared Statements เพื่อความปลอดภัย)
$sql = "SELECT s.stock_id, s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit
        FROM stock s
        JOIN products p ON s.product_id = p.product_id";

// สร้างเงื่อนไข WHERE ตามตัวกรอง
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
    $params[] = (int)$month_filter;
    $types .= 'i';
}
// ถ้า filter_year เป็นปีปัจจุบัน หรือปีที่ระบุ ให้กรองปีนั้น
if ($filter_year > 0) {
    $where_clauses[] = "YEAR(s.stock_date) = ?";
    $params[] = (int)$filter_year;
    $types .= 'i';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

// เรียงลำดับจากใหม่ไปเก่า (DESC)
$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";

// ใช้ Prepared Statement
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// ดึงจำนวนแถวทั้งหมดเพื่อใช้ในการนับลำดับย้อนกลับ
$total_rows = $result->num_rows;

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานสต็อก</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap');
        :root {
            --primary-color: #3498db; --secondary-color: #2c3e50;
            --light-bg: #eaf6f6; --navy-blue: #001f3f; --white: #ffffff;
            --light-gray: #f8f9fa; --gray-border: #ced4da; --text-color: #495057;
            --success: #2ecc71; --danger: #e74c3c; --warning: #f39c12;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Sarabun', sans-serif; background-color: var(--light-bg); color: var(--text-color); display: flex; }
        .sidebar {
            width: 250px; background-color: var(--primary-color); color: white;
            padding: 1.5rem; height: 100vh; position: fixed; top: 0; left: 0;
            transition: transform 0.3s ease-in-out; box-shadow: 2px 0 15px rgba(0,0,0,0.1);
            display: flex; flex-direction: column; z-index: 1000;
        }
        .sidebar.hidden { transform: translateX(-100%); }
        .sidebar-header { text-align: center; margin-bottom: 2rem; }
        .logo { width: 90px; height: 90px; border-radius: 50%; border: 4px solid rgba(255,255,255,0.3); object-fit: cover; margin-bottom: 1rem; }
        .sidebar-header h2 { font-size: 1.5rem; }
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
        .container { background-color: var(--white); padding: 25px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
        .filter-form label { font-weight: 500; }
        .filter-form select { padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; }
        .action-button {
            padding: 0.7rem 1.2rem; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;
            color: white; font-size: 0.95rem; transition: all 0.2s ease; display: inline-flex;
            align-items: center; gap: 0.5rem; margin-left: 0.5rem;
        }
        .action-button:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .action-button.pdf { background-color: var(--danger); }
        .action-button.excel { background-color: var(--success); }
        .action-button.email { background-color: var(--warning); color: #333; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background-color: var(--navy-blue); color: var(--white); padding: 15px; text-align: left; }
        tbody td { padding: 15px; border-bottom: 1px solid #e0e0e0; }
        tbody tr:nth-child(even) { background-color: var(--light-gray); }
        tbody tr:hover { background-color: #d4eaf7; }
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
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; width: 100%; }
            .filter-form { flex-direction: column; align-items: stretch; gap: 1rem; }
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
        <a href="stock_dashboard.php?page=table&type=" class="<?= ($page == 'table' && $type_filter == '') ? 'active' : '' ?>"><i class="fas fa-list fa-fw"></i>&nbsp; <span>ทั้งหมด</span></a>
        <a href="stock_dashboard.php?page=table&type=import" class="<?= ($page == 'table' && $type_filter == 'import') ? 'active' : '' ?>"><i class="fas fa-arrow-down fa-fw"></i>&nbsp; <span>รับเข้า</span></a>
        <a href="stock_dashboard.php?page=table&type=remove" class="<?= ($page == 'table' && $type_filter == 'remove') ? 'active' : '' ?>"><i class="fas fa-arrow-up fa-fw"></i>&nbsp; <span>จ่ายออก</span></a>
        <a href="stock_graphs.php"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
    </div>

    <div class="main" id="main-content">
        <div class="header-main">
            <h1><i class="fas fa-boxes"></i>&nbsp; รายงานคลังสินค้า</h1>
        </div>

        <div class="container">
            <form method="get" class="filter-form" id="filterForm">
                <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
                <label for="type-filter">ประเภท:</label>
                <select name="type" id="type-filter" onchange="this.form.submit()">
                    <option value="" <?= ($type_filter == '') ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="import" <?= ($type_filter == 'import') ? 'selected' : '' ?>>รับเข้า</option>
                    <option value="remove" <?= ($type_filter == 'remove') ? 'selected' : '' ?>>จ่ายออก</option>
                </select>
                <label for="month-filter">เดือน:</label>
                <select name="month" id="month-filter" onchange="this.form.submit()">
                    <option value="0" <?= ($month_filter == 0) ? 'selected' : '' ?>>ทั้งหมด</option>
                    <?php for ($m = 1; $m <= 12; $m++) : ?>
                        <option value="<?= $m ?>" <?= ($month_filter == $m) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                    <?php endfor; ?>
                </select>
                <label for="year-select">ปี (พ.ศ.):</label>
<select name="year" id="year-select" onchange="this.form.submit()">
    <option value="<?= $current_year ?>" <?= (intval($filter_year) == intval($current_year)) ? 'selected' : '' ?>>
        ปีปัจจุบัน (<?= intval($current_year) + 543 ?>)
    </option>
    <?php 
    // วนลูปตั้งแต่ปี ค.ศ. 2022 (พ.ศ. 2565) ถึง ค.ศ. 2135 (พ.ศ. 2678)
    for($y = 2022; $y <= 2135; $y++): 
        if ($y == $current_year) continue; // ข้ามปีปัจจุบันหากถูกเพิ่มแล้ว
    ?>
    <option value="<?= $y ?>" <?= (intval($y) == intval($filter_year)) ? 'selected' : '' ?>>
        <?= $y + 543 ?>
    </option>
    <?php endfor; ?>
</select>
                <div style="margin-left:auto;">
                    <button type="button" id="pdfButton" class="action-button pdf"><i class="fas fa-file-pdf"></i> PDF</button>
                    <button type="button" id="excelButton" class="action-button excel"><i class="fas fa-file-excel"></i> Excel</button>
                    <button type="button" id="emailModalButton" class="action-button email"><i class="fas fa-paper-plane"></i> ส่ง Email</button>
                </div>
            </form>
        </div>

        <div class="container">
            <h2><i class="fas fa-table"></i> ตารางข้อมูลสต็อก</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>วันที่</th>
                            <th>สินค้า</th>
                            <th>ประเภท</th>
                            <th>จำนวน</th>
                            <th>หน่วย</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0) :
                            // ลำดับเริ่มต้นจากจำนวนแถวทั้งหมด (รายการใหม่สุด)
                            $i = $total_rows; 
                            while ($row = $result->fetch_assoc()) :
                        ?>
                            <tr>
                                <td style="text-align:center;"><?= $i-- ?></td>
                                <td style="text-align:center;"><?= date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543) ?></td>
                                <td><?= htmlspecialchars($row['product_name']) ?></td>
                                <td style="text-align:center;"><?= $row['stock_type']=='import' ? '<span style="color: #27ae60; font-weight:bold;">รับเข้า</span>' : '<span style="color: #c0392b; font-weight:bold;">จ่ายออก</span>' ?></td>
                                <td style="text-align:center; font-weight:bold;"><?= number_format($row['quantity']) ?></td>
                                <td style="text-align:center;"><?= htmlspecialchars($row['unit']) ?></td>
                            </tr>
                        <?php endwhile;
                        else : ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">ไม่พบข้อมูล</td>
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
                <button id="sendEmailButton" class="action-button email">ส่ง</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('toggle-sidebar-btn');

        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('full-width');
        });
        
        // ปิด/เปิด sidebar อัตโนมัติสำหรับหน้าจอขนาดเล็ก
        if (window.innerWidth <= 768) {
            sidebar.classList.add('hidden');
            mainContent.classList.add('full-width');
        }

        const pdfButton = document.getElementById('pdfButton');
        const excelButton = document.getElementById('excelButton');
        const emailModalButton = document.getElementById('emailModalButton');
        const sendEmailButton = document.getElementById('sendEmailButton');
        const emailModal = document.getElementById('emailModal');
        const recipientEmailInput = document.getElementById('recipientEmail');
        const emailStatus = document.getElementById('emailStatus');

        // ฟังก์ชันสร้าง URL สำหรับ Export
        function buildExportUrl(baseUrl) {
            const params = new URLSearchParams(window.location.search);
            params.delete('page');
            return `${baseUrl}?${params.toString()}`;
        }

        pdfButton.addEventListener('click', function() {
            window.open(buildExportUrl('stock_pdf.php'), '_blank');
        });

        excelButton.addEventListener('click', function() {
            window.location.href = buildExportUrl('stock_excel.php');
        });

        // ฟังก์ชัน Modal (ถูกประกาศใน window scope เพื่อให้เรียกใช้ได้จาก onclick ใน HTML)
        window.openModal = () => {
            emailModal.style.display = 'flex';
            recipientEmailInput.value = '';
            emailStatus.innerHTML = '';
            sendEmailButton.disabled = false;
            // *** ปรับ Logic การตั้งค่าเริ่มต้นสำหรับ Checkbox ***
            const pdfCheckbox = document.querySelector('input[name="file_format"][value="pdf"]');
            const excelCheckbox = document.querySelector('input[name="file_format"][value="excel"]');
            if (pdfCheckbox) pdfCheckbox.checked = true;
            if (excelCheckbox) excelCheckbox.checked = false; // เคลียร์ Excel
        };
        window.closeModal = () => { emailModal.style.display = 'none'; };

        emailModalButton.addEventListener('click', openModal);
        emailModal.addEventListener('click', (event) => {
            if (event.target === emailModal) closeModal();
        });

        // ฟังก์ชันส่งอีเมล
        sendEmailButton.addEventListener('click', async function() {
            const email = recipientEmailInput.value.trim();
            if (!email || !/\S+@\S+\.\S+/.test(email)) {
                emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>';
                return;
            }
            
            // *** ดึงค่า Checkbox ทั้งหมดที่ถูกเลือก ***
            const selectedFormats = Array.from(document.querySelectorAll('input[name="file_format"]:checked'))
                .map(cb => cb.value);

            if (selectedFormats.length === 0) {
                emailStatus.innerHTML = '<span style="color: var(--danger);">กรุณาเลือกรูปแบบไฟล์อย่างน้อย 1 ไฟล์</span>';
                return;
            }
            
            this.disabled = true;
            emailStatus.innerHTML = '<span style="color: var(--warning);">กำลังส่ง...</span>';

            const formData = new FormData();
            formData.append('email', email);
            
            // *** ส่งรูปแบบไฟล์ทั้งหมดเป็น Array/List (ใช้ file_formats[]) ***
            selectedFormats.forEach(format => {
                formData.append('file_formats[]', format);
            });

            // ส่งค่าตัวกรองทั้งหมดไปกับ FormData
            const params = new URLSearchParams(window.location.search);
            for (const [key, value] of params) {
                formData.append(key, value);
            }

            try {
                const response = await fetch('send_email.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.status === 'success') {
                    emailStatus.innerHTML = `<span style="color: var(--success);">${result.message}</span>`;
                    setTimeout(closeModal, 2000);
                } else {
                    emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาด: ${result.message || 'เกิดข้อผิดพลาดในการส่ง'}</span>`;
                    this.disabled = false;
                }
            } catch (error) {
                emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาด: ${error}</span>`;
                this.disabled = false;
            }
        });
    });
    </script>
</body>
</html>