<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path สำหรับ FPDF และฟอนต์
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require '../vendor/autoload.php';
require_once 'db.php';

// use FPDF\FPDF; // Remove this line, FPDF does not use namespaces

// กำหนดค่าแรงต่อวัน
$full_rate = 300;
$half_rate = 150;

// ฟังก์ชันช่วย
function thai_month_name($month_year) {
    $parts = explode('-', $month_year);
    if (count($parts) !== 2) return $month_year;
    $y = (int)$parts[0];
    $m = (int)$parts[1];
    $months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    return $months[$m - 1] . ' ' . ($y + 543);
}

// 1. รับค่าตัวกรอง
$selected_month = $_GET['month'] ?? date('Y-m');
$report_title = 'รายงานเงินเดือนพนักงาน ประจำเดือน ' . thai_month_name($selected_month);

// 2. ดึงข้อมูลและคำนวณเงินเดือน (โค้ดเดียวกับใน dashboard)
$calculated_data = [];
$stmt = $conn->prepare("
    SELECT 
        e.employee_id, e.full_name,
        SUM(CASE WHEN a.morning='present' AND a.afternoon='present' THEN 1 ELSE 0 END) AS full_days,
        SUM(CASE WHEN (a.morning='present' AND a.afternoon NOT IN ('present', 'late')) OR (a.morning NOT IN ('present', 'late') AND a.afternoon='present') THEN 1 ELSE 0 END) AS half_days,
        COUNT(DISTINCT CASE WHEN a.morning='late' OR a.afternoon='late' THEN a.attend_date END) AS late_days,
        SUM(CASE WHEN a.morning='leave' AND a.afternoon='leave' THEN 1 ELSE 0 END) AS leave_days,
        SUM(CASE WHEN a.morning='absent' AND a.afternoon='absent' THEN 1 ELSE 0 END) AS absent_days
    FROM employees e
    LEFT JOIN attendances a ON e.employee_id = a.employee_id AND DATE_FORMAT(a.attend_date, '%Y-%m') = ?
    WHERE e.status = 1
    GROUP BY e.employee_id
    ORDER BY e.employee_id
");
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$result = $stmt->get_result();

if($result){
    while($row = $result->fetch_assoc()){
        $work_days_paid = (float)$row['full_days'] + ((float)$row['half_days'] * 0.5) + (float)$row['late_days'];
        $amount = ((int)$row['full_days'] * $full_rate) + ((int)$row['half_days'] * $half_rate) + ((int)$row['late_days'] * $full_rate);
        
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

// หัวตาราง
$pdf->SetFont('THSarabunNew', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$header = ['ลำดับ', 'รหัส', 'ชื่อ-สกุล', 'เต็มวัน', 'ครึ่งวัน', 'สาย', 'ลา', 'ขาด', 'รวมวันทำงาน', 'เงินเดือน (บาท)'];
$w = [15, 20, 55, 20, 20, 20, 20, 20, 30, 30]; // ความกว้างแต่ละคอลัมน์
$align = ['C', 'C', 'L', 'C', 'C', 'C', 'C', 'C', 'C', 'R'];

for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 8, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

// เนื้อหาตาราง
$pdf->SetFont('THSarabunNew', '', 10);
$i = 1;
$totals = array_fill_keys(['full','half','late','leave','absent','work_days','amount'], 0);

if (!empty($calculated_data)) {
    foreach ($calculated_data as $d) {
        // คำนวณยอดรวม
        foreach(['full','half','late','leave','absent','work_days','amount'] as $key) {
             $totals[$key] += $d[$key]; 
        }

        $pdf->Cell($w[0], 7, $i++, 1, 0, 'C');
        $pdf->Cell($w[1], 7, iconv('UTF-8', 'TIS-620', $d['id']), 1, 0, 'C');
        $pdf->Cell($w[2], 7, iconv('UTF-8', 'TIS-620', $d['full_name']), 1, 0, 'L');
        $pdf->Cell($w[3], 7, $d['full'], 1, 0, 'C');
        $pdf->Cell($w[4], 7, $d['half'], 1, 0, 'C');
        $pdf->Cell($w[5], 7, $d['late'], 1, 0, 'C');
        $pdf->Cell($w[6], 7, $d['leave'], 1, 0, 'C');
        $pdf->Cell($w[7], 7, $d['absent'], 1, 0, 'C');
        $pdf->Cell($w[8], 7, number_format($d['work_days'], 1), 1, 0, 'C');
        $pdf->Cell($w[9], 7, number_format($d['amount'], 2), 1, 1, 'R');
    }

    // แถวรวม
    $pdf->SetFont('THSarabunNew', 'B', 10);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell($w[0]+$w[1]+$w[2], 8, iconv('UTF-8', 'TIS-620', 'ยอดรวมทั้งหมด'), 1, 0, 'R', true);
    $pdf->Cell($w[3], 8, number_format($totals['full']), 1, 0, 'C', true);
    $pdf->Cell($w[4], 8, number_format($totals['half']), 1, 0, 'C', true);
    $pdf->Cell($w[5], 8, number_format($totals['late']), 1, 0, 'C', true);
    $pdf->Cell($w[6], 8, number_format($totals['leave']), 1, 0, 'C', true);
    $pdf->Cell($w[7], 8, number_format($totals['absent']), 1, 0, 'C', true);
    $pdf->Cell($w[8], 8, number_format($totals['work_days'], 1), 1, 0, 'C', true);
    $pdf->Cell($w[9], 8, number_format($totals['amount'], 2), 1, 1, 'R', true);
    
} else {
    $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลเงินเดือนในเดือนที่เลือก'), 1, 1, 'C');
}

ob_end_clean();

// 4. ส่งออกไฟล์
$pdf->Output('D', 'payroll_report_' . $selected_month . '.pdf');
exit();
?>