<?php
session_start();
require_once "db.php";

// --- รับค่าจากฟอร์ม ---
$selected_date = $_POST['attend_date'] ?? date('Y-m-d');
$selected_emp = $_POST['selected_emp'] ?? '';
// รับค่าวันที่เริ่มต้นและสิ้นสุด (ถ้าไม่มีให้เป็นค่าว่าง)
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
<html>
<head>
<meta charset="UTF-8">
<title>เช็คชื่อพนักงาน</title>
<link rel="stylesheet" href="../css/attendances.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<header>
    <img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo"/>
    <h1>เช็คชื่อพนักงาน</h1>
    <a href="employees.php" class="home-button">กลับ</a>
    <a href="employee_payments.php" class="home-button">คำนวณเงินเดือน</a>
</header>

<div class="container">
    <h2>เช็คชื่อพนักงาน (เช้า/บ่าย)</h2>
    <form method="POST">
        <div class="date-selector">
            <label>วันที่: <input type="date" name="attend_date" value="<?= $selected_date ?>" onchange="this.form.submit()" required></label>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ลำดับ</th> <th>ชื่อ-สกุล</th> <th>เช้า</th> <th>บ่าย</th>
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
                    <td class="status-group">
                        <?php foreach($status_options as $key=>$label): ?>
                        <button type="button" class="status-btn <?= $key ?> <?= $morning==$key?'selected':'' ?>" onclick="selectStatus('<?= $empId ?>','morning','<?= $key ?>',this)"><?= $label ?></button>
                        <?php endforeach; ?>
                        <input type="hidden" name="status[<?= $empId ?>][morning]" id="status-<?= $empId ?>-morning" value="<?= $morning ?>">
                    </td>
                    <td class="status-group">
                        <?php foreach($status_options as $key=>$label): ?>
                        <button type="button" class="status-btn <?= $key ?> <?= $afternoon==$key?'selected':'' ?>" onclick="selectStatus('<?= $empId ?>','afternoon','<?= $key ?>',this)"><?= $label ?></button>
                        <?php endforeach; ?>
                        <input type="hidden" name="status[<?= $empId ?>][afternoon]" id="status-<?= $empId ?>-afternoon" value="<?= $afternoon ?>">
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="4" style="text-align:center;">ไม่มีข้อมูลพนักงาน</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <button type="submit" name="save_attendance" class="save-btn">บันทึกการเช็คชื่อ</button>
    </form>
</div>

<hr>

<div class="container">
    <h2>ดูประวัติพนักงาน</h2>
    <form method="POST">
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
        <label>ตั้งแต่:
            <input type="date" name="start_date" value="<?= $start_date ?>">
        </label>
        <label>ถึง:
            <input type="date" name="end_date" value="<?= $end_date ?>">
        </label>
        <button type="submit" class="save-btn">ดูประวัติ</button>
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

        // สรุปรวม (ไม่ใช่รายเดือนแล้ว)
        $stmt = $conn->prepare("
            SELECT
                    COUNT(CASE WHEN morning='present' AND afternoon='present' THEN 1 END) AS full_day,
                    COUNT(CASE WHEN (morning='present' AND afternoon!='present') OR (morning!='present' AND afternoon='present') THEN 1 END) AS half_day,
                    COUNT(CASE WHEN morning='late' OR afternoon='late' THEN 1 END) AS late_day,
                    COUNT(CASE WHEN morning='absent' OR afternoon='absent' THEN 1 END) AS absent_day,
                    COUNT(CASE WHEN morning='leave' OR afternoon='leave' THEN 1 END) AS leave_day
            FROM attendances
            WHERE employee_id=? $date_condition
        ");
        $stmt->bind_param(...$params);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        ?>
        <h3>สรุปการเช็คชื่อ <?= (!empty($start_date)) ? " (ตั้งแต่ ".date('d/m/Y', strtotime($start_date))." ถึง ".date('d/m/Y', strtotime($end_date)).")" : "(ทั้งหมด)" ?></h3>
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
        <h3>ประวัติรายวัน</h3>
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
            <tr><td colspan="3" style="text-align: center;">ไม่พบประวัติในช่วงวันที่ที่เลือก</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if(isset($_SESSION['alert'])): ?>
<script>
    Swal.fire({
        icon: '<?= $_SESSION['alert']['type'] ?>',
        title: '<?= $_SESSION['alert']['message'] ?>',
        showConfirmButton: false,
        timer: 1500
    });
</script>
<?php unset($_SESSION['alert']); endif; ?>

<script>
function selectStatus(empId, period, status, buttonElement) {
    document.getElementById(`status-${empId}-${period}`).value = status;
    const buttonGroup = buttonElement.parentElement.querySelectorAll('.status-btn');
    buttonGroup.forEach(btn => btn.classList.remove('selected'));
    buttonElement.classList.add('selected');
}
</script>

</body>
</html>