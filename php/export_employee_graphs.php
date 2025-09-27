<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path สำหรับ FPDF และฟอนต์
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require '../vendor/autoload.php';
require_once 'db.php'; 

// 1. ฟังก์ชันช่วยแปลเดือน/สร้างหัวข้อ
function thai_month_name($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? 'ไม่ระบุ';
}

// 2. รับค่าจาก POST
// เนื่องจาก JS ส่ง dailyChartImg มา เราต้องรับมันไว้แต่ไม่ต้องนำมาใช้
$dailyChartImg = $_POST['dailyChartImg'] ?? null;
$monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
$yearlyChartImg = $_POST['yearlyChartImg'] ?? null;
$selected_year = (int)($_POST['year'] ?? date('Y'));

if (!$monthlyChartImg || !$yearlyChartImg) {
    http_response_code(400);
    die('Missing chart image data.');
}

// 3. แปลง Base64 เป็นรูปภาพชั่วคราว (รับแค่ Monthly และ Yearly)
$temp_dir = sys_get_temp_dir();
$tempFiles = [];

// Monthly Chart
$monthlyData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $monthlyChartImg));
$monthlyTempFile = $temp_dir . '/monthly_' . uniqid() . '.png';
file_put_contents($monthlyTempFile, $monthlyData);
$tempFiles[] = $monthlyTempFile;

// Yearly Chart
$yearlyData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $yearlyChartImg));
$yearlyTempFile = $temp_dir . '/yearly_' . uniqid() . '.png';
file_put_contents($yearlyTempFile, $yearlyData);
$tempFiles[] = $yearlyTempFile;


// 4. สร้างเอกสาร PDF
$pdf = new FPDF();

// ตั้งค่าฟอนต์ภาษาไทย
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
$pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
$pdf->AddPage();
$pdf->SetFont('THSarabunNew', 'B', 22);

// หัวข้อหลัก
$pdf->Cell(0, 15, iconv('UTF-8', 'TIS-620', 'รายงานกราฟเงินเดือนรวมบริษัท'), 0, 1, 'C');
$pdf->Ln(5);

// --- กราฟที่ 1: รายเดือน ---
$pdf->SetFont('THSarabunNew', 'B', 16);
$title1 = 'สรุปยอดเงินเดือนรายเดือน (ปี พ.ศ. ' . ($selected_year + 543) . ')';
$pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $title1), 0, 1, 'L');
// เพิ่มรูปภาพ (ตำแหน่ง X, Y, ความกว้าง)
$pdf->Image($tempFiles[0], 10, $pdf->GetY(), 190); 
$pdf->SetY($pdf->GetY() + 85); // เลื่อนตำแหน่งลงมา

// --- กราฟที่ 2: รายปี (ขึ้นหน้าใหม่) ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew', 'B', 16);
$title2 = 'สรุปยอดเงินเดือนรวมรายปี';
$pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $title2), 0, 1, 'L');
$pdf->Image($tempFiles[1], 10, $pdf->GetY(), 190);

// 5. ส่งออกไฟล์และลบไฟล์ชั่วคราว
ob_end_clean();
$pdf->Output('D', 'salary_graphs_report_' . date('Ymd') . '.pdf');

// ลบไฟล์ชั่วคราว
foreach ($tempFiles as $file) {
    if (file_exists($file)) unlink($file);
}
exit;
?>