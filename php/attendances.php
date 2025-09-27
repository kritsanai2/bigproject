<?php
session_start();
require_once "db.php";
require_once __DIR__ . '/includes/auth.php';

// --- รับค่าจากฟอร์ม ---
$selected_date = $_POST['attend_date'] ?? date('Y-m-d');
$selected_emp = $_POST['selected_emp'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';

// สถานะที่อนุญาต
$status_options_keys = ['present','late','absent','leave'];
$status_options = ['present'=>'มา','late'=>'สาย','absent'=>'ขาด','leave'=>'ลา'];

// บันทึกเช็คชื่อเช้า/บ่าย
if(isset($_POST['save_attendance'])){
    foreach($_POST['status'] as $employee_id => $status_array){
        $morning_status = $status_array['morning'] ?? 'present';
        $afternoon_status = $status_array['afternoon'] ?? 'present';
        if(!in_array($morning_status,$status_options_keys)) $morning_status='present';
        if(!in_array($afternoon_status,$status_options_keys)) $afternoon_status='present';

        $stmt = $conn->prepare("
            INSERT INTO attendances (employee_id, attend_date, morning, afternoon)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE morning=?, afternoon=?
        ");
        $stmt->bind_param("ssssss", $employee_id, $selected_date, $morning_status, $afternoon_status, $morning_status, $afternoon_status);
        $stmt->execute();
    }
    $_SESSION['alert'] = ['type' => 'success', 'message' => 'บันทึกการเช็คชื่อเรียบร้อย'];
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// ดึงข้อมูลพนักงานที่ยังใช้งานอยู่
$emps = $conn->query("SELECT * FROM employees WHERE status=1 ORDER BY employee_id ASC");

// ดึงข้อมูลเช็คชื่อของวันที่เลือก
$attendance_data = [];
$res = $conn->query("SELECT * FROM attendances WHERE attend_date='{$selected_date}'");
while($row = $res->fetch_assoc()){
    $attendance_data[$row['employee_id']] = ['morning'=>$row['morning'],'afternoon'=>$row['afternoon']];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>เช็คชื่อพนักงาน</title>
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
        --info: #9b59b6;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'Sarabun', sans-serif;
        background-color: var(--light-teal-bg);
        color: var(--text-color);
        padding: 20px;
    }
    .container-wrapper {
        max-width: 1400px; margin: 0 auto; background: var(--white);
        border-radius: 20px; box-shadow: 0 15px 30px rgba(0,0,0,0.1); padding: 30px 40px;
    }
    header {
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;
        border-bottom: 2px solid var(--primary-color); padding-bottom: 20px; margin-bottom: 30px; gap: 1rem;
    }
    .logo { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 3px solid var(--gold-accent); }
    header h1 {
        font-family: 'Playfair Display', serif; font-size: 2.5rem; color: var(--navy-blue);
        margin: 0; display: flex; align-items: center; gap: 1rem;
    }
    .header-buttons { display: flex; gap: 1rem; }
    .home-button {
        text-decoration: none; background-color: var(--primary-color); color: var(--white);
        padding: 10px 25px; border-radius: 50px; font-weight: 500;
        transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(52, 152, 219, 0.2);
        display: flex; align-items: center; gap: 8px;
    }
    .home-button:hover { background-color: #2980b9; transform: translateY(-3px); box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3); }
    .container {
        background-color: var(--white); padding: 25px; border-radius: 12px;
        margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    .container h2, .container h3 {
        font-size: 1.8rem; color: var(--navy-blue); margin-bottom: 1.5rem;
        padding-bottom: 10px; border-bottom: 1px solid #eee;
    }
    form { display: flex; flex-direction: column; gap: 1rem; }
    .form-row { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
    label { font-weight: 500; }
    input[type="date"], select {
        padding: 0.75rem; border: 1px solid var(--gray-border); border-radius: 8px; font-size: 1rem; font-family: 'Sarabun', sans-serif;
    }
    .save-btn {
        padding: 10px 25px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer;
        color: white; background-color: var(--primary-color); display: inline-flex; align-items: center;
        gap: 8px; transition: all 0.2s; align-self: flex-start;
    }
    .save-btn:hover { background-color: #2980b9; transform: translateY(-2px); }
    .center-btn-container { text-align: center; margin-top: 1rem; }
    .center-btn-container .save-btn { align-self: center; }

    table { width: 100%; border-collapse: collapse; }
    thead th {
        background-color: var(--navy-blue); color: var(--white);
        padding: 15px; text-align: center; font-size: 0.9rem;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    thead th:nth-child(2) { text-align: left; }
    tbody td {
        padding: 10px 15px; border-bottom: 1px solid #e0e0e0; color: #333; text-align: center; vertical-align: middle;
    }
    tbody td:nth-child(2) { text-align: left; }
    tbody tr:nth-child(even) { background-color: var(--light-gray); }
    tbody tr:hover { background-color: #d4eaf7; }

    /* === ดีไซน์ใหม่สำหรับปุ่มเช็คชื่อ === */
    .status-group {
        display: flex;
        border: 1px solid var(--gray-border);
        border-radius: 50px; /* ทำให้ขอบมน */
        overflow: hidden; /* ซ่อนส่วนเกินของปุ่มด้านใน */
        width: fit-content;
        margin: auto;
    }
    .status-btn {
        background-color: transparent;
        border: none;
        padding: 8px 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        border-right: 1px solid var(--gray-border);
    }
    .status-btn:last-child {
        border-right: none; /* ปุ่มสุดท้ายไม่มีเส้นกั้น */
    }
    
    /* สีตัวอักษรปกติ */
    .status-btn.present { color: var(--success); }
    .status-btn.late { color: var(--warning); }
    .status-btn.absent { color: var(--danger); }
    .status-btn.leave { color: var(--info); }
    
    /* สไตล์เมื่อถูกเลือก */
    .status-btn.selected {
        color: white !important;
        font-weight: 500;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
    }
    .status-btn.present.selected { background-color: var(--success); }
    .status-btn.late.selected { background-color: var(--warning); color: #212529 !important; }
    .status-btn.absent.selected { background-color: var(--danger); }
    .status-btn.leave.selected { background-color: var(--info); }
</style>
</head>
<body>

<div class="container-wrapper">
    <header>
        <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo"/>
        <h1><i class="fas fa-user-check"></i> เช็คชื่อพนักงาน</h1>
        <div class="header-buttons">
            <a href="employees.php" class="home-button"><i class="fas fa-users"></i> กลับ</a>
            <a href="employee_payments.php" class="home-button"><i class="fas fa-file-invoice-dollar"></i> คำนวณเงินเดือน</a>
        </div>
    </header>

    <div class="container">
        <h2>เช็คชื่อพนักงาน (เช้า/บ่าย)</h2>
        <form method="POST">
            <div class="form-row">
                <label>วันที่: <input type="date" name="attend_date" value="<?= $selected_date ?>" onchange="this.form.submit()" required></label>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th>
                        <th>ชื่อ-สกุล</th> 
                        <th>เช้า</th>
                        <th>บ่าย</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; if($emps->num_rows > 0): $emps->data_seek(0); while($row = $emps->fetch_assoc()):
                    $empId = $row['employee_id'];
                    $morning = $attendance_data[$empId]['morning'] ?? 'present';
                    $afternoon = $attendance_data[$empId]['afternoon'] ?? 'present';
                ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td>
                            <div class="status-group">
                                <?php foreach($status_options as $key=>$label): ?>
                                <button type="button" class="status-btn <?= $key ?> <?= $morning==$key?'selected':'' ?>" onclick="selectStatus('<?= $empId ?>','morning','<?= $key ?>',this)"><?= $label ?></button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="status[<?= $empId ?>][morning]" id="status-<?= $empId ?>-morning" value="<?= $morning ?>">
                        </td>
                        <td>
                             <div class="status-group">
                                <?php foreach($status_options as $key=>$label): ?>
                                <button type="button" class="status-btn <?= $key ?> <?= $afternoon==$key?'selected':'' ?>" onclick="selectStatus('<?= $empId ?>','afternoon','<?= $key ?>',this)"><?= $label ?></button>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="status[<?= $empId ?>][afternoon]" id="status-<?= $empId ?>-afternoon" value="<?= $afternoon ?>">
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="4" style="text-align:center;">ไม่มีข้อมูลพนักงาน</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <div class="center-btn-container">
                <button type="submit" name="save_attendance" class="save-btn"><i class="fas fa-save"></i> บันทึกการเช็คชื่อ</button>
            </div>
        </form>
    </div>

    <div class="container">
        <h2><i class="fas fa-history"></i> ดูประวัติพนักงาน</h2>
        <form method="POST">
            <div class="form-row">
                <label>พนักงาน:
                    <select name="selected_emp">
                        <option value="">-- เลือกพนักงาน --</option>
                        <?php
                        if($emps->num_rows > 0) {
                            $emps->data_seek(0);
                            while($row = $emps->fetch_assoc()): ?>
                            <option value="<?= $row['employee_id'] ?>" <?= $selected_emp==$row['employee_id']?'selected':'' ?>><?= htmlspecialchars($row['full_name']) ?></option>
                        <?php endwhile; } ?>
                    </select>
                </label>
                <label>ตั้งแต่: <input type="date" name="start_date" value="<?= $start_date ?>"></label>
                <label>ถึง: <input type="date" name="end_date" value="<?= $end_date ?>"></label>
                <button type="submit" class="save-btn"><i class="fas fa-search"></i> ดูประวัติ</button>
            </div>
        </form>

        <?php if($selected_emp): ?>
            <?php
            // สร้างเงื่อนไข WHERE สำหรับวันที่
            $date_condition = "";
            $params = ["s", $selected_emp];
            if(!empty($start_date) && !empty($end_date)){
                $date_condition = " AND attend_date BETWEEN ? AND ? ";
                $params[0] .= "ss"; // เพิ่ม type 'string' 2 ตัว
                $params[] = $start_date;
                $params[] = $end_date;
            }

            // สรุปรวม
            $stmt = $conn->prepare("
                SELECT
                        COUNT(CASE WHEN morning='present' AND afternoon='present' THEN 1 END) AS full_day,
                        COUNT(CASE WHEN (morning='present' AND afternoon!='present') OR (morning!='present' AND afternoon='present') THEN 1 END) AS half_day,
                        COUNT(DISTINCT CASE WHEN morning='late' OR afternoon='late' THEN attend_date END) AS late_day,
                        COUNT(CASE WHEN morning='absent' OR afternoon='absent' THEN 1 END) AS absent_day,
                        COUNT(CASE WHEN morning='leave' OR afternoon='leave' THEN 1 END) AS leave_day
                FROM attendances
                WHERE employee_id=? $date_condition
            ");
            $stmt->bind_param(...$params);
            $stmt->execute();
            $summary = $stmt->get_result()->fetch_assoc();
            ?>
            <h3 style="margin-top: 2rem;">สรุปการเช็คชื่อ <?= (!empty($start_date)) ? " (ตั้งแต่ ".date('d/m/Y', strtotime($start_date))." ถึง ".date('d/m/Y', strtotime($end_date)).")" : "(ทั้งหมด)" ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>มาเต็มวัน</th> <th>มาครึ่งวัน</th> <th>สาย</th> <th>ขาด</th> <th>ลา</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= $summary['full_day'] ?? 0 ?></td>
                        <td><?= $summary['half_day'] ?? 0 ?></td>
                        <td><?= $summary['late_day'] ?? 0 ?></td>
                        <td><?= $summary['absent_day'] ?? 0 ?></td>
                        <td><?= $summary['leave_day'] ?? 0 ?></td>
                    </tr>
                </tbody>
            </table>

            <?php
            // ประวัติรายวัน
            $stmt2 = $conn->prepare("SELECT attend_date,morning,afternoon FROM attendances WHERE employee_id=? $date_condition ORDER BY attend_date DESC");
            $stmt2->bind_param(...$params);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            ?>
            <h3 style="margin-top: 2rem;">ประวัติรายวัน</h3>
            <table>
                <thead>
                    <tr>
                        <th>วันที่</th> <th>เช้า</th> <th>บ่าย</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($res2->num_rows > 0): while($row = $res2->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($row['attend_date'])) ?></td>
                    <td><?= $status_options[$row['morning']] ?? '-' ?></td>
                    <td><?= $status_options[$row['afternoon']] ?? '-' ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="3" style="text-align: center; padding: 1rem;">ไม่พบประวัติในช่วงวันที่ที่เลือก</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
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

<script>
function selectStatus(empId, period, status, buttonElement) {
    // 1. Set the hidden input value
    document.getElementById(`status-${empId}-${period}`).value = status;
    
    // 2. Find all buttons within the same group (parent container)
    const buttonGroup = buttonElement.parentElement.querySelectorAll('.status-btn');
    
    // 3. Remove 'selected' class from all buttons in that group
    buttonGroup.forEach(btn => btn.classList.remove('selected'));
    
    // 4. Add 'selected' class to the clicked button
    buttonElement.classList.add('selected');
}
</script>

</body>
</html>