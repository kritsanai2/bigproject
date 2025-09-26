<?php
session_start();
require_once "db.php"; 

// กำหนดค่าแรงต่อวัน
$full_rate = 300;
$half_rate = 150;

$calculated_data = [];
$selected_month = $_POST['month'] ?? date('Y-m');

// --- คำนวณเงินเดือน ---
if(isset($_POST['calculate'])){
    $stmt = $conn->prepare("
        SELECT 
            e.employee_id,
            e.full_name,
            SUM(CASE WHEN a.morning='present' AND a.afternoon='present' THEN 1 ELSE 0 END) AS full_days,
            SUM(CASE WHEN (a.morning='present' AND a.afternoon NOT IN ('present', 'late')) OR (a.afternoon='present' AND a.morning NOT IN ('present', 'late')) THEN 1 END) AS half_days,
            COUNT(DISTINCT CASE WHEN a.morning='late' OR a.afternoon='late' THEN a.attend_date END) AS late_days,
            COUNT(CASE WHEN a.morning='leave' AND a.afternoon='leave' THEN 1 END) AS leave_days,
            COUNT(CASE WHEN a.morning='absent' AND a.afternoon='absent' THEN 1 END) AS absent_days
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
            // มาสายยังได้เงินเต็มวัน
            $amount = ((int)$row['full_days'] * $full_rate) + ((int)$row['half_days'] * $half_rate) + ((int)$row['late_days'] * $full_rate);
            $work_days_paid = (float)$row['full_days'] + ((float)$row['half_days'] * 0.5) + (float)$row['late_days'];

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
<title>คำนวณและบันทึกเงินเดือน</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Sarabun', sans-serif;
        background-color: var(--light-teal-bg);
        color: var(--text-color);
        padding: 20px;
    }

    .container-wrapper {
        max-width: 1400px;
        margin: 0 auto;
        background: var(--white);
        border-radius: 20px;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        padding: 30px 40px;
    }

    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        border-bottom: 2px solid var(--primary-color);
        padding-bottom: 20px;
        margin-bottom: 30px;
        gap: 1rem;
    }
    .logo {
        width: 70px; height: 70px; border-radius: 50%;
        object-fit: cover; border: 3px solid var(--gold-accent);
    }
    header h1 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem; color: var(--navy-blue);
        margin: 0; font-weight: 700;
        display: flex; align-items: center; gap: 1rem;
    }
    .home-button {
        text-decoration: none; background-color: var(--primary-color); color: var(--white);
        padding: 10px 25px; border-radius: 50px; font-weight: 500;
        transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(52, 152, 219, 0.2);
        display: flex; align-items: center; gap: 8px;
    }
    .home-button:hover {
        background-color: #2980b9; transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
    }
    
    .container {
        background-color: var(--white);
        padding: 25px; border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    
    .form-controls {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background-color: var(--light-gray);
        border-radius: 12px;
    }
    .form-controls label { font-weight: 500; }
    .form-controls input[type="month"] {
        padding: 10px; border: 1px solid var(--gray-border);
        border-radius: 8px; font-size: 1rem; font-family: 'Sarabun', sans-serif;
    }
    .form-controls button {
        padding: 10px 25px; border: none; border-radius: 8px;
        font-size: 1rem; font-weight: 500; cursor: pointer; color: white;
        display: inline-flex; align-items: center; gap: 8px;
        transition: all 0.2s;
    }
    .form-controls button[name="calculate"] { background-color: var(--primary-color); }
    .form-controls button[name="calculate"]:hover { background-color: #2980b9; transform: translateY(-2px); }
    
    .save-button-container { text-align: center; margin-top: 2rem; }
    .save-button-container button {
        background-color: var(--success); color: white;
        padding: 12px 30px; font-size: 1.1rem; border-radius: 8px;
        border: none; cursor: pointer; font-weight: 500;
        display: inline-flex; align-items: center; gap: 8px;
        transition: all 0.2s;
    }
    .save-button-container button:hover { background-color: #27ae60; transform: translateY(-2px); }

    .table-wrapper { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
        background-color: var(--navy-blue); color: var(--white);
        padding: 15px; text-align: center; font-size: 0.9rem;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    tbody td {
        padding: 15px; border-bottom: 1px solid #e0e0e0; color: #333; text-align: center;
    }
    tbody td:nth-child(3) { text-align: left; } /* Align name to left */
    tbody td:last-child { font-weight: bold; color: var(--primary-color); }
    tbody tr { transition: background-color 0.2s ease; }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }
</style>
</head>
<body>

<div class="container-wrapper">
    <header>
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo"/>
        <h1><i class="fas fa-calculator"></i> คำนวณและบันทึกเงินเดือน</h1>
        <a href="attendances.php" class="home-button"><i class="fas fa-arrow-left"></i> กลับไปหน้าเช็คชื่อ</a>
    </header>

    <div class="container">
        <form method="POST" class="form-controls">
            <label for="month-select">เลือกเดือน:</label> 
            <input type="month" id="month-select" name="month" value="<?= $selected_month ?>">
            <button type="submit" name="calculate"><i class="fas fa-cogs"></i> คำนวณ</button>
        </form>

        <?php if(!empty($calculated_data)): ?>
        <form method="POST">
            <input type="hidden" name="month" value="<?= $selected_month ?>">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ลำดับ</th> <th>รหัส</th> <th>ชื่อ-สกุล</th> <th>เต็มวัน</th> <th>ครึ่งวัน</th> <th>สาย</th> <th>ลา</th> <th>ขาด</th> <th>รวมวันได้เงิน</th> <th>เงินเดือน (บาท)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i=1; $total_amount = 0; foreach($calculated_data as $id => $d): $total_amount += $d['amount']; ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $id ?></td>
                            <td><?= htmlspecialchars($d['full_name']) ?></td>
                            <td><?= $d['full'] ?></td>
                            <td><?= $d['half'] ?></td>
                            <td><?= $d['late'] ?></td>
                            <td><?= $d['leave'] ?></td>
                            <td><?= $d['absent'] ?></td>
                            <td><?= number_format($d['work_days'], 1) ?></td>
                            <td><?= number_format($d['amount'], 2) ?></td>
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
                        <tr style="background-color: var(--navy-blue); color: var(--white); font-weight: bold;">
                            <td colspan="9" style="text-align: right; padding-right: 2rem;">ยอดรวมเงินเดือนทั้งหมด</td>
                            <td><?= number_format($total_amount, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="save-button-container">
                <button type="submit" name="save"><i class="fas fa-save"></i> บันทึกลงฐานข้อมูล</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if(isset($_SESSION['alert'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['alert']['type'] ?>',
        title: '<?= $_SESSION['alert']['message'] ?>',
        showConfirmButton: false,
        timer: 1800,
        toast: true,
        position: 'top-end',
        timerProgressBar: true
    });
</script>
<?php unset($_SESSION['alert']); endif; ?>

</body>
</html>