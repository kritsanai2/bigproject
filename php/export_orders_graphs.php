<?php
// export_orders_graphs.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php'; 

require '../vendor/autoload.php';
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 

function thai_month($m) {
    $months = ["","มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    return $months[(int)$m] ?? '';
}

$dailyChartImg   = $_POST['dailyChartImg']   ?? null;
$monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
$yearlyChartImg  = $_POST['yearlyChartImg']  ?? null;
$selected_year   = $_POST['year']            ?? date('Y');
$selected_month  = $_POST['month']           ?? date('m');

if (!$dailyChartImg || !$monthlyChartImg || !$yearlyChartImg) {
    die('Missing chart image data.');
}

function saveBase64Image($base64, $prefix) {
    $temp_dir = sys_get_temp_dir();
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64));
    $file = $temp_dir . '/' . $prefix . '_' . uniqid() . '.png';
    file_put_contents($file, $data);
    return $file;
}

$dailyTempFile   = saveBase64Image($dailyChartImg, 'daily_order');
$monthlyTempFile = saveBase64Image($monthlyChartImg, 'monthly_order');
$yearlyTempFile  = saveBase64Image($yearlyChartImg, 'yearly_order');

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddFont('THSarabunNew','','THSarabunNew.php');
$pdf->AddFont('THSarabunNew','B','THSarabunNew.php');

$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',18);
$pdf->Cell(0, 12, iconv('UTF-8','TIS-620','รายงานกราฟสรุปยอดขาย'), 0, 1, 'C');
$pdf->SetFont('THSarabunNew','',14);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','วันที่ออกรายงาน: '.date('d/m/').(date('Y')+543)), 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปยอดขายรายวัน (เดือน '.thai_month($selected_month).' ปี พ.ศ. '.($selected_year+543).')'), 0, 1, 'L');
$pdf->Image($dailyTempFile, 10, $pdf->GetY(), 190);

$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปยอดขายรายเดือน (ปี พ.ศ. '.($selected_year+543).')'), 0, 1, 'L');
$pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190);
$pdf->SetY($pdf->GetY() + 100);

$pdf->Ln(10);
$pdf->SetFont('THSarabunNew','B',16);
$pdf->Cell(0, 10, iconv('UTF-8','TIS-620','สรุปยอดขายรายปี'), 0, 1, 'L');
$pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

ob_end_clean();
$pdf->Output('D', 'orders_graph_report_'.date('Y-m-d').'.pdf');

unlink($dailyTempFile);
unlink($monthlyTempFile);
unlink($yearlyTempFile);
exit;
?>