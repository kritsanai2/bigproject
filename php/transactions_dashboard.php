<?php
session_start();
require_once "auth.php";
require_once "db.php";

// --- ฟังก์ชันช่วยเหลือ ---
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}

// --- (ปรับปรุง) รับค่า Filter และกำหนดค่าเริ่มต้นอัจฉริยะ ---
$type_filter = $_GET['type'] ?? '';
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
// ถ้าไม่ได้เลือกปี ให้ใช้ปีปัจจุบันเป็นค่าเริ่มต้น
$filter_year  = isset($_GET['year']) && intval($_GET['year']) > 0 ? intval($_GET['year']) : intval(date('Y'));

// --- 1. สร้าง Query สำหรับดึงรายการธุรกรรม (transactions) ---
$sql = "SELECT t.*, od.product_id, p.product_name, o.order_id
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id
        LEFT JOIN orders o ON od.order_id = o.order_id";

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

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$transaction_rows = $result->fetch_all(MYSQLI_ASSOC);


// --- (ปรับปรุง) 2. ดึงข้อมูลเงินเดือนและแปลงให้เป็นรูปแบบเดียวกับ transactions ---
$salary_rows = [];
// จะดึงยอดเงินเดือนก็ต่อเมื่อดูหน้ารวม ('') หรือหน้ารายจ่าย ('expense') เท่านั้น
if ($type_filter === '' || $type_filter === 'expense') {
    $salary_sql = "SELECT total_amount, pay_month FROM salary";
    
    $salary_params = [];
    $salary_types = "";
    $salary_where = [];

    if ($filter_month > 0) {
        $salary_where[] = "MONTH(pay_month) = ?";
        $salary_params[] = $filter_month;
        $salary_types .= "i";
    }
    if ($filter_year > 0) {
        $salary_where[] = "YEAR(pay_month) = ?";
        $salary_params[] = $filter_year;
        $salary_types .= "i";
    }

    if (!empty($salary_where)) {
        $salary_sql .= " WHERE " . implode(" AND ", $salary_where);
    }

    $stmt_salary = $conn->prepare($salary_sql);
    if (!empty($salary_params)) {
        $stmt_salary->bind_param($salary_types, ...$salary_params);
    }
    $stmt_salary->execute();
    $salary_results = $stmt_salary->get_result()->fetch_all(MYSQLI_ASSOC);

    // แปลงข้อมูล salary ให้มีโครงสร้างเหมือน transactions เพื่อนำไปรวมกัน
    foreach ($salary_results as $salary) {
        $salary_rows[] = [
            'transaction_id' => 'S' . date('mY', strtotime($salary['pay_month'])), // สร้าง ID สมมติ
            'transaction_type' => 'expense',
            'amount' => $salary['total_amount'],
            'transaction_date' => $salary['pay_month'],
            'expense_type' => 'เงินเดือนพนักงานเดือน ' . thai_month(date('n', strtotime($salary['pay_month']))),
            'order_id' => null,
            'slip_image' => null,
            'product_name' => null
        ];
    }
}

// --- 3. รวมข้อมูลและจัดเรียงตามวันที่ ---
$all_rows = array_merge($transaction_rows, $salary_rows);
// จัดเรียงข้อมูลทั้งหมดตามวันที่จากใหม่ไปเก่า
usort($all_rows, function($a, $b) {
    return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
});


// --- 4. คำนวณยอดรวมจากข้อมูลที่รวมกันแล้ว ---
$total_income = 0;
$total_expense = 0;
foreach($all_rows as $row){
    if($row['transaction_type'] === 'income'){
        $total_income += $row['amount'];
    } else {
        $total_expense += $row['amount'];
    }
}
$balance = $total_income - $total_expense;


// --- 5. สร้างหัวข้อรายงานแบบ Dynamic ---
$report_title = "รายงาน";
if ($type_filter == 'income') $report_title .= "รายรับ";
elseif ($type_filter == 'expense') $report_title .= "รายจ่าย";
else $report_title .= "รายรับ-รายจ่าย";

if ($filter_month > 0) $report_title .= " เดือน " . thai_month($filter_month);

