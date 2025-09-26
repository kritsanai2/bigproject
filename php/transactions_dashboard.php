<?php
session_start();
require_once "db.php"; 

// ฟังก์ชันแปลงประเภทเป็นไทย
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}

// กรองประเภท
$type_filter = $_GET['type'] ?? '';

$sql = "SELECT t.*, od.product_id, p.product_name
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id";

if ($type_filter) {
    $sql .= " WHERE t.transaction_type = ?";
}
$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";

$stmt = $conn->prepare($sql);
if ($type_filter) {
    $stmt->bind_param("s", $type_filter);
}
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>รายงานรายรับ-รายจ่าย</title>
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
    body {
        font-family: 'Sarabun', sans-serif;
        background-color: var(--light-teal-bg);
        color: var(--text-color);
        display: flex;
    }
    
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
    
    .filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;}
    .filter-bar h2 {
        font-size: 1.8rem;
        color: var(--dark-teal, var(--navy-blue));
        margin: 0;
    }
    #shareButton {
        padding: 0.7rem 1.5rem; border: none; border-radius: 8px;
        font-weight: 500; cursor: pointer; color: white; font-size: 1rem;
        background-color: var(--info); transition: all 0.2s ease;
    }
    #shareButton:hover { background-color: #8e44ad; transform: translateY(-2px); }
    
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
    tbody tr { transition: background-color 0.2s ease; }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }
    td.income { color: var(--success); font-weight: bold; }
    td.expense { color: var(--danger); font-weight: bold; }
    td.amount { text-align: right; }
    td.center { text-align: center; }

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
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo">
        <h2>รายรับ-รายจ่าย</h2>
    </div>
    <a href="dashboard.php"><i class="fas fa-home fa-fw"></i>&nbsp; <span>กลับ</span></a>
    <a href="transactions_dashboard.php?type=" class="<?=($type_filter=='')?'active':''?>"><i class="fas fa-list fa-fw"></i>&nbsp; <span>รายการทั้งหมด</span></a>
    <a href="transactions_dashboard.php?type=income" class="<?=($type_filter=='income')?'active':''?>"><i class="fas fa-arrow-down fa-fw"></i>&nbsp; <span>รายรับ</span></a>
    <a href="transactions_dashboard.php?type=expense" class="<?=($type_filter=='expense')?'active':''?>"><i class="fas fa-arrow-up fa-fw"></i>&nbsp; <span>รายจ่าย</span></a>
    <a href="transactions_graphs.php"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-receipt"></i>&nbsp; รายงานรายรับ-รายจ่าย</h1>
    </div>
    
    <div class="container">
        <div class="filter-bar">
            <h2><i class="fas fa-table"></i>&nbsp;ตารางข้อมูล (<?= $type_filter ? thai_type($type_filter) : 'ทั้งหมด' ?>)</h2>
            <button type="button" id="shareButton"><i class="fas fa-share-alt"></i>&nbsp; ส่งออกรายงาน</button>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>รหัส</th>
                        <th>วันที่</th>
                        <th>ประเภท</th>
                        <th>จำนวนเงิน (บาท)</th>
                        <th>รายละเอียด</th>
                        <th>อ้างอิงออเดอร์</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result && $result->num_rows > 0) {
                    $rows = $result->fetch_all(MYSQLI_ASSOC);
                    $i = count($rows);
                    foreach($rows as $row):
                        $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : ($row['product_name'] ?? 'รายรับจากออเดอร์');
                        $order_id_display = $row['order_id'] ?? '-';
                ?>
                    <tr>
                        <td class="center"><?= $i-- ?></td>
                        <td class="center"><?= $row['transaction_id'] ?></td>
                        <td class="center"><?= date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543) ?></td>
                        <td class="center <?= $row['transaction_type'] ?>"><?= $row['transaction_type']=='income' ? '<i class="fas fa-plus-circle"></i> ' : '<i class="fas fa-minus-circle"></i> ' ?><?= thai_type($row['transaction_type']) ?></td>
                        <td class="amount <?= $row['transaction_type'] ?>"><?= number_format($row['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($desc) ?></td>
                        <td class="center"><?= htmlspecialchars($order_id_display) ?></td>
                    </tr>
                <?php 
                    endforeach;
                } else {
                    echo '<tr><td colspan="7" style="text-align:center; padding: 2rem;">ไม่พบข้อมูล</td></tr>';
                }
                ?>
                </tbody>
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
    if (window.matchMedia('(max-width: 768px)').matches) {
        sidebar.classList.add('hidden');
        main.classList.add('full-width');
    }

    const shareButton = document.getElementById('shareButton');
    const modal = document.getElementById('shareModal');
    const fileSelect = document.getElementById('fileSelect');
    const downloadBtn = document.getElementById('downloadButton');
    const emailBtn = document.getElementById('emailButton');
    const messengerBtn = document.getElementById('messengerButton');
    const actionButtons = [downloadBtn, emailBtn, messengerBtn];

    window.openModal = () => modal.style.display = 'flex';
    window.closeModal = () => {
        modal.style.display = 'none';
        fileSelect.value = '';
        toggleActionButtons(true);
    }

    shareButton.addEventListener('click', openModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) { closeModal(); }
    });

    function toggleActionButtons(disabled) {
        actionButtons.forEach(button => button.disabled = disabled);
    }

    fileSelect.addEventListener('change', function() {
        toggleActionButtons(this.value === '');
    });

    const currentParams = new URLSearchParams(window.location.search);
    const reportTitle = "รายงานรายรับ-รายจ่าย";

    downloadBtn.addEventListener('click', function() {
        const fileType = fileSelect.value;
        if (!fileType) return;
        const exportUrl = `transactions_export.php?format=${fileType}&${currentParams.toString()}`;
        window.open(exportUrl, '_blank');
        closeModal();
    });

    emailBtn.addEventListener('click', function() {
        const fileType = fileSelect.value;
        if (!fileType) return;
        const exportUrl = `${window.location.origin}${window.location.pathname.replace('transactions_dashboard.php', '')}transactions_export.php?format=${fileType}&${currentParams.toString()}`;
        const body = `สวัสดี,\n\nนี่คือลิงก์สำหรับรายงานรายรับ-รายจ่าย:\n${exportUrl}\n\nขอบคุณ`;
        const mailtoLink = `mailto:?subject=${encodeURIComponent(reportTitle)}&body=${encodeURIComponent(body)}`;
        window.location.href = mailtoLink;
        closeModal();
    });

    messengerBtn.addEventListener('click', function() {
        const fileType = fileSelect.value;
        if (!fileType) return;
        const appId = 'YOUR_APP_ID';
        if (appId === 'YOUR_APP_ID') {
            alert('กรุณาตั้งค่า Facebook App ID ในไฟล์โค้ดก่อนใช้งานฟังก์ชันนี้');
            return;
        }
        const exportUrl = encodeURIComponent(`${window.location.origin}${window.location.pathname.replace('transactions_dashboard.php', '')}transactions_export.php?format=${fileType}&${currentParams.toString()}`);
        const messengerLink = `https://www.facebook.com/dialog/send?link=${exportUrl}&app_id=${appId}&redirect_uri=${encodeURIComponent(window.location.href)}`;
        window.open(messengerLink, '_blank');
        closeModal();
    });
});
</script>

</body>
</html>