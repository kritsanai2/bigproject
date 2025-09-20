<?php
session_start();
require_once "db.php";  // เชื่อมต่อฐานข้อมูล

// =================== PhpSpreadsheet ===================
require 'vendor/autoload.php'; // ต้องติดตั้ง PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// =================== รับค่าปีและรูปแบบ ===================
$selected_year = $_GET['year'] ?? date('Y');
$format = $_GET['format'] ?? 'pdf'; // pdf หรือ excel

// =================== ฟังก์ชันแปลงเลขเดือนเป็นชื่อเดือน ===================
function thai_month($month){
    $months = [
        1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
        5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
        9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
    ];
    return $months[$month] ?? $month;
}

// =================== ดึงข้อมูลรายเดือน ===================
$monthlyData = [];
for($m=1;$m<=12;$m++){
    $monthlyData[$m] = 0;
}

$sql = "SELECT MONTH(pay_month) AS m, SUM(amount) AS total
        FROM employee_payments
        WHERE YEAR(pay_month) = $selected_year
        GROUP BY MONTH(pay_month)";
$res = $conn->query($sql);
while($r = $res->fetch_assoc()){
    $monthlyData[$r['m']] = (float)$r['total'];
}

// =================== PDF ===================
if($format == 'pdf'){
    require('fpdf/fpdf.php'); // ต้องมีโฟลเดอร์ fpdf

    $pdf = new FPDF('P','mm','A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,"รายงานเงินเดือนรวมบริษัท ประจำปี $selected_year",0,1,'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(50,10,'เดือน',1);
    $pdf->Cell(50,10,'เงินเดือนรวม (บาท)',1);
    $pdf->Ln();

    $pdf->SetFont('Arial','',12);
    foreach($monthlyData as $m => $total){
        $pdf->Cell(50,10,thai_month($m),1);
        $pdf->Cell(50,10,number_format($total,2),1);
        $pdf->Ln();
    }

    $pdf->Output("I","รายงานเงินเดือน_$selected_year.pdf");
    exit;
}

// =================== Excel ===================
if($format == 'excel'){
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("รายงานเงินเดือน $selected_year");

    // Header
    $sheet->setCellValue('A1', 'เดือน');
    $sheet->setCellValue('B1', 'เงินเดือนรวม (บาท)');

    $row = 2;
    foreach($monthlyData as $m => $total){
        $sheet->setCellValue("A$row", thai_month($m));
        $sheet->setCellValue("B$row", $total);
        $row++;
    }

    // ส่งออกไฟล์ Excel
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment;filename=\"รายงานเงินเดือน_$selected_year.xlsx\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
