<?php
require __DIR__ . '/../vendor/autoload.php';
require_once "db.php";  // เรียกไฟล์เชื่อมต่อฐานข้อมูล

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;
use Dompdf\Options;

$type = $_GET['type'] ?? 'pdf';  // pdf | excel
$selected_month = $_POST['month'] ?? date('Y-m');
$daily_rate = 300;

// ================== ดึงข้อมูล ==================
$start_date = $selected_month . "-01";
$end_date   = date("Y-m-t", strtotime($start_date));

$employees_result = $conn->query("SELECT employee_id, full_name FROM employees WHERE deleted=0 ORDER BY employee_id ASC");
$attendances_result = $conn->query("
    SELECT employee_id, attend_date, status
    FROM attendances
    WHERE attend_date BETWEEN '$start_date' AND '$end_date'
");

$attendanceData = [];
while($row = $attendances_result->fetch_assoc()){
    $day = date("d", strtotime($row['attend_date']));
    $attendanceData[$row['employee_id']][$day] = $row['status'];
}

$payments_result = $conn->query("SELECT employee_id, work_days, amount FROM employee_payments WHERE pay_month='$selected_month'");
$paymentsData = [];
while($row = $payments_result->fetch_assoc()){
    $paymentsData[$row['employee_id']] = $row;
}

// ================== ประมวลผลข้อมูล ==================
$employees = [];
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
    $salary = $paymentsData[$emp['employee_id']]['amount'] ?? ($work_days * $daily_rate);
    $emp['full_days']   = $full;
    $emp['half_days']   = $half;
    $emp['late_days']   = $late;
    $emp['leave_days']  = $leave;
    $emp['absent_days'] = $absent;
    $emp['work_days']   = $work_days;
    $emp['salary']      = $salary;
    $employees[]        = $emp;
}

// ================== Export Excel ==================
if($type === 'excel'){
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = ['ID','Full Name','Full Days','Half Days','Late Days','Leave Days','Absent Days','Work Days','Salary'];
    $col='A';
    foreach($headers as $h){ 
        $sheet->setCellValue($col.'1', $h); 
        $col++; 
    }

    $rowNum=2;
    foreach($employees as $emp){
        $sheet->setCellValue('A'.$rowNum, $emp['employee_id']);
        $sheet->setCellValue('B'.$rowNum, $emp['full_name']);
        $sheet->setCellValue('C'.$rowNum, $emp['full_days']);
        $sheet->setCellValue('D'.$rowNum, $emp['half_days']);
        $sheet->setCellValue('E'.$rowNum, $emp['late_days']);
        $sheet->setCellValue('F'.$rowNum, $emp['leave_days']);
        $sheet->setCellValue('G'.$rowNum, $emp['absent_days']);
        $sheet->setCellValue('H'.$rowNum, $emp['work_days']);
        $sheet->setCellValue('I'.$rowNum, $emp['salary']);
        $rowNum++;
    }

    // กำหนด header สำหรับดาวน์โหลด
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="employees_payment_'.$selected_month.'.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ================== Export PDF ==================
$fontDir = __DIR__ . '/../fonts/';
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'THSarabunIT๙');  // ตั้งชื่อฟอนต์ที่เราจะใช้
$dompdf = new Dompdf($options);



$html='<style>
@font-face {
    font-family: "THSarabunIT9";
    src: url("'.$fontDir.'THSarabunIT๙.ttf") format("truetype");
}
body{font-family:"THSarabunIT9", Arial; font-size:14pt;}
table{border-collapse:collapse;width:100%; font-size:12pt;}
th,td{border:1px solid #000; padding:6px;}
th{background:#00b894;color:#fff; text-align:center;}
td.left{text-align:left;}
td.right{text-align:right;}
</style>';
$html.='<h3 style="text-align:center;">Employee Payment - '.date('F Y', strtotime($selected_month)).'</h3>';
$html.='<table><tr>
<th>ID</th><th>Full Name</th><th>Full Days</th><th>Half Days</th><th>Late Days</th>
<th>Leave Days</th><th>Absent Days</th><th>Work Days</th><th>Salary</th></tr>';

foreach($employees as $emp){
    $html.='<tr>';
    $html.='<td class="right">'.$emp['employee_id'].'</td>';
    $html.='<td class="left">'.$emp['full_name'].'</td>';
    $html.='<td class="right">'.$emp['full_days'].'</td>';
    $html.='<td class="right">'.$emp['half_days'].'</td>';
    $html.='<td class="right">'.$emp['late_days'].'</td>';
    $html.='<td class="right">'.$emp['leave_days'].'</td>';
    $html.='<td class="right">'.$emp['absent_days'].'</td>';
    $html.='<td class="right">'.$emp['work_days'].'</td>';
    $html.='<td class="right">'.number_format($emp['salary'],2).'</td>';
    $html.='</tr>';
}
$html.='</table>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4','landscape');
$dompdf->render();
$dompdf->stream("employees_payment_{$selected_month}.pdf", ["Attachment"=>1]);
exit;
?>
