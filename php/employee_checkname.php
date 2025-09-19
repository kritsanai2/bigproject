<?php
require_once "db.php"; // เรียกไฟล์เชื่อมต่อฐานข้อมูล

$selected_month = $_POST['month'] ?? date('Y-m'); // YYYY-MM
$daily_rate = 300; // วันเต็ม
$half_rate = 150;  // ครึ่งวัน

$start_date = $selected_month . "-01";
$end_date = date("Y-m-t", strtotime($start_date));

// ดึงพนักงาน
$employees = $conn->query("SELECT employee_id, full_name FROM employees WHERE deleted=0 ORDER BY employee_id ASC");

// ดึง attendance ของเดือน
$attendances = $conn->query("
    SELECT employee_id, attend_date, status
    FROM attendances
    WHERE attend_date BETWEEN '$start_date' AND '$end_date'
");

$attendanceData = [];
while($row = $attendances->fetch_assoc()){
    $day = date("d", strtotime($row['attend_date']));
    $attendanceData[$row['employee_id']][$day] = $row['status'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>📝 สรุปการเช็คชื่อและเงินเดือน</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Sarabun', sans-serif; padding:30px; background:#f0f4f8; }
h2 { text-align:center; color:#2d3436; margin-bottom:20px; }

form { text-align:center; margin-bottom:25px; }
input[type="month"] { padding:8px 12px; font-size:16px; border:1px solid #ccc; border-radius:5px; }
button { padding:8px 16px; font-size:16px; border:none; border-radius:5px; background:#00b894; color:#fff; cursor:pointer; transition:0.3s; }
button:hover { background:#019875; }

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-radius: 10px;
    overflow: hidden;
}

th {
    background-color: #00b894;
    color: white;
    padding: 12px;
    text-align: center;
}

td {
    background-color: #dff9fb;
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #c8d6e5;
}

tr:hover td {
    background-color: #74b9ff;
    color: #fff;
    transform: translateY(-2px);
    transition: 0.2s;
}

tfoot td {
    font-weight:bold;
    background:#dfe6e9;
    color:#2d3436;
}

@media screen and (max-width: 1024px){
    table, thead, tbody, th, td, tr { display:block; }
    thead tr { display:none; }
    tr { margin-bottom:15px; border-bottom:2px solid #ccc; }
    td { text-align:right; padding-left:50%; position:relative; }
    td::before {
        content: attr(data-label);
        position:absolute;
        left:15px;
        width:45%;
        padding-left:10px;
        font-weight:bold;
        text-align:left;
    }
}
</style>
</head>
<body>

<h2>📝 สรุปการเช็คชื่อและเงินเดือนพนักงาน</h2>

<form method="post">
    เลือกเดือน: 
    <input type="month" name="month" value="<?= $selected_month ?>">
    <button type="submit">แสดง</button>
</form>

<table>
    <tr>
        <th>ลำดับ</th>
        <th>รหัสพนักงาน</th>
        <th>ชื่อ-สกุล</th>
        <th>วันเต็ม</th>
        <th>ครึ่งวัน</th>
        <th>วันสาย</th>
        <th>วันลา</th>
        <th>วันขาด</th>
        <th>จำนวนวันทำงาน</th>
        <th>เงินเดือน (บาท)</th>
    </tr>
<?php
$no = 1;
$total_salary = 0; // รวมเงินเดือนทุกคน
while($emp = $employees->fetch_assoc()){
    $full=0; $half=0; $late=0; $leave=0; $absent=0;
    if(isset($attendanceData[$emp['employee_id']])){
        foreach($attendanceData[$emp['employee_id']] as $status){
            if($status=='present') $full++;
            elseif($status=='half') $half++;
            elseif($status=='late') $late++;
            elseif($status=='leave') $leave++;
            elseif($status=='absent') $absent++;
        }
    }
    $work_days = $full + ($half*0.5);
    $salary = $work_days * $daily_rate;
    $total_salary += $salary;

    echo "<tr>
        <td data-label='ลำดับ'>{$no}</td>
        <td data-label='รหัสพนักงาน'>{$emp['employee_id']}</td>
        <td data-label='ชื่อ-สกุล'>{$emp['full_name']}</td>
        <td data-label='วันเต็ม'>{$full}</td>
        <td data-label='ครึ่งวัน'>{$half}</td>
        <td data-label='วันสาย'>{$late}</td>
        <td data-label='วันลา'>{$leave}</td>
        <td data-label='วันขาด'>{$absent}</td>
        <td data-label='จำนวนวันทำงาน'>{$work_days}</td>
        <td data-label='เงินเดือน (บาท)'>".number_format($salary,2)."</td>
    </tr>";
    $no++;
}
?>
    <tfoot>
        <tr>
            <td colspan="9">รวมเงินเดือนทั้งหมด</td>
            <td><?= number_format($total_salary,2) ?></td>
        </tr>
    </tfoot>
</table>

</body>
</html>
