<?php
// แสดงข้อผิดพลาดสำหรับดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// กำหนด Path ฟอนต์
define('FPDF_FONTPATH', __DIR__ . '/fonts/');

// เรียกใช้งาน Library
require '../vendor/autoload.php';
require_once 'db.php';

// ฟังก์ชันแปลงเดือนเป็นภาษาไทย
function thai_month($month) {
    $months = [
        1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
        5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
        9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
    ];
    return $months[(int)$month] ?? $month;
}

// รับค่าจาก POST
$dailyChartImg   = $_POST['dailyChartImg']   ?? null;
$monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
$yearlyChartImg  = $_POST['yearlyChartImg']  ?? null;
$daily_year      = $_POST['daily_year']      ?? date('Y');
$daily_month     = $_POST['daily_month']     ?? date('m');
$selected_year   = $_POST['year']            ?? date('Y');

if (!$dailyChartImg || !$monthlyChartImg || !$yearlyChartImg) {
    http_response_code(400);
    die('Missing chart image data.');
}

// ฟังก์ชันช่วยแปลง Base64 -> ไฟล์ชั่วคราว
$temp_dir = sys_get_temp_dir();
function saveBase64Image($base64, $prefix) {
    global $temp_dir;
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
    $file = $temp_dir . '/' . $prefix . '_' . uniqid() . '.png';
    file_put_contents($file, $data);
    return $file;
}

$dailyTempFile   = saveBase64Image($dailyChartImg, 'daily');
$monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly');
$yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly');

// เริ่มสร้าง PDF
$pdf = new FPDF();
$pdf->AddFont('THSarabunNew','','THSarabunNew.php');
$pdf->AddFont('THSarabunNew','B','THSarabunNew.php');

// --- หน้าแรก: Daily Chart ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',20);
$pdf->Cell(0,15,iconv('UTF-8','TIS-620','รายงานกราฟสรุปสต็อกสินค้า'),0,1,'C');
$pdf->Ln(5);
$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(
    0,10,
    iconv('UTF-8','TIS-620','สรุปยอดรับเข้า-จ่ายออกรายวัน เดือน '.thai_month($daily_month).' ปี พ.ศ. '.($daily_year+543)),
    0,1,'L'
);
$pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190);

// --- หน้าใหม่: Monthly Chart ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(
    0,10,
    iconv('UTF-8','TIS-620','สรุปยอดรับเข้า-จ่ายออกรายเดือน (ปี พ.ศ. '.($selected_year+543).')'),
    0,1,'L'
);
$pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);

// --- หน้าใหม่: Yearly Chart ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(
    0,10,
    iconv('UTF-8','TIS-620','สรุปยอดรับเข้า-จ่ายออกรายปี'),
    0,1,'L'
);
$pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

// ส่งออก PDF
ob_end_clean();
$pdf->Output('D', 'stock_graph_report_'.$selected_year.'.pdf');

// ลบไฟล์ชั่วคราว
unlink($dailyTempFile);
unlink($monthlyTempFile);
unlink($yearlyTempFile);
exit;
?>
