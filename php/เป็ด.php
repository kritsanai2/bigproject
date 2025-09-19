<?php
$conn = new mysqli("localhost", "root", "", "bigproject");
$conn->set_charset("utf8mb4");
if($conn->connect_error) die("Connection failed: ".$conn->connect_error);

$selected_month = $_POST['month'] ?? date('Y-m'); // YYYY-MM
$daily_rate = 300; // วันเต็ม
$half_rate = 150;  // ครึ่งวัน

$start_date = $selected_month . "-01";
$end_date = date("Y-m-t", strtotime($start_date));

// ดึงพนักงาน
$employees_result = $conn->query("SELECT employee_id, full_name FROM employees WHERE deleted=0 ORDER BY employee_id ASC");

// ดึง attendance ของเดือน
$attendances_result = $conn->query("
    SELECT employee_id, attend_date, status
    FROM attendances
    WHERE attend_date BETWEEN '$start_date' AND '$end_date'
");

// จัดเก็บ attendance
$attendanceData = [];
while($row = $attendances_result->fetch_assoc()){
    $day = date("d", strtotime($row['attend_date']));
    $attendanceData[$row['employee_id']][$day] = $row['status'];
}

// ดึงข้อมูล employee_payments ของเดือนที่เลือก
$payments_result = $conn->query("
    SELECT employee_id, work_days, amount
    FROM employee_payments
    WHERE pay_month='$selected_month'
");

$paymentsData = [];
while($row = $payments_result->fetch_assoc()){
    $paymentsData[$row['employee_id']] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>รายงานเช็คชื่อและเงินเดือน</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
/* Reset & Font */
* {margin:0; padding:0; box-sizing:border-box; font-family: 'Sarabun', sans-serif;}
body {display:flex; min-height:100vh; background:#f5f5f5;}

/* Sidebar */
.sidebar {
    width: 70px;
    background: linear-gradient(#00b894, #00d2b3);
    color: #fff;
    display: flex;
    flex-direction: column;
    padding: 20px 10px;
    transition: 0.3s;
    overflow: hidden;
    box-shadow: 2px 0 8px rgba(0,0,0,0.1);
    z-index: 10;
}
.sidebar:hover { width: 220px; }
.sidebar h2,
.sidebar a span { opacity: 0; transition: 0.3s; white-space: nowrap; }
.sidebar:hover h2,
.sidebar:hover a span { opacity: 1; }
.sidebar a {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: #fff;
    padding: 12px;
    margin: 5px 0;
    border-radius: 10px;
    transition: all 0.3s;
}
.sidebar a i { min-width: 25px; text-align: center; margin-right: 10px; }
.sidebar a:hover,
.sidebar a.active { background: rgba(255,255,255,0.2); transform: scale(1.05); }
.sidebar .logo {
    width: 60px; height: 60px; border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    object-fit: cover;
    margin: 0 auto 15px;
    display: block;
    transition: transform 0.3s ease;
}
.sidebar .logo:hover { transform: scale(1.1) rotate(5deg); }

/* Main content */
.main {flex:1; padding:20px; overflow-x:auto;}
.main h2 {text-align:center; margin-bottom:20px;}

/* Form / Filter */
form {text-align:center; margin-bottom:20px;}
input[type="month"], select, button {padding:6px 12px; border-radius:5px; border:1px solid #74b9ff; margin:0 5px; font-size:1rem;}
button {border:none; cursor:pointer; background:#00b894; color:#fff; font-weight:600; transition:0.3s;}
button:hover {background:#019ca1;}

/* ตาราง employees */
#reportContent table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-radius: 10px;
    overflow: hidden;
}
#reportContent th {
    background-color: #00b894;
    color: white;
    padding: 12px;
    text-align: center;
}
#reportContent td {
    background-color: #dff9fb;
    padding: 12px;
    text-align: center;
    border-bottom: 1px solid #c8d6e5;
}
#reportContent tr:hover td {
    background-color: #74b9ff;
    color: white;
    transform: translateY(-2px);
    transition: 0.2s;
}

/* ปุ่มส่งออก PDF/Excel */
.btn-export {
    padding: 12px 25px;
    font-size: 1rem;
    border-radius: 20px;
    text-decoration: none;
    font-weight: 600;
    color: #fff;
    display: inline-block;
    transition: all 0.3s ease;
    cursor: pointer;
    margin: 5px;
}
.btn-export.pdf { background-color: #e74c3c; }
.btn-export.pdf:hover { background-color: #c0392b; }
.btn-export.excel { background-color: #27ae60; }
.btn-export.excel:hover { background-color: #1e8449; }

/* Responsive */
@media screen and (max-width:1024px){
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

<div class="sidebar">
    <img src="/projectจบ/img/da.jfif" alt="โลโก้โรงน้ำดื่ม" class="logo" />
    <h2><span>รายการเช็คชื่อ</span></h2>
    <a href="dashboard.php"><span>กลับ</span></a>
    <a href="employee_dashboard.php"><span>รายการเช็คชื่อ</span></a>
    <a href="employee_graphs.php"><span>กราฟ</span></a>
</div>

<div class="main">
<h2>📝 สรุปการเช็คชื่อและเงินเดือนพนักงาน - <?= date('F Y', strtotime($selected_month)) ?></h2>

<!-- Form เลือกเดือน -->
<form method="post" action="">
    เลือกเดือน: 
    <input type="month" name="month" value="<?= $selected_month ?>">
    <button type="submit">แสดงข้อมูล</button>
</form>

<!-- ปุ่มส่งออก PDF/Excel -->
<div class="export-buttons">
    <button type="button" id="exportPdf" class="btn-export pdf">📄 ส่งออก PDF</button>
    <button type="button" id="exportExcel" class="btn-export excel">📊 ส่งออก Excel</button>
</div>

<!-- ตารางรายงาน -->
<div class="main" id="reportContent">
    <table>
        <thead>
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
        </thead>
        <tbody>
            <?php
            $no = 1;
            $total_salary = 0;
            while($emp = $employees_result->fetch_assoc()){
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
                $salary = $paymentsData[$emp['employee_id']]['amount'] 
                          ?? (($full * $daily_rate) + ($half * $half_rate));
                $total_salary += $salary;

                echo "<tr>
                    <td>{$no}</td>
                    <td>{$emp['employee_id']}</td>
                    <td>{$emp['full_name']}</td>
                    <td>{$full}</td>
                    <td>{$half}</td>
                    <td>{$late}</td>
                    <td>{$leave}</td>
                    <td>{$absent}</td>
                    <td>{$work_days}</td>
                    <td>".number_format($salary,2)."</td>
                </tr>";
                $no++;
            }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="9" style="text-align:right; font-weight:bold;">รวมเงินเดือนทั้งหมด</td>
                <td style="font-weight:bold;"><?= number_format($total_salary,2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- ไลบรารีสำหรับ export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
// ส่งออก PDF หน้าเว็บ
document.getElementById("exportPdf").addEventListener("click", function(){
    const element = document.getElementById("reportContent");
    html2pdf()
    .set({
        margin: 0.5,
        filename: 'employee_dashboard.pdf',
        pagebreak: { mode: 'avoid-all' },
        html2canvas: { scale: 2 }
    })
    .from(element)
    .save();
});

// ส่งออก Excel หน้าเว็บ
document.getElementById("exportExcel").addEventListener("click", function(){
    var table = document.querySelector("#reportContent table");
    var wb = XLSX.utils.table_to_book(table, {sheet:"Sheet1"});
    XLSX.writeFile(wb, 'employee_dashboard.xlsx');
});
</script>

</body>
</html>
