<?php
session_start();
// Make sure to have this file with your database connection details
require_once "db.php";

// Thai language helper functions
function thai_month($month) {
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return $months[(int)$month] ?? '';
}

function thai_type($type) {
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก';
}

// Get filter parameters from URL
$type_filter = $_GET['type'] ?? '';
$month_filter = $_GET['month'] ?? 0;
$year_filter = $_GET['year'] ?? date('Y');
$page = $_GET['page'] ?? 'table'; // 'table' or 'graph'

// --- Data Fetching for Table ---
$sql = "SELECT s.stock_id, s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit
        FROM stock s
        JOIN products p ON s.product_id = p.product_id";

// Build WHERE clause based on filters
$where_clauses = [];
if ($type_filter) {
    $where_clauses[] = "s.stock_type = '" . $conn->real_escape_string($type_filter) . "'";
}
if ($month_filter > 0) {
    $where_clauses[] = "MONTH(s.stock_date) = " . (int)$month_filter;
}
if ($year_filter) {
    $where_clauses[] = "YEAR(s.stock_date) = " . (int)$year_filter;
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

// **Sort by the newest data first**
$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";
$result = $conn->query($sql);

// --- Data Fetching for Graph ---
$graph_data = [];
$graph_labels = [];
$graph_sql = "SELECT s.stock_type, SUM(s.quantity) AS total_qty FROM stock s";

if (!empty($where_clauses)) {
    $graph_sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$graph_sql .= " GROUP BY s.stock_type";

$graph_result = $conn->query($graph_sql);
if ($graph_result) {
    while ($row = $graph_result->fetch_assoc()) {
        $graph_data[$row['stock_type']] = $row['total_qty'];
        $graph_labels[] = thai_type($row['stock_type']);
    }
}
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
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap');

        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --light-teal-bg: #eaf6f6;
            --navy-blue: #001f3f;
            --gold-accent: #fca311;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --gray-border: #ced4da;
            --text-color: #495057;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #9b59b6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--light-teal-bg);
            color: var(--text-color);
            display: flex;
        }

        /* --- Sidebar --- */
        .sidebar {
            width: 250px;
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease-in-out;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }
        .sidebar.hidden { transform: translateX(-100%); }
        .sidebar-header { text-align: center; margin-bottom: 2rem; }
        .logo {
            width: 90px; height: 90px; border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            object-fit: cover; margin-bottom: 1rem;
        }
        .sidebar-header h2 { font-size: 1.5rem; font-weight: 700; margin: 0; }
        .sidebar a {
            color: white; text-decoration: none; font-size: 1.1rem;
            padding: 0.8rem 1rem; border-radius: 8px;
            width: 100%; text-align: left;
            transition: background-color 0.2s ease, transform 0.2s ease;
            margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem;
        }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.15); transform: translateX(5px); }
        .sidebar a.active { background-color: rgba(0, 0, 0, 0.2); font-weight: 500; }
        .toggle-btn {
            position: fixed; top: 1rem; right: 1rem; z-index: 1001;
            background-color: var(--primary-color); color: white; border: none;
            border-radius: 50%; width: 40px; height: 40px;
            font-size: 1.5rem; cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: flex; justify-content: center; align-items: center;
        }

        /* --- Main Content --- */
        .main {
            margin-left: 250px;
            padding: 2rem;
            flex-grow: 1;
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - 250px);
        }
        .main.full-width { margin-left: 0; width: 100%; }
        
        .header-main {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        .header-main h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem; color: var(--navy-blue);
            margin: 0; border: none;
            display: flex; align-items: center; gap: 1rem;
        }
        
        .container {
            background-color: var(--white);
            padding: 25px; border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; }
        .filter-form label { font-weight: 500; }
        .filter-form select {
            padding: 0.6rem 1rem; border-radius: 8px;
            border: 1px solid var(--gray-border); font-size: 1rem;
            font-family: 'Sarabun', sans-serif;
        }
        #shareButton {
            padding: 0.7rem 1.5rem; border: none; border-radius: 8px;
            font-weight: 500; cursor: pointer; color: white; font-size: 1rem;
            background-color: var(--info); transition: all 0.2s ease;
            margin-left: auto;
        }
        #shareButton:hover { background-color: #8e44ad; transform: translateY(-2px); }

        /* --- Table --- */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background-color: var(--navy-blue); color: var(--white);
            padding: 15px; text-align: left; font-size: 0.9rem;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        tbody td {
            padding: 15px; border-bottom: 1px solid #e0e0e0;
            color: #333;
        }
        tbody tr:nth-child(even) { background-color: var(--light-gray); }
        tbody tr:hover { background-color: #d4eaf7; }

        /* --- Chart Section --- */
        .chart-container { padding-top: 1rem; }
        
        /* --- Modal --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 31, 63, 0.6); backdrop-filter: blur(5px); z-index: 2000; justify-content: center; align-items: center; }
        .modal-content { background-color: var(--white); padding: 30px 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 500px; position: relative; animation: fadeInScale 0.4s ease-out; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .modal-header h3 { margin: 0; color: var(--navy-blue); font-size: 1.8rem; }
        .close-button { background: none; border: none; font-size: 2rem; cursor: pointer; color: #aaa; transition: color 0.2s ease, transform 0.2s ease; }
        .close-button:hover { color: var(--danger); transform: rotate(90deg); }
        .modal-body label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .modal-body select { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); margin-bottom: 1.5rem; font-size: 1rem; }
        .action-buttons { display: flex; gap: 1rem; }
        .action-buttons button { flex-grow: 1; padding: 0.8rem 1rem; border-radius: 8px; border: none; color: white; font-weight: 500; cursor: pointer; transition: all 0.2s; }
        .action-buttons button:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        #downloadButton { background-color: var(--success); }
        #emailButton { background-color: var(--warning); color: #212529;}
        #messengerButton { background-color: var(--primary-color); }
        .action-buttons button:disabled { background-color: #bdc3c7; cursor: not-allowed; opacity: 0.7; transform: none; box-shadow: none; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; width: 100%; padding: 1.5rem; }
            .filter-form { flex-direction: column; align-items: stretch; gap: 1rem; }
            #shareButton { margin-left: 0; }
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
        
        <?php if (isset($_GET['status']) && isset($_GET['msg'])): ?>
            <div class="container" style="padding: 15px; background-color: <?= $_GET['status'] == 'success' ? 'var(--success)' : 'var(--danger)' ?>; color: white;">
                <p><?= htmlspecialchars($_GET['msg']) ?></p>
            </div>
        <?php endif; ?>

        <div class="container">
            <form method="get" class="filter-form">
                <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
                <label for="type-filter">ประเภท:</label>
                <select name="type" id="type-filter" onchange="this.form.submit()">
                    <option value="" <?= ($type_filter == '') ? 'selected' : '' ?>>ทั้งหมด</option>
                    <option value="import" <?= ($type_filter == 'import') ? 'selected' : '' ?>>รับเข้า</option>
                    <option value="remove" <?= ($type_filter == 'remove') ? 'selected' : '' ?>>จ่ายออก</option>
                </select>
                <label for="month-filter">เดือน:</label>
                <select name="month" id="month-filter" onchange="this.form.submit()">
                    <option value="0" <?= ($month_filter == 0) ? 'selected' : '' ?>>ทั้งปี</option>
                    <?php for ($m = 1; $m <= 12; $m++) : ?>
                        <option value="<?= $m ?>" <?= ($month_filter == $m) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                    <?php endfor; ?>
                </select>
                <label for="year-filter">ปี (พ.ศ.):</label>
                <select name="year" id="year-filter" onchange="this.form.submit()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--) : ?>
                        <option value="<?= $y ?>" <?= ($year_filter == $y) ? 'selected' : '' ?>><?= $y + 543 ?></option>
                    <?php endfor; ?>
                </select>
                
                <button type="button" id="shareButton"><i class="fas fa-share-alt"></i>&nbsp; ส่งออกรายงาน</button>
            </form>
        </div>

        <div class="container">
            <h2><i class="fas fa-table"></i> ตารางข้อมูลสต็อก</h2>
            <div class="table-wrapper">
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
                            $i = 1; 
                            while ($row = $result->fetch_assoc()) :
                        ?>
                            <tr>
                                <td style="text-align:center;"><?= $i++ ?></td>
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

    <div id="shareModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-share-alt"></i> ส่งออกรายงาน</h3>
                <button class="close-button" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <label for="fileSelect">เลือกรูปแบบไฟล์เพื่อส่งออก:</label>
                <select id="fileSelect">
                    <option value="">-- กรุณาเลือก --</option>
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                </select>
                
                <label for="recipientEmail">อีเมลผู้รับ:</label>
                <input type="email" id="recipientEmail" placeholder="ตัวอย่าง: recipient@example.com" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); margin-bottom: 1.5rem; font-size: 1rem;">
                
                <div class="action-buttons">
                    <button id="downloadButton" disabled><i class="fas fa-download"></i> ดาวน์โหลด</button>
                    <button id="emailButton" disabled><i class="fas fa-envelope"></i> ส่งอีเมลแนบไฟล์</button>
                    <button id="messengerButton" disabled><i class="fab fa-facebook-messenger"></i> Messenger</button>
                </div>
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
        
        if (window.innerWidth <= 768) {
            sidebar.classList.add('hidden');
            mainContent.classList.add('full-width');
        }

        const shareButton = document.getElementById('shareButton');
        const modal = document.getElementById('shareModal');
        const fileSelect = document.getElementById('fileSelect');
        const downloadBtn = document.getElementById('downloadButton');
        const emailBtn = document.getElementById('emailButton');
        const recipientEmailInput = document.getElementById('recipientEmail'); // เพิ่มตัวแปร
        const messengerBtn = document.getElementById('messengerButton');
        const actionButtons = [downloadBtn, emailBtn, messengerBtn];
        
        window.openModal = () => modal.style.display = 'flex';
        window.closeModal = () => {
            modal.style.display = 'none';
            fileSelect.value = '';
            recipientEmailInput.value = ''; // ล้างช่องอีเมลเมื่อปิด
            toggleActionButtons(true);
        }

        shareButton.addEventListener('click', openModal);
        modal.addEventListener('click', function(event) {
            if (event.target === modal) { closeModal(); }
        });
        
        function toggleActionButtons(disabled) {
            // Logic สำหรับปุ่มดาวน์โหลด/Messenger ยังคงพึ่งพา fileSelect
            downloadBtn.disabled = disabled;
            messengerBtn.disabled = disabled;
            
            // Logic สำหรับปุ่มอีเมลต้องเช็คทั้ง fileSelect และ recipientEmail
            const emailValue = recipientEmailInput.value.trim();
            const fileValue = fileSelect.value;
            emailBtn.disabled = !(fileValue && emailValue && /\S+@\S+\.\S+/.test(emailValue)); // เช็ครูปแบบอีเมลด้วย
        }

        fileSelect.addEventListener('change', function() {
             // เรียกใช้ toggleActionButtons เพื่ออัปเดตสถานะปุ่มทั้งหมด
            toggleActionButtons(this.value === '');
        });
        
        // เพิ่ม Listener สำหรับช่องกรอกอีเมล
        recipientEmailInput.addEventListener('input', function() {
             // เรียกใช้ toggleActionButtons เพื่ออัปเดตสถานะปุ่มทั้งหมด
             toggleActionButtons(fileSelect.value === '' || this.value.trim() === '');
        });


        const currentParams = new URLSearchParams(window.location.search);
        const reportTitle = "รายงานสต็อกสินค้า";

        downloadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const fileType = fileSelect.value;
            if (!fileType) return;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังเตรียมไฟล์...';
            this.disabled = true;

            const exportUrl = `stock_export.php?format=${fileType}&${currentParams.toString()}`;

            fetch(exportUrl)
                .then(response => {
                    if (!response.ok) { throw new Error('เกิดข้อผิดพลาดในการสร้างไฟล์'); }
                    const disposition = response.headers.get('Content-Disposition');
                    let filename = `stock_report_${new Date().toISOString()}.${fileType}`;
                    if (disposition && disposition.indexOf('attachment') !== -1) {
                        const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                        const matches = filenameRegex.exec(disposition);
                        if (matches != null && matches[1]) {
                            filename = matches[1].replace(/['"]/g, '');
                        }
                    }
                    return response.blob().then(blob => ({ blob, filename }));
                })
                .then(({ blob, filename }) => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    this.innerHTML = '<i class="fas fa-download"></i> ดาวน์โหลด';
                    closeModal();
                })
                .catch(error => {
                    console.error('Download error:', error);
                    alert('ไม่สามารถดาวน์โหลดไฟล์ได้');
                    this.innerHTML = '<i class="fas fa-download"></i> ดาวน์โหลด';
                    toggleActionButtons(fileSelect.value === '');
                });
        });

        // โค้ดสำหรับปุ่ม 'ส่งอีเมลแนบไฟล์' ที่ถูกแก้ไข
        emailBtn.addEventListener('click', function() {
            const fileType = fileSelect.value;
            const recipientEmail = recipientEmailInput.value.trim();
            if (!fileType || !recipientEmail) return;

            // ตรวจสอบรูปแบบอีเมลขั้นสุดท้ายก่อนส่ง
            if (!/\S+@\S+\.\S+/.test(recipientEmail)) {
                alert('กรุณากรอกรูปแบบอีเมลให้ถูกต้อง');
                return;
            }
            
            // ชี้ไปยังสคริปต์ stock_report_email.php
            const emailUrl = `stock_report_email.php?format=${fileType}&recipient=${encodeURIComponent(recipientEmail)}&${currentParams.toString()}`;

            this.innerHTML = '<i class="fas fa-paper-plane fa-spin"></i> กำลังส่งอีเมล...';
            this.disabled = true;

            // เปลี่ยนเส้นทางผู้ใช้ไปยังสคริปต์ส่งอีเมล
            window.location.href = emailUrl;
            // ไม่ต้อง closeModal() เพราะจะถูก redirect ไปหน้าใหม่
        });

        messengerBtn.addEventListener('click', function() {
            const fileType = fileSelect.value;
            if (!fileType) return;
            const appId = 'https://www.facebook.com/kritsanai.thongkam';
            if (appId === 'YOUR_APP_ID') {
                alert('กรุณาตั้งค่า Facebook App ID ในไฟล์โค้ดก่อนใช้งานฟังก์ชันนี้');
                return;
            }
            const exportUrl = encodeURIComponent(`${window.location.origin}${window.location.pathname.replace('stock_dashboard.php', '')}stock_export.php?format=${fileType}&${currentParams.toString()}`);
            const messengerLink = `https://www.facebook.com/dialog/send?link=${exportUrl}&app_id=${appId}&redirect_uri=${encodeURIComponent(window.location.href)}`;
            window.open(messengerLink, '_blank');
            closeModal();
        });
    });
    </script>
</body>

</html>
