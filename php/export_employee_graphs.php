<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path สำหรับ FPDF และฟอนต์
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require '../vendor/autoload.php';
require_once 'db.php'; 

// 1. ฟังก์ชันช่วย
function thai_month_name($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? 'ไม่ระบุ';
}

// 2. รับค่าจาก POST
$monthlyChartImg = $_POST['monthlyChartImg'] ?? null;
$yearlyChartImg = $_POST['yearlyChartImg'] ?? null;
$selected_year = (int)($_POST['year'] ?? date('Y'));

if (!$monthlyChartImg || !$yearlyChartImg) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing chart image data.']);
    exit;
}

$tempFiles = []; // Array สำหรับเก็บไฟล์ชั่วคราวที่จะลบทีหลัง

try {
    // 3. แปลง Base64 เป็นรูปภาพชั่วคราว
    $temp_dir = sys_get_temp_dir();

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
    $pdf = new FPDF('P', 'mm', 'A4'); // ตั้งค่าเป็นแนวตั้ง (Portrait)
    $pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
    $pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
    
    // --- สร้างหน้าและใส่กราฟทั้งหมด ---
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 20);
    $pdf->Cell(0, 15, iconv('UTF-8', 'TIS-620', 'รายงานกราฟเงินเดือนรวมบริษัท'), 0, 1, 'C');
    
    // --- กราฟที่ 1: รายเดือน ---
    $pdf->SetFont('THSarabunNew', 'B', 16);
    $title1 = 'สรุปยอดเงินเดือนรายเดือน (ปี พ.ศ. ' . ($selected_year + 543) . ')';
    $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $title1), 0, 1, 'L');
    $pdf->Image($monthlyTempFile, 10, $pdf->GetY(), 190); 
    $pdf->Ln(105); // เลื่อนตำแหน่งลงมาสำหรับกราฟถัดไป (ปรับค่าได้ตามความสูงของกราฟ)

    // --- กราฟที่ 2: รายปี (อยู่ในหน้าเดียวกัน) ---
    $pdf->SetFont('THSarabunNew', 'B', 16);
    $title2 = 'สรุปยอดเงินเดือนรวมรายปี';
    $pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $title2), 0, 1, 'L');
    $pdf->Image($yearlyTempFile, 10, $pdf->GetY(), 190);

    // 5. ส่งออกไฟล์
    ob_end_clean(); // เคลียร์ Output Buffer ก่อนส่งไฟล์
    $pdf->Output('D', 'salary_graphs_report_' . date('Ymd') . '.pdf');

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    // 6. ลบไฟล์ชั่วคราวเสมอ
    foreach ($tempFiles as $file) {
        if (file_exists($file)) unlink($file);
    }
}
exit;
?>