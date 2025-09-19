<?php
session_start();
require_once "db.php";

$selected_date = $_POST['attend_date'] ?? date('Y-m-d');
$selected_emp = $_POST['selected_emp'] ?? '';

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

        // INSERT หรือ UPDATE แบบปลอดภัย
        $stmt = $conn->prepare("
            INSERT INTO attendances (employee_id, attend_date, morning, afternoon)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE morning=?, afternoon=?
        ");
        $stmt->bind_param("ssssss", $employee_id, $selected_date, $morning_status, $afternoon_status, $morning_status, $afternoon_status);
        $stmt->execute();
    }
    echo "<div style='color:green;margin-bottom:10px;'>บันทึกการเช็คชื่อเรียบร้อย</div>";
}

// ดึงข้อมูลพนักงาน
$emps = $conn->query("SELECT * FROM employees WHERE deleted=0 ORDER BY full_name ASC");

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
</head>
<body>

<header>
<img src="../img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo"/>
<h1>เช็คชื่อพนักงาน</h1>
<a href="employees.php" class="home-button">กลับ</a>
<a href="employee_payments.php" class="home-button">คำนวน</a>
</header>

<h2>เช็คชื่อพนักงาน (เช้า/บ่าย)</h2>
<form method="POST">
<label>วันที่: <input type="date" name="attend_date" value="<?= $selected_date ?>" required></label>
<table>
<tr>
<th>ลำดับ</th>
<th>ชื่อ-สกุล</th>
<th>เช้า</th>
<th>บ่าย</th>
</tr>
<?php $i=1; while($row = $emps->fetch_assoc()):
    $empId = $row['employee_id'];
    $morning = $attendance_data[$empId]['morning'] ?? '';
    $afternoon = $attendance_data[$empId]['afternoon'] ?? '';
?>
<tr>
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($row['full_name']) ?></td>
<td>
<?php foreach($status_options as $key=>$label): ?>
<button type="button" class="status-btn <?= $key ?> <?= $morning==$key?'selected':'' ?>" onclick="selectStatus('<?= $empId ?>','morning','<?= $key ?>',this)"><?= $label ?></button>
<?php endforeach; ?>
<input type="hidden" name="status[<?= $empId ?>][morning]" id="status-<?= $empId ?>-morning" value="<?= $morning ?>">
</td>
<td>
<?php foreach($status_options as $key=>$label): ?>
<button type="button" class="status-btn <?= $key ?> <?= $afternoon==$key?'selected':'' ?>" onclick="selectStatus('<?= $empId ?>','afternoon','<?= $key ?>',this)"><?= $label ?></button>
<?php endforeach; ?>
<input type="hidden" name="status[<?= $empId ?>][afternoon]" id="status-<?= $empId ?>-afternoon" value="<?= $afternoon ?>">
</td>
</tr>
<?php endwhile; ?>
</table>
<button type="submit" name="save_attendance" class="save-btn">บันทึกเช็คชื่อ</button>
</form>

<hr>

<h2>ดูประวัติพนักงาน</h2>
<form method="POST">
<label>พนักงาน:
<select name="selected_emp">
<option value="">-- เลือกพนักงาน --</option>
<?php
$emps->data_seek(0);
while($row = $emps->fetch_assoc()): ?>
<option value="<?= $row['employee_id'] ?>" <?= $selected_emp==$row['employee_id']?'selected':'' ?>><?= htmlspecialchars($row['full_name']) ?></option>
<?php endwhile; ?>
</select>
</label>
<button type="submit" class="save-btn">ดูประวัติ</button>
</form>

<?php
if($selected_emp){
    // สรุปรายเดือน (ใช้ DISTINCT เพื่อนับไม่ซ้ำวัน)
    $stmt = $conn->prepare("
        SELECT MONTH(attend_date) AS month,
            COUNT(DISTINCT CASE WHEN morning='present' THEN attend_date END) AS morning_present,
            COUNT(DISTINCT CASE WHEN afternoon='present' THEN attend_date END) AS afternoon_present,
            COUNT(DISTINCT CASE WHEN morning='late' THEN attend_date END) AS morning_late,
            COUNT(DISTINCT CASE WHEN afternoon='late' THEN attend_date END) AS afternoon_late,
            COUNT(DISTINCT CASE WHEN morning='absent' THEN attend_date END) AS morning_absent,
            COUNT(DISTINCT CASE WHEN afternoon='absent' THEN attend_date END) AS afternoon_absent,
            COUNT(DISTINCT CASE WHEN morning='leave' THEN attend_date END) AS morning_leave,
            COUNT(DISTINCT CASE WHEN afternoon='leave' THEN attend_date END) AS afternoon_leave
        FROM attendances
        WHERE employee_id=?
        GROUP BY MONTH(attend_date)
        ORDER BY MONTH(attend_date)
    ");
    $stmt->bind_param("s",$selected_emp);
    $stmt->execute();
    $res = $stmt->get_result();
?>
<h3>สรุปการเช็คชื่อ (รายเดือน)</h3>
<table>
<tr>
<th>เดือน</th>
<th>มา (เช้า)</th>
<th>มา (บ่าย)</th>
<th>สาย (เช้า)</th>
<th>สาย (บ่าย)</th>
<th>ขาด (เช้า)</th>
<th>ขาด (บ่าย)</th>
<th>ลา (เช้า)</th>
<th>ลา (บ่าย)</th>
</tr>
<?php while($row = $res->fetch_assoc()): ?>
<tr>
<td><?= $row['month'] ?></td>
<td><?= $row['morning_present'] ?></td>
<td><?= $row['afternoon_present'] ?></td>
<td><?= $row['morning_late'] ?></td>
<td><?= $row['afternoon_late'] ?></td>
<td><?= $row['morning_absent'] ?></td>
<td><?= $row['afternoon_absent'] ?></td>
<td><?= $row['morning_leave'] ?></td>
<td><?= $row['afternoon_leave'] ?></td>
</tr>
<?php endwhile; ?>
</table>

<?php
    // ประวัติรายวัน
    $stmt2 = $conn->prepare("SELECT attend_date,morning,afternoon FROM attendances WHERE employee_id=? ORDER BY attend_date");
    $stmt2->bind_param("s",$selected_emp);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
?>
<h3>ประวัติรายวัน</h3>
<table>
<tr>
<th>วันที่</th>
<th>เช้า</th>
<th>บ่าย</th>
</tr>
<?php while($row = $res2->fetch_assoc()): ?>
<tr>
<td><?= $row['attend_date'] ?></td>
<td><?= $status_options[$row['morning']] ?? '-' ?></td>
<td><?= $status_options[$row['afternoon']] ?? '-' ?></td>
</tr>
<?php endwhile; ?>
</table>
<?php } ?>

<script src="../attendances.js"></script>
</body>
</html>
