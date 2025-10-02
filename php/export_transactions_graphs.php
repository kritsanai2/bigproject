<?php
// export_transactions_graphs.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 

// --- (เพิ่มใหม่) สร้าง Class เพื่อจัดการ Header/Footer ---
class PDF_Report extends FPDF {
    // Page footer
    function Footer() {
        // ไปที่ตำแหน่ง 1.5 ซม. จากด้านล่าง
        $this->SetY(-15);
        $this->SetFont('THSarabunNew','',10);
        
        // พิมพ์วันที่สร้างรายงานชิดซ้าย
        $this->Cell(0, 10, iconv('UTF-8','TIS-620', 'สร้างเมื่อ: ' . date('d/m/') . (date('Y')+543)), 0, 0, 'L');
        
        // พิมพ์เลขหน้าชิดขวา
        $this->Cell(0, 10, iconv('UTF-8','TIS-620', 'หน้า ').$this->PageNo().'/{nb}', 0, 0, 'R');
    }
}

// --- ฟังก์ชันเดือนภาษาไทย ---
function thai_month($m) {
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    return $months[(int)$m] ?? '';
}

// --- รับค่าจาก POST ที่ส่งมาจาก JavaScript ---
$dailyChartImg   = $_POST['dailyChartImg']   ?? null;
$monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
$yearlyChartImg  = $_POST['yearlyChartImg']  ?? null;
// รับค่าฟิลเตอร์ของแต่ละกราฟ
$daily_filter_year   = (int)($_POST['daily_year'] ?? date('Y'));
$daily_filter_month  = (int)($_POST['daily_month'] ?? date('m'));
$monthly_filter_year = (int)($_POST['monthly_year'] ?? date('Y'));

if (!$dailyChartImg || !$monthlyChartImg || !$yearlyChartImg) {
    http_response_code(400);
    die('Missing chart image data.');
}

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

$dailyTempFile   = saveBase64Image($dailyChartImg, 'daily');
$monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly');
$yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly');

// --- เริ่มสร้างเอกสาร PDF (แก้ไข) ---
$pdf = new PDF_Report('P', 'mm', 'A4'); // ใช้ Class ที่เราสร้างขึ้นใหม่
$pdf->AliasNbPages(); // เปิดใช้งานการนับจำนวนหน้าทั้งหมด
$pdf->AddFont('THSarabunNew','','THSarabunNew.php');
$pdf->AddFont('THSarabunNew','B','THSarabunNew.php');

// --- หน้าที่ 1: กราฟรายวัน และรายเดือน ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',20);
$pdf->Cell(0, 15, iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดรายรับ-รายจ่าย'), 0, 1, 'C');

$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปรายวัน (เดือน '.thai_month($daily_filter_month).' ปี พ.ศ. '.($daily_filter_year+543).')'), 0, 1, 'L');
$pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190);
$pdf->Ln(105); // เว้นระยะ

$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปรายเดือน (ปี พ.ศ. '.($monthly_filter_year+543).')'), 0, 1, 'L');
$pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);

// --- หน้าที่ 2: กราฟรายปี ---
$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',20);
$pdf->Cell(0, 15, iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดรายรับ-รายจ่าย (ต่อ)'), 0, 1, 'C');

$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปรายปี'), 0, 1, 'L');
$pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

// --- ส่งออกไฟล์ PDF ---
ob_end_clean(); 
$pdf->Output('D', 'transactions_graph_report_'.date('Y-m-d').'.pdf');

// --- ลบไฟล์ชั่วคราว ---
unlink($dailyTempFile);
unlink($monthlyTempFile);
unlink($yearlyTempFile);
exit;
?>