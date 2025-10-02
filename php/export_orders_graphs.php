<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 

// --- ฟังก์ชันเดือนภาษาไทย ---
function thai_month($m) {
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    return $months[(int)$m] ?? '';
}

// --- รับค่าจาก POST ที่ส่งมาจาก JavaScript ---
$dailyChartImg   = $_POST['dailyChartImg']   ?? null;
$monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
$yearlyChartImg  = $_POST['yearlyChartImg']  ?? null;
// **รับค่าฟิลเตอร์ของแต่ละกราฟให้ถูกต้อง**
$daily_filter_year   = (int)($_POST['daily_year'] ?? date('Y'));
$daily_filter_month  = (int)($_POST['daily_month'] ?? date('m'));
$monthly_filter_year = (int)($_POST['monthly_year'] ?? date('Y'));

if (!$dailyChartImg || !$monthlyChartImg || !$yearlyChartImg) {
    http_response_code(400);
    die('Missing chart image data.');
}

function saveBase64Image($base64, $prefix) {
    $temp_dir = sys_get_temp_dir();
    if (!is_writable($temp_dir)) die('Temporary directory is not writable.');
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
    $file = $temp_dir . '/' . $prefix . '_' . uniqid() . '.png';
    file_put_contents($file, $data);
    return $file;
}

$dailyTempFile   = saveBase64Image($dailyChartImg, 'daily_sales');
$monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly_sales');
$yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly_sales');

// --- เริ่มสร้างเอกสาร PDF ---
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddFont('THSarabunNew','','THSarabunNew.php');
$pdf->AddFont('THSarabunNew','B','THSarabunNew.php');

// --- หน้าที่ 1: กราฟรายวัน และรายเดือน ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',20);
$pdf->Cell(0, 15, iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดขาย'), 0, 1, 'C');

$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','ยอดขายรายวัน (เดือน '.thai_month($daily_filter_month).' ปี พ.ศ. '.($daily_filter_year+543).')'), 0, 1, 'L');
$pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190);
$pdf->Ln(105); 

$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','ยอดขายรายเดือน (ปี พ.ศ. '.($monthly_filter_year+543).')'), 0, 1, 'L');
$pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);

// --- หน้าที่ 2: กราฟรายปี ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',20);
$pdf->Cell(0, 15, iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดขาย (ต่อ)'), 0, 1, 'C');

$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปยอดขายรายปี'), 0, 1, 'L');
$pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

// --- ส่งออกไฟล์ PDF ---
ob_end_clean(); 
$pdf->Output('D', 'sales_graph_report_'.date('Y-m-d').'.pdf');

// --- ลบไฟล์ชั่วคราว ---
unlink($dailyTempFile);
unlink($monthlyTempFile);
unlink($yearlyTempFile);
exit;
?>