if ($filter_year > 0) {
    $report_title .= " ปี " . ($filter_year + 543);
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>💧 รายงานรายรับ-รายจ่าย</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
    /* ================================
    Root Variables
    ================================ */
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
        --info: #9b59b6; /* 💜 สีสำหรับปุ่มรูปภาพ */
        --info-blue: #3498db;
    }

    /* ================================
    Reset & Base
    ================================ */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    body {
        font-family: 'Sarabun', sans-serif;
        background-color: var(--light-teal-bg);
        display: flex;
        color: var(--text-color);
    }

    /* ================================
    Sidebar
    ================================ */
    .sidebar {
        width: 250px;
        height: 100vh;
        position: fixed;
        top: 0; left: 0;
        padding: 1.5rem;
        background: linear-gradient(180deg, var(--primary-color), #2980b9);
        color: var(--white);
        box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        z-index: 1000;
        transition: transform 0.3s;
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
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.8rem 1rem;
        margin-bottom: 0.5rem;
        font-size: 1.1rem;
        text-decoration: none;
        color: var(--white);
        border-radius: 8px;
        transition: all 0.2s;
    }
    .sidebar a:hover {
        background-color: rgba(255,255,255,0.15);
        transform: translateX(5px);
    }
    .sidebar a.active { background-color: rgba(0,0,0,0.2); }

    .toggle-btn {
        position: fixed;
        top: 1rem; right: 1rem;
        z-index: 1001;
        background-color: var(--primary-color);
        color: var(--white);
        border: none;
        border-radius: 50%;
        width: 40px; height: 40px;
        font-size: 1.5rem;
        cursor: pointer;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* ================================
    Main Content
    ================================ */
    .main {
        margin-left: 250px;
        padding: 2rem;
        flex-grow: 1;
        width: calc(100% - 250px);
        transition: margin-left 0.3s;
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

    /* ================================
    Containers & Filters
    ================================ */
    .container {
        background-color: var(--white);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
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
    .filter-form select,
    .filter-form button { font-size: 1rem; }
    .filter-form select {
        padding: 0.65rem;
        border-radius: 8px;
        border: 1px solid var(--gray-border);
    }

    /* ================================
    Buttons
    ================================ */
    .action-buttons { display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .action-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.7rem 1.2rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        color: var(--white);
        text-decoration: none;
        transition: all 0.2s;
    }
    .action-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .btn-filter { background-color: var(--primary-color); }
    .btn-pdf    { background-color: var(--danger); }
    .btn-excel  { background-color: var(--success); }
    .btn-email  { background-color: var(--warning); color: #333; }

    /* ================================
    Tables
    ================================ */
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

    td.income  { color: var(--success); font-weight: bold; }
    td.expense { color: var(--danger); font-weight: bold; }
    td.amount  { text-align: right; }
    td.center  { text-align: center; }

    .btn-action-table {
        padding: 0.5rem 1rem; border-radius: 6px; border: none;
        cursor: pointer; font-size: 0.9rem; color: white;
        transition: transform 0.2s ease; white-space: nowrap;
        display: inline-flex; align-items: center; gap: 0.5rem;
    }
    .btn-action-table:hover { transform: translateY(-2px); }
    .btn-preview { background-color: var(--info-blue); }
    .btn-preview:hover { background-color: #2980b9; }


    /* ================================
    Summary Cards
    ================================ */
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 20px;
    }
    .summary-card {
        background-color: var(--white);
        border-radius: 10px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        border-left: 5px solid;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .summary-card .icon { font-size: 2.5rem; }
    .summary-card .info h4 {
        margin: 0;
        font-size: 1rem;
        color: var(--secondary-color);
    }
    .summary-card .info p {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 700;
    }
    .card-income  { border-color: var(--success); }
    .card-income .icon, .card-income .info p { color: var(--success); }
    .card-expense { border-color: var(--danger); }
    .card-expense .icon, .card-expense .info p { color: var(--danger); }
    .card-balance { border-color: var(--primary-color); }
    .card-balance .icon { color: var(--primary-color); }
    .card-balance .info p { color: <?= $balance >= 0 ? 'var(--success)' : 'var(--danger)' ?>; }

    /* ================================
    Modal
    ================================ */
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
        width: 90%; max-width: 500px;
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
       <h2>ระบบจัดการ</h2>
    </div>
    <a href="dashboard.php"><i class="fas fa-home fa-fw"></i>&nbsp; <span>กลับ</span></a>
    <a href="transactions.php"><i class="fas fa-money-bill-wave fa-fw"></i>&nbsp; <span>จัดการรายรับ-รายจ่าย</span></a>
    <a href="transactions_dashboard.php" class="<?= empty($type_filter) ? 'active' : '' ?>"><i class="fas fa-list fa-fw"></i>&nbsp; <span>ทั้งหมด</span></a>
    <a href="transactions_dashboard.php?type=income" class="<?= ($type_filter=='income') ? 'active' : '' ?>"><i class="fas fa-arrow-down fa-fw"></i>&nbsp; <span>รายรับ</span></a>
    <a href="transactions_dashboard.php?type=expense" class="<?= ($type_filter=='expense') ? 'active' : '' ?>"><i class="fas fa-arrow-up fa-fw"></i>&nbsp; <span>รายจ่าย</span></a>
    <a href="transactions_graphs.php"><i class="fas fa-chart-pie fa-fw"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-receipt"></i>&nbsp; รายงานรายรับ-รายจ่าย</h1>
    </div>

    <?php if (empty($type_filter) || $type_filter === 'expense' || $type_filter === 'income'): ?>
    <div class="summary-cards">
        <?php if (empty($type_filter) || $type_filter === 'income'): ?>
        <div class="summary-card card-income">
            <div class="icon"><i class="fas fa-arrow-circle-down"></i></div>
            <div class="info"><h4>ยอดรวมรายรับ</h4><p><?= number_format($total_income, 2) ?></p></div>
        </div>
        <?php endif; ?>

        <?php if (empty($type_filter) || $type_filter === 'expense'): ?>
        <div class="summary-card card-expense">
            <div class="icon"><i class="fas fa-arrow-circle-up"></i></div>
            <div class="info"><h4>ยอดรวมรายจ่าย</h4><p><?= number_format($total_expense, 2) ?></p></div>
        </div>
        <?php endif; ?>
        
        <?php if (empty($type_filter)): ?>
        <div class="summary-card card-balance">
            <div class="icon"><i class="fas fa-wallet"></i></div>
            <div class="info"><h4>ยอดสุทธิ</h4><p><?= number_format($balance, 2) ?></p></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>


    <div class="container">
        <div class="filter-box">
            <form method="get" class="filter-form">
                <input type="hidden" name="type" value="<?= htmlspecialchars($type_filter) ?>">

                <label for="month-select">เดือน :</label>
                <select name="month" id="month-select">
                    <option value="0" <?= ($filter_month == 0) ? 'selected' : '' ?>>ทุกเดือน</option>
                    <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($filter_month == $m) ? 'selected' : '' ?>><?= thai_month($m) ?></option>
                    <?php endfor; ?>
                </select>

                <label for="year-select">ปี :</label>
                <select name="year" id="year-select">
                    <option value="0" <?= ($filter_year == 0) ? 'selected' : '' ?>>ทุกปี</option>
                    <?php
                    $start_year = 2024;
                    $end_year = intval(date('Y')) + 5;
                    for($y = $start_year; $y <= $end_year; $y++):
                    ?>
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
                        <th>ประเภท</th>
                        <th>จำนวนเงิน (บาท)</th>
                        <th>วันที่</th>
                        <th>รายละเอียด</th>
                        <th>รหัสสั่งซื้อ</th>
                        <th>รูปภาพ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_rows)):
                        $i = 1;
                        foreach($all_rows as $row):
                            $desc = $row['transaction_type'] == 'expense'
                            ? ($row['expense_type'] ?? '-')
                            : ($row['product_name'] ?? '-');
                    ?>
                    <tr>
                        <td class="center"><?= $i++ ?></td>
                        <td class="center <?= $row['transaction_type'] ?>"><?= $row['transaction_type']=='income' ? '<i class="fas fa-plus-circle"></i> ' : '<i class="fas fa-minus-circle"></i> ' ?><?= thai_type($row['transaction_type']) ?></td>
                        <td class="amount <?= $row['transaction_type'] ?>"><?= number_format($row['amount'], 2) ?></td>
                        <td class="center"><?= date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543) ?></td>
                        <td style="text-align:left;"><?= htmlspecialchars($desc) ?></td>
                        <td class="center"><?= htmlspecialchars($row['order_id'] ?? '-') ?></td>
                        <td class="center">
                            <?php if (!empty($row['slip_image'])): ?>
                                <button type="button" class="btn-action-table btn-preview" onclick="showImagePreview('<?= htmlspecialchars($row['slip_image']) ?>')">
                                    <i class="fas fa-eye"></i> ดูรูปภาพ
                                </button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                        endforeach;
                    else:
                        echo '<tr><td colspan="7" style="text-align:center; padding: 2rem; color:#7f8c8d;"><i class="fas fa-info-circle"></i> ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td></tr>';
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
            <button class="close-button" id="closeModalBtn">&times;</button>
        </div>
        <div class="modal-body">
            <label>เลือกรูปแบบไฟล์:</label>
            <div style="margin: 0.5rem 0 1.5rem; display: flex; gap: 2rem; flex-wrap: wrap;">
                <label><input type="checkbox" name="file_format" value="pdf" checked> PDF</label>
                <label><input type="checkbox" name="file_format" value="excel"> Excel</label>
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
// ฟังก์ชันสำหรับแสดงรูปภาพใน Modal
function showImagePreview(imagePath) {
    Swal.fire({
        imageUrl: '../' + imagePath,
        imageAlt: 'รูปภาพประกอบรายจ่าย',
        imageHeight: '80vh',
        width: 'auto',
        showConfirmButton: false,
        showCloseButton: true,
        backdrop: `rgba(0,31,63,0.7)`
    });
}

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
    // ตัวแปรสำหรับปุ่มรูปภาพถูกลบออกไปแล้ว
    const pdfButton = document.getElementById('pdfButton');
    const excelButton = document.getElementById('excelButton');
    const emailModalButton = document.getElementById('emailModalButton');
    const emailModal = document.getElementById('emailModal');

    function buildExportUrl(baseUrl) {
        const params = new URLSearchParams(window.location.search);
        return `${baseUrl}?${params.toString()}`;
    }

    // Event Listener สำหรับปุ่มรูปภาพถูกลบออกไปแล้ว
    if (pdfButton) pdfButton.addEventListener('click', () => window.open(buildExportUrl('export_transactions_pdf.php'), '_blank'));
    if (excelButton) excelButton.addEventListener('click', () => window.location.href = buildExportUrl('export_transactions_excel.php'));

    // --- Modal Control ---
    if(emailModal && emailModalButton) {
        const sendEmailButton = document.getElementById('sendEmailButton');
        const recipientEmailInput = document.getElementById('recipientEmail');
        const emailStatus = document.getElementById('emailStatus');
        const closeModalBtn = document.getElementById('closeModalBtn');

        const openModal = () => {
            emailModal.style.display = 'flex';
            recipientEmailInput.value = '';
            emailStatus.innerHTML = '';
            sendEmailButton.disabled = false;
            // รีเซ็ตค่า Checkboxes
            emailModal.querySelector('input[value="pdf"]').checked = true;
            emailModal.querySelector('input[value="excel"]').checked = false;
            // บรรทัดสำหรับรีเซ็ต checkbox รูปภาพถูกลบออกไปแล้ว
        };

        const closeModal = () => { emailModal.style.display = 'none'; };

        emailModalButton.addEventListener('click', openModal);
        closeModalBtn.addEventListener('click', closeModal);
        emailModal.addEventListener('click', (e) => {
            if (e.target === emailModal) closeModal();
        });

        sendEmailButton.addEventListener('click', async function() {
            const email = recipientEmailInput.value.trim();
            if (!email || !/\S+@\S+\.\S+/.test(email)) {
                emailStatus.innerHTML = `<span style="color: var(--danger);">กรุณากรอกอีเมลให้ถูกต้อง</span>`;
                return;
            }

            const selectedFormats = Array.from(document.querySelectorAll('#emailModal input[name="file_format"]:checked')).map(cb => cb.value);
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
                const response = await fetch('send_transactions_email.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Server response was not ok.');

                const result = await response.json();
                if (result.status === 'success') {
                    emailStatus.innerHTML = `<span style="color: var(--success);">${result.message}</span>`;
                    setTimeout(closeModal, 2000);
                } else {
                    emailStatus.innerHTML = `<span style="color: var(--danger);">ผิดพลาด: ${result.message || 'เกิดข้อผิดพลาด'}</span>`;
                    this.disabled = false;
                }
            } catch (error) {
                console.error('Error sending email:', error);
                emailStatus.innerHTML = `<span style="color: var(--danger);">เกิดข้อผิดพลาดในการเชื่อมต่อกับ Server</span>`;
                this.disabled = false;
            }
        });
    }
});
</script>

</body>
</html>