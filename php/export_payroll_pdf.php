<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path สำหรับ FPDF และฟอนต์
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require '../vendor/autoload.php';
require_once 'db.php';

// --- ค่าคงที่ ---
define('FULL_RATE', 300);
define('HALF_RATE', 150);

// --- ฟังก์ชันช่วยเหลือ ---
function thai_month_name($month_num) {
    $months = [1=>'มกราคม', 2=>'กุมภาพันธ์', 3=>'มีนาคม', 4=>'เมษายน', 5=>'พฤษภาคม', 6=>'มิถุนายน', 7=>'กรกฎาคม', 8=>'สิงหาคม', 9=>'กันยายน', 10=>'ตุลาคม', 11=>'พฤศจิกายน', 12=>'ธันวาคม'];
    return $months[intval($month_num)] ?? '';
}

// 1. รับค่าตัวกรอง
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year_filter  = isset($_GET['year']) ? intval($_GET['year']) : 0;

// --- สร้างหัวข้อรายงานแบบ Dynamic ---
if ($month_filter == 0 && $year_filter == 0) {
    $report_title = "รายงานเงินเดือนทั้งหมด";
} else {
    $report_title = "รายงานเงินเดือน";
    if ($month_filter > 0) $report_title .= " เดือน " . thai_month_name($month_filter);
    if ($year_filter > 0) $report_title .= " ปี " . ($year_filter + 543);
    else $report_title .= " (ทุกปี)";
}

// 2. ดึงข้อมูลและคำนวณเงินเดือน (โค้ดเดียวกับใน dashboard)
$sql = "
    SELECT 
        e.employee_id, e.full_name, p.full_days, p.half_days, p.late_days,
        p.leave_days, p.absent_days, p.work_days, p.amount
    FROM employees e
    LEFT JOIN employee_payments p ON e.employee_id = p.employee_id
        AND (? = 0 OR MONTH(p.pay_month) = ?)
        AND (? = 0 OR YEAR(p.pay_month) = ?)
    WHERE e.status = 1
    ORDER BY e.employee_id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiii', $month_filter, $month_filter, $year_filter, $year_filter);
$stmt->execute();
$result = $stmt->get_result();

$calculated_data = [];
if($result){
    while($row = $result->fetch_assoc()){
        $work_days_paid = (float)$row['full_days'] + ((float)$row['half_days'] * 0.5) + ((float)$row['late_days'] * 0.5);
        $amount = ((int)$row['full_days'] * FULL_RATE) + ((int)$row['half_days'] * HALF_RATE) + ((int)$row['late_days'] * HALF_RATE);
        
        $calculated_data[] = [
            'id' => $row['employee_id'], 'full_name' => $row['full_name'], 'full' => (int)$row['full_days'],
            'half' => (int)$row['half_days'], 'late' => (int)$row['late_days'],
            'leave' => (int)$row['leave_days'], 'absent' => (int)$row['absent_days'],
            'work_days' => $work_days_paid, 'amount' => $amount
        ];
    }
}

// 3. สร้างเอกสาร PDF
$pdf = new FPDF('L'); // ตั้งค่าแนวนอน
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
$pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
$pdf->AddPage();

// หัวข้อรายงาน
$pdf->SetFont('THSarabunNew', 'B', 18);
$pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
$pdf->Ln(5);

// หัวตาราง
$pdf->SetFont('THSarabunNew', 'B', 12);
$pdf->SetFillColor(230, 230, 230);
$header = ['ลำดับ', 'รหัส', 'ชื่อ-สกุล', 'เต็มวัน', 'ครึ่งวัน', 'สาย', 'ลา', 'ขาด', 'รวมวันทำงาน', 'เงินเดือน (บาท)'];
$w = [15, 20, 75, 20, 20, 20, 20, 20, 30, 30]; // ความกว้างแต่ละคอลัมน์

for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

// เนื้อหาตาราง
$pdf->SetFont('THSarabunNew', '', 12);
$i = 1;
$totals = array_fill_keys(['full','half','late','leave','absent','work_days','amount'], 0);

if (!empty($calculated_data)) {
    foreach ($calculated_data as $d) {
        foreach(['full','half','late','leave','absent','work_days','amount'] as $key) {
             $totals[$key] += $d[$key]; 
        }

        $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
        $pdf->Cell($w[1], 8, iconv('UTF-8', 'TIS-620', $d['id']), 1, 0, 'C');
        $pdf->Cell($w[2], 8, iconv('UTF-8', 'TIS-620', $d['full_name']), 1, 0, 'L');
        $pdf->Cell($w[3], 8, $d['full'], 1, 0, 'C');
        $pdf->Cell($w[4], 8, $d['half'], 1, 0, 'C');
        $pdf->Cell($w[5], 8, $d['late'], 1, 0, 'C');
        $pdf->Cell($w[6], 8, $d['leave'], 1, 0, 'C');
        $pdf->Cell($w[7], 8, $d['absent'], 1, 0, 'C');
        $pdf->Cell($w[8], 8, number_format($d['work_days'], 1), 1, 0, 'C');
        $pdf->Cell($w[9], 8, number_format($d['amount'], 2), 1, 1, 'R');
    }

    // แถวรวม
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell($w[0]+$w[1]+$w[2], 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมทั้งหมด'), 1, 0, 'R', true);
    $pdf->Cell($w[3], 10, number_format($totals['full']), 1, 0, 'C', true);
    $pdf->Cell($w[4], 10, number_format($totals['half']), 1, 0, 'C', true);
    $pdf->Cell($w[5], 10, number_format($totals['late']), 1, 0, 'C', true);
    $pdf->Cell($w[6], 10, number_format($totals['leave']), 1, 0, 'C', true);
    $pdf->Cell($w[7], 10, number_format($totals['absent']), 1, 0, 'C', true);
    $pdf->Cell($w[8], 10, number_format($totals['work_days'], 1), 1, 0, 'C', true);
    $pdf->Cell($w[9], 10, number_format($totals['amount'], 2), 1, 1, 'R', true);
    
} else {
    $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลตามเงื่อนไขที่เลือก'), 1, 1, 'C');
}

ob_end_clean();

// 4. ส่งออกไฟล์
$pdf->Output('D', 'payroll_report_' . date('Y-m-d') . '.pdf');
exit();
?>