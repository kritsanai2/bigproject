<?php
session_start();
require_once "db.php"; 

// กำหนดค่าแรงต่อวัน
$full_rate = 300;
$half_rate = 150;
$late_rate = 300;

$calculated_data = [];
$selected_month = $_GET['month'] ?? date('Y-m');

// --- คำนวณเงินเดือน ---
// FIX: แก้ไข SQL ให้กรอง e.status = 1 และปรับปรุงการนับวัน
$stmt = $conn->prepare("
    SELECT 
        e.employee_id,
        e.full_name,
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
        // FIX: แก้ไขสูตรคำนวณ work_days และ amount ให้ถูกต้อง
        $work_days_paid = (float)$row['full_days'] + ((float)$row['half_days'] * 0.5) + (float)$row['late_days'];
        $amount = ((int)$row['full_days'] * $full_rate) + ((int)$row['half_days'] * $half_rate) + ((int)$row['late_days'] * $full_rate); // มาสายยังได้เงินเต็มวัน

        $calculated_data[$row['employee_id']] = [
            'full_name' => $row['full_name'],
            'full' => (int)$row['full_days'],
            'half' => (int)$row['half_days'],
            'late' => (int)$row['late_days'],
            'leave' => (int)$row['leave_days'],
            'absent' => (int)$row['absent_days'],
            'work_days' => $work_days_paid,
            'amount' => $amount
        ];
    }
}


// --- บันทึกข้อมูล ---
if(isset($_POST['save']) && isset($_POST['data'])){
    $pay_month = date('Y-m-01', strtotime($selected_month));

    $stmt = $conn->prepare("
        INSERT INTO employee_payments (employee_id, pay_month, amount, work_days, daily_rate, full_days, half_days, late_days, leave_days, absent_days)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            amount=VALUES(amount), work_days=VALUES(work_days), daily_rate=VALUES(daily_rate), full_days=VALUES(full_days),
            half_days=VALUES(half_days), late_days=VALUES(late_days), leave_days=VALUES(leave_days),
            absent_days=VALUES(absent_days)
    ");

    foreach($_POST['data'] as $employee_id => $data){
        $stmt->bind_param("ssddiiiiii",
            $employee_id, $pay_month,
            $data['amount'], $data['work_days'], $full_rate, $data['full'],
            $data['half'], $data['late'], $data['leave'], $data['absent']
        );
        $stmt->execute();
    }
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'บันทึกข้อมูลเงินเดือนเรียบร้อย'];
    header("Location: " . $_SERVER['PHP_SELF']."?month=".$selected_month); 
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>รายงานเช็คชื่อและเงินเดือน</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

    .sidebar { width: 250px; background-color: var(--primary-color); color: white; padding: 2rem 1.5rem; height: 100vh; position: fixed; top: 0; left: 0; transition: transform 0.3s ease-in-out; box-shadow: 2px 0 10px rgba(0,0,0,0.1); display: flex; flex-direction: column; align-items: center; z-index: 1000; }
    .sidebar.hidden { transform: translateX(-100%); }
    .logo { width: 100px; height: 100px; border-radius: 50%; border: 4px solid rgba(255, 255, 255, 0.3); object-fit: cover; margin-bottom: 1.5rem; }
    .sidebar h2 { font-size: 1.5rem; margin-bottom: 2rem; font-weight: 700; text-align: center; color: white; }
    .sidebar a { color: white; text-decoration: none; font-size: 1.1rem; padding: 0.8rem 1.5rem; border-radius: 8px; width: 100%; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; transition: all 0.2s ease; }
    .sidebar a:hover { background-color: rgba(255, 255, 255, 0.2); transform: translateX(5px); }
    .sidebar a.active { background-color: rgba(255, 255, 255, 0.3); font-weight: 500; }
    .toggle-btn { position: fixed; top: 1rem; right: 1rem; z-index: 1001; background-color: var(--primary-color); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; font-size: 1.5rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: flex; justify-content: center; align-items: center; }

    .main { margin-left: 250px; padding: 2rem; flex-grow: 1; transition: margin-left 0.3s ease-in-out; width: calc(100% - 250px); }
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
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    
    .filter {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding: 1.5rem;
        background-color: var(--light-gray);
        border-radius: 12px;
    }
    .filter-form { display: flex; gap: 1rem; align-items: center; }
    .filter-form label { font-weight: 500; }
    .filter-form input {
        padding: 10px; border: 1px solid var(--gray-border);
        border-radius: 8px; font-size: 1rem; font-family: 'Sarabun', sans-serif;
    }
    .filter-form button, #shareButton {
        padding: 10px 25px; border: none; border-radius: 8px;
        font-size: 1rem; font-weight: 500; cursor: pointer; color: white;
        display: inline-flex; align-items: center; gap: 8px;
        transition: all 0.2s;
    }
    .filter-form button { background-color: var(--primary-color); }
    .filter-form button:hover { background-color: #2980b9; transform: translateY(-2px); }
    #shareButton { background-color: var(--info); }
    #shareButton:hover { background-color: #8e44ad; transform: translateY(-2px); }
    
    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
        background-color: var(--navy-blue); color: var(--white);
        padding: 15px; text-align: center; font-size: 0.9rem;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    tbody td {
        padding: 15px; border-bottom: 1px solid #e0e0e0;
        color: #333; text-align: center;
    }
    tbody td:nth-child(3) { text-align: left; }
    tbody td:last-child { font-weight: bold; color: var(--primary-color); }
    tbody tr { transition: background-color 0.2s ease; }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }
    tfoot td { font-weight: bold; background-color: var(--light-gray); }
    tfoot td:last-child { color: var(--danger); font-size: 1.2rem; }

    .save-button-container { text-align: center; margin-top: 2rem; }
    .save-button-container button {
        background-color: var(--success); color: white;
        padding: 12px 30px; font-size: 1.1rem; border-radius: 8px;
        border: none; cursor: pointer; font-weight: 500;
        display: inline-flex; align-items: center; gap: 8px;
        transition: all 0.2s;
    }
    .save-button-container button:hover { background-color: #27ae60; transform: translateY(-2px); }
    
</style>
</head>
<body>

<button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>

<div class="sidebar" id="sidebar">
  <img src="../img/da.jfif" alt="โลโก้" class="logo">
  <h2>ข้อมูลเงินเดือน</h2>
  <a href="dashboard.php"><i class="fas fa-home"></i>&nbsp; <span>หน้าหลัก</span></a>
  <a href="employee_dashboard.php" class="active"><i class="fas fa-users"></i>&nbsp; <span>รายงานเงินเดือน</span></a>
  <a href="employee_graphs.php"><i class="fas fa-chart-pie"></i>&nbsp; <span>รายงานกราฟ</span></a>
</div>

<div class="main" id="main">
    <div class="header-main">
        <h1><i class="fas fa-file-invoice-dollar"></i>&nbsp; รายงานเงินเดือนพนักงาน</h1>
    </div>
    
    <div class="container">
        <form method="POST">
             <div class="filter">
                <div class="filter-form">
                    <label>เลือกเดือน:</label>
                    <input type="month" name="month" value="<?= $selected_month ?>" onchange="this.form.submit()">
                </div>
                <button type="button" id="shareButton"><i class="fas fa-share-alt"></i>&nbsp; แชร์หน้านี้</button>
             </div>
        </form>

        <?php if(!empty($calculated_data)): ?>
        <form method="POST">
            <input type="hidden" name="month" value="<?= $selected_month ?>">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ลำดับ</th>
                            <th>รหัส</th>
                            <th>ชื่อ-สกุล</th>
                            <th>เต็มวัน</th>
                            <th>ครึ่งวัน</th>
                            <th>สาย</th>
                            <th>ลา</th>
                            <th>ขาด</th>
                            <th>รวมวันทำงาน</th>
                            <th>เงินเดือน (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    $totals = ['full' => 0, 'half' => 0, 'late' => 0, 'leave' => 0, 'absent' => 0, 'work_days' => 0, 'amount' => 0];
                    foreach($calculated_data as $id => $d):
                        foreach($totals as $key => &$value) {
                            $value += $d[$key];
                        }
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
                        <input type="hidden" name="data[<?= $id ?>][full]" value="<?= $d['full'] ?>">
                        <input type="hidden" name="data[<?= $id ?>][half]" value="<?= $d['half'] ?>">
                        <input type="hidden" name="data[<?= $id ?>][late]" value="<?= $d['late'] ?>">
                        <input type="hidden" name="data[<?= $id ?>][leave]" value="<?= $d['leave'] ?>">
                        <input type="hidden" name="data[<?= $id ?>][absent]" value="<?= $d['absent'] ?>">
                        <input type="hidden" name="data[<?= $id ?>][work_days]" value="<?= $d['work_days'] ?>">
                        <input type="hidden" name="data[<?= $id ?>][amount]" value="<?= $d['amount'] ?>">
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
            <div class="save-button-container">
                <button type="submit" name="save"><i class="fas fa-save"></i> บันทึกลงฐานข้อมูล</button>
            </div>
        </form>
        <?php else: ?>
        <p style="text-align: center; color: #7f8c8d; padding: 2rem;">กรุณาเลือกเดือนและกดคำนวณเพื่อดูข้อมูล</p>
        <?php endif; ?>
    </div>
</div>

<div id="shareModal" class="modal-overlay">
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // All JS logic remains the same
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
});
</script>

</body>
</html>