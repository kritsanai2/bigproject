<?php
require_once "auth.php";
require_once "db.php";

// ฟังก์ชันแปลงเลขเดือนเป็นชื่อเดือนภาษาไทย
function thai_month($month)
{
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return $months[intval($month)] ?? '';
}

// --- รับค่าตัวกรอง ---
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$current_year = intval(date('Y'));
$filter_year  = isset($_GET['year']) ? intval($_GET['year']) : $current_year; // ค่าเริ่มต้นคือปีปัจจุบัน

// --- สร้าง SQL Query ---
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
if ($filter_year > 0) {
    $where_clauses[] = "YEAR(o.order_date) = ?";
    $params[] = $filter_year;
    $types .= "i";
}

if (!empty($where_clauses)) {
    $sql_detailed_sales .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_detailed_sales .= " ORDER BY o.order_date DESC, o.order_id DESC";

$stmt = $conn->prepare($sql_detailed_sales);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result_sales = $stmt->get_result();

// --- สร้างหัวข้อรายงานแบบ Dynamic ---
$report_title = "รายงานการขาย";
if ($filter_month > 0) {
    $report_title .= " เดือน " . thai_month($filter_month);
}
if ($filter_year > 0) {
    $report_title .= " ปี " . ($filter_year + 543);
} else {
    $report_title .= "ทั้งหมด";
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>💧 รายงานการขายรายสินค้า</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* ====================== Root Colors ====================== */
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
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .sidebar-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
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
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .sidebar a.active {
            background-color: rgba(0, 0, 0, 0.2);
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

        /* ====================== Header ====================== */
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
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        /* ====================== Filter & Actions ====================== */
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

        .filter-form select {
            padding: 0.65rem;
            border-radius: 8px;
            border: 1px solid var(--gray-border);
            font-size: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }

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

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-filter {
            background-color: var(--primary-color);
        }

        .btn-pdf {
            background-color: var(--danger);
        }

        .btn-excel {
            background-color: var(--success);
        }

        .btn-email {
            background-color: var(--warning);
            color: #333;
        }

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
        }

        tbody tr:nth-child(even) {
            background-color: var(--light-gray);
        }

        tbody tr:hover {
            background-color: #d4eaf7;
        }

        /* ====================== Modal ====================== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 31, 63, 0.6);
            backdrop-filter: blur(5px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--white);
            padding: 30px 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
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
            transition: all 0.2s;
        }

        .close-button:hover {
            color: var(--danger);
            transform: rotate(90deg);
        }

        .modal-footer {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
        }

        .modal-body input[type="email"] {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--gray-border);
            margin-top: 0.5rem;
            font-size: 1rem;
        }
    </style>
</head>

<body>

    <button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
            <h2>ระบบจัดการ</h2>
        </div>
        <a href="dashboard.php"><i class="fas fa-home fa-fw"></i>&nbsp; <span>กลับ</span></a>
        <a href="orders.php"><i class="fas fa-shopping-cart fa-fw"></i>&nbsp; <span>จัดการคำสั่งซื้อ</span></a>
        <a href="orders_dashboard.php" class="active"><i class="fas fa-receipt fa-fw"></i>&nbsp; <span>รายงานการขาย</span></a>
        <a href="orders_graphs.php"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>กราฟยอดขาย</span></a>
    </div>

    <div class="main" id="main">
        <div class="header-main">
            <h1><i class="fas fa-file-invoice-dollar"></i>&nbsp; รายงานการขาย</h1>
        </div>

        <div class="container">
            <div class="filter-box">
                <form method="get" class="filter-form">
                    <label for="month-select">เดือน :</label>
                    <select name="month" id="month-select">
                        <option value="0" <?= ($filter_month == 0) ? 'selected' : '' ?>>ทุกเดือน</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($filter_month == $m) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                        <?php endfor; ?>
                    </select>

                    <label for="year-select">ปี :</label>
                    <select name="year" id="year-select">
                        <option value="0" <?= ($filter_year == 0) ? 'selected' : '' ?>>ทุกปี</option>
                        <?php for ($y = 2022; $y <= 2035; $y++): ?>
                            <option value="<?= $y ?>" <?= ($y == $filter_year) ? 'selected' : '' ?>>
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
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>วันที่</th>
                            <th style="text-align:left;">ชื่อลูกค้า</th>
                            <th style="text-align:left;">ชื่อสินค้า</th>
                            <th style="text-align:right;">จำนวน</th>
                            <th style="text-align:right;">ราคา/หน่วย</th>
                            <th style="text-align:right;">ราคารวม (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_sales && $result_sales->num_rows > 0):
                            $i = $result_sales->num_rows;
                            while ($row = $result_sales->fetch_assoc()):
                        ?>
                                <tr>
                                    <td style="text-align:center;"><?= $i-- ?></td>
                                    <td style="text-align:center;"><?= date('d/m/', strtotime($row['order_date'])) . (date('Y', strtotime($row['order_date'])) + 543) ?></td>
                                    <td style="text-align:left;"><?= htmlspecialchars($row['customer_name']) ?></td>
                                    <td style="text-align:left;"><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td style="text-align:right;"><?= number_format($row['quantity']) . ' ' . htmlspecialchars($row['unit']) ?></td>
                                    <td style="text-align:right;"><?= number_format($row['price'], 2) ?></td>
                                    <td style="text-align:right; font-weight:bold; color:var(--primary-color);"><?= number_format($row['item_total'], 2) ?></td>
                                </tr>
                            <?php
                            endwhile;
                        else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 2rem; color:#7f8c8d;"><i class="fas fa-info-circle"></i> ไม่พบข้อมูลการขายตามเงื่อนไขที่เลือก</td>
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

            function buildExportUrl(baseUrl) {
                const params = new URLSearchParams(window.location.search);
                return `${baseUrl}?${params.toString()}`;
            }

            if (pdfButton) {
                pdfButton.addEventListener('click', () => window.open(buildExportUrl('export_sales_pdf.php'), '_blank'));
            }

            if (excelButton) {
                excelButton.addEventListener('click', () => window.location.href = buildExportUrl('export_sales_excel.php'));
            }

            if (emailModalButton) {
                const closeModalBtn = document.getElementById('closeModalBtn');
                const sendEmailButton = document.getElementById('sendEmailButton');
                const recipientEmailInput = document.getElementById('recipientEmail');
                const emailStatus = document.getElementById('emailStatus');

                const openModal = () => {
                    emailModal.style.display = 'flex';
                    recipientEmailInput.value = '';
                    emailStatus.innerHTML = '';
                    sendEmailButton.disabled = false;
                    const pdfCheckbox = emailModal.querySelector('input[value="pdf"]');
                    const excelCheckbox = emailModal.querySelector('input[value="excel"]');
                    if (pdfCheckbox) pdfCheckbox.checked = true;
                    if (excelCheckbox) excelCheckbox.checked = false;
                };
                const closeModal = () => {
                    emailModal.style.display = 'none';
                };

                emailModalButton.addEventListener('click', openModal);
                closeModalBtn.addEventListener('click', closeModal);
                emailModal.addEventListener('click', (event) => {
                    if (event.target === emailModal) closeModal();
                });

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
                    selectedFormats.forEach(format => {
                        formData.append('file_formats[]', format);
                    });

                    const params = new URLSearchParams(window.location.search);
                    for (const [key, value] of params) {
                        formData.append(key, value);
                    }

                    try {
                        const response = await fetch('send_sales_email.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.status === 'success') {
                            emailStatus.innerHTML = `<span style="color: var(--success);">${result.message}</span>`;
                            setTimeout(closeModal, 2000);
                        } else {
                            emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาด: ${result.message || 'เกิดข้อผิดพลาด'}</span>`;
                            this.disabled = false;
                        }
                    } catch (error) {
                        emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาดในการเชื่อมต่อกับ Server</span>`;
                        this.disabled = false;
                    }
                });
            }
        });
    </script>

</body>

</html>