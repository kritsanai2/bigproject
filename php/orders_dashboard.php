<?php
session_start();
require_once "db.php"; 

// ฟังก์ชันแปลงเดือนเป็นภาษาไทย
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
$filter_year  = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<style>
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
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', sans-serif; background-color: var(--light-teal-bg); color: var(--text-color); display: flex; }

    .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary-color), #2980b9); color: white; padding: 1.5rem; height: 100vh; position: fixed; top: 0; left: 0; transition: transform 0.3s ease-in-out; box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1); display: flex; flex-direction: column; z-index: 1000; }
    .sidebar.hidden { transform: translateX(-100%); }
    .sidebar-header { text-align: center; margin-bottom: 2rem; }
    .logo { width: 90px; height: 90px; border-radius: 50%; border: 4px solid rgba(255, 255, 255, 0.3); object-fit: cover; margin-bottom: 1rem; }
    .sidebar-header h2 { font-size: 1.5rem; font-weight: 700; margin: 0; }
    .sidebar a { color: white; text-decoration: none; font-size: 1.1rem; padding: 0.8rem 1rem; border-radius: 8px; width: 100%; text-align: left; transition: background-color 0.2s ease, transform 0.2s ease; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
    .sidebar a:hover { background-color: rgba(255, 255, 255, 0.15); transform: translateX(5px); }
    .sidebar a.active { background-color: rgba(0, 0, 0, 0.2); font-weight: 500; }
    .toggle-btn { position: fixed; top: 1rem; right: 1rem; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); display: flex; justify-content: center; align-items: center; }

    .main { margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s ease-in-out; width: calc(100% - 250px); }
    .main.full-width { margin-left: 0; }
    
    .header-main { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border-bottom: 2px solid var(--primary-color); padding-bottom: 1.5rem; margin-bottom: 2rem; }
    .header-main h1 { font-family: 'Playfair Display', serif; font-size: 2.5rem; color: var(--navy-blue); margin: 0; border: none; display: flex; align-items: center; gap: 1rem; }
    
    .container { background: var(--white); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 1.5rem; margin-bottom: 2rem; }
    .filter-form { display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem; }
    .filter-form label { font-weight: 500; }
    .filter-form select { padding: 0.75rem; border-radius: 8px; border: 1px solid var(--gray-border); font-size: 1rem; }
    
    #shareButton { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; background-color: var(--info); }
    #shareButton:hover { transform: translateY(-2px); background-color: #8e44ad; }
    
    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background-color: var(--navy-blue); color: var(--white); padding: 15px; text-align: left; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
    tbody td { padding: 15px; border-bottom: 1px solid #e0e0e0; color: #333; }
    tbody tr { transition: background-color 0.2s ease; }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }
    th:first-child, td:first-child { text-align: center; }
    th:nth-child(5), td:nth-child(5), th:nth-child(6), td:nth-child(6), th:nth-child(7), td:nth-child(7) { text-align: right; }
    td:nth-child(7) { color: var(--success); font-weight: bold; }
    
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
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../img/da.jfif" alt="โลโก้" class="logo">
        <h2>ข้อมูลการขาย</h2>
    </div>
    <a href="dashboard.php"><i class="fas fa-home fa-fw"></i>&nbsp; <span>หน้าหลัก</span></a>
    <a href="orders_dashboard.php" class="active"><i class="fas fa-receipt fa-fw"></i>&nbsp; <span>รายงานการขาย</span></a>
    <a href="orders_graphs.php"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>กราฟยอดขาย</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-file-invoice-dollar"></i>&nbsp; รายงานการขาย</h1>
        <button type="button" id="shareButton"><i class="fas fa-share-alt"></i>&nbsp; แชร์หน้านี้</button>
    </div>

    <div class="container">
        <form method="get" class="filter-form">
            <label for="month-select">เดือน:</label>
            <select name="month" id="month-select" onchange="this.form.submit()">
                <option value="0" <?= ($filter_month == 0) ? 'selected' : '' ?>>ทั้งปี</option>
                <?php for($m=1; $m<=12; $m++): ?>
                <option value="<?= $m ?>" <?= ($m == $filter_month) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                <?php endfor; ?>
            </select>
            <label for="year-select">ปี (พ.ศ.):</label>
            <select name="year" id="year-select" onchange="this.form.submit()">
                <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= ($y == $filter_year) ? 'selected' : '' ?>><?= $y + 543 ?></option>
                <?php endfor; ?>
            </select>
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
                    $i = count($rows);
                    $grand_total = 0;
                    foreach($rows as $row):
                        $grand_total += $row['item_total'];
                    ?>
                    <tr>
                        <td><?= $i-- ?></td>
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
                <?php if ($result_sales && $result_sales->num_rows > 0): ?>
                <tfoot style="background-color: var(--light-gray); font-weight: bold;">
                    <tr>
                        <td colspan="6" style="text-align: right; padding-right: 2rem; font-size: 1.1rem;">ยอดรวมทั้งหมด</td>
                        <td style="font-size: 1.2rem; color: var(--danger);"><?= number_format($grand_total, 2) ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<div id="shareModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-share-alt"></i> แชร์รายงาน</h3>
            <button class="close-button" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <label for="fileSelect">เลือกรูปแบบไฟล์เพื่อส่งออก:</label>
            <select id="fileSelect">
                <option value="">-- กรุณาเลือก --</option>
                <option value="pdf">PDF</option>
                <option value="excel">Excel</option>
            </select>
            <div class="action-buttons">
                <button id="downloadButton" disabled><i class="fas fa-download"></i> ดาวน์โหลด</button>
                <button id="emailButton" disabled><i class="fas fa-envelope"></i> อีเมล</button>
                <button id="messengerButton" disabled><i class="fab fa-facebook-messenger"></i> Messenger</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main');
    const toggleBtn = document.getElementById('toggle-btn');

    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('hidden');
            mainContent.classList.toggle('full-width');
        });
    }

    if (window.innerWidth <= 768) {
        if (sidebar) sidebar.classList.add('hidden');
        if (mainContent) mainContent.classList.add('full-width');
    }

    // --- Share Modal Functionality (เพิ่มกลับเข้ามา) ---
    const shareButton = document.getElementById('shareButton');
    const modal = document.getElementById('shareModal');
    
    if (shareButton && modal) {
        const fileSelect = modal.querySelector('#fileSelect');
        const downloadBtn = modal.querySelector('#downloadButton');
        const emailBtn = modal.querySelector('#emailButton');
        const messengerBtn = modal.querySelector('#messengerButton');
        const actionButtons = [downloadBtn, emailBtn, messengerBtn];
        
        window.openModal = () => modal.style.display = 'flex';
        window.closeModal = () => {
            modal.style.display = 'none';
            if(fileSelect) fileSelect.value = '';
            toggleActionButtons(true);
        };

        shareButton.addEventListener('click', openModal);
        modal.querySelector('.close-button').addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
        
        const toggleActionButtons = (disabled) => {
            actionButtons.forEach(button => {
                if(button) button.disabled = disabled;
            });
        };

        if(fileSelect) {
            fileSelect.addEventListener('change', function() {
                toggleActionButtons(this.value === '');
            });
        }

        const currentParams = new URLSearchParams(window.location.search);
        const reportTitle = "รายงานการขาย";
        const exportFileName = 'orders_export.php'; 

        if(downloadBtn) {
            downloadBtn.addEventListener('click', function() {
                const fileType = fileSelect.value;
                if (!fileType) return;
                const exportUrl = `${exportFileName}?format=${fileType}&${currentParams.toString()}`;
                window.open(exportUrl, '_blank');
                closeModal();
            });
        }
        
        if(emailBtn) {
             emailBtn.addEventListener('click', function() {
                const fileType = fileSelect.value;
                if (!fileType) return;
                const exportUrl = `${window.location.origin}/${exportFileName}?format=${fileType}&${currentParams.toString()}`;
                const body = `สวัสดี,\n\nนี่คือลิงก์สำหรับรายงานการขาย:\n${exportUrl}\n\nขอบคุณ`;
                const mailtoLink = `mailto:?subject=${encodeURIComponent(reportTitle)}&body=${encodeURIComponent(body)}`;
                window.location.href = mailtoLink;
                closeModal();
            });
        }
        
        if(messengerBtn) {
            messengerBtn.addEventListener('click', function() {
                const fileType = fileSelect.value;
                if (!fileType) return;
                const appId = 'YOUR_APP_ID';
                if (appId === 'YOUR_APP_ID') {
                    alert('กรุณาตั้งค่า Facebook App ID ในไฟล์โค้ดก่อนใช้งานฟังก์ชันนี้');
                    return;
                }
                const exportUrl = encodeURIComponent(`${window.location.origin}/${exportFileName}?format=${fileType}&${currentParams.toString()}`);
                const messengerLink = `https://www.facebook.com/dialog/send?link=${exportUrl}&app_id=${appId}&redirect_uri=${encodeURIComponent(window.location.href)}`;
                window.open(messengerLink, '_blank');
                closeModal();
            });
        }
    }
});
</script>

</body>
</html>