<?php
// export_stock_graphs.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- เรียกใช้ Library และไฟล์ที่จำเป็น ---
require '../vendor/autoload.php';
// **สำคัญ:** ตรวจสอบว่ามี folder fonts และไฟล์ฟอนต์ TH Sarabun อยู่จริง
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
$selected_year   = $_POST['year']            ?? date('Y');
$selected_month  = $_POST['month']           ?? date('m');

// --- ตรวจสอบข้อมูล ---
if (!$dailyChartImg || !$monthlyChartImg || !$yearlyChartImg) {
    http_response_code(400);
    die('Missing chart image data.');
}

// --- ฟังก์ชันสำหรับบันทึกรูปภาพ Base64 เป็นไฟล์ชั่วคราว ---
function saveBase64Image($base64, $prefix) {
    $temp_dir = sys_get_temp_dir();
    if (!is_writable($temp_dir)) {
        die('Temporary directory is not writable.');
    }
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
    $file = $temp_dir . '/' . $prefix . '_' . uniqid() . '.png';
    file_put_contents($file, $data);
    return $file;
}

// --- สร้างไฟล์รูปภาพชั่วคราว ---
$dailyTempFile   = saveBase64Image($dailyChartImg, 'daily');
$monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly');
$yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly');

// --- เริ่มสร้างเอกสาร PDF ---
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddFont('THSarabunNew','','THSarabunNew.php');
$pdf->AddFont('THSarabunNew','B','THSarabunNew.php');

// --- หน้าที่ 1: กราฟรายวัน ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',18);
$pdf->Cell(0, 12, iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดสต็อกสินค้า'), 0, 1, 'C');
$pdf->SetFont('THSarabunNew','',14);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','วันที่ออกรายงาน: '.date('d/m/').(date('Y')+543)), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปรายวัน (เดือน '.thai_month($selected_month).' ปี พ.ศ. '.($selected_year+543).')'), 0, 1, 'L');
$pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190);

// --- หน้าที่ 2: กราฟรายเดือน และรายปี ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปรายเดือน (ปี พ.ศ. '.($selected_year+543).')'), 0, 1, 'L');
$pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);
$pdf->SetY($pdf->GetY() + 100); // เว้นระยะห่างสำหรับรูปแรก

$pdf->Ln(10);

$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปรายปี'), 0, 1, 'L');
$pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

// --- ส่งออกไฟล์ PDF ให้ Browser ดาวน์โหลด ---
ob_end_clean(); // ล้าง Output Buffer ก่อนส่งไฟล์
$pdf->Output('D', 'stock_graph_report_'.date('Y-m-d').'.pdf');

// --- ลบไฟล์ชั่วคราวหลังใช้งานเสร็จ ---
unlink($dailyTempFile);
unlink($monthlyTempFile);
unlink($yearlyTempFile);
exit;
?>