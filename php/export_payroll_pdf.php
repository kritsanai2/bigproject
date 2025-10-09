<?php
// --- เปิดการแสดงข้อผิดพลาด ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- FPDF Path และ Autoload ---
define('FPDF_FONTPATH', __DIR__ . '/fonts/');
require '../vendor/autoload.php';
require_once 'db.php';

// --- ฟังก์ชันช่วยเหลือ ---
function thai_month($month_num) {
    $months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    return $months[intval($month_num)] ?? '';
}

// --- รับค่า Filter ---
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : 0;
$year_filter  = isset($_GET['year']) ? intval($_GET['year']) : 0;

// --- สร้างหัวข้อรายงาน ---
$report_title = "รายงานเงินเดือน";
if ($month_filter > 0) $report_title .= " เดือน " . thai_month($month_filter);
if ($year_filter > 0)  $report_title .= " ปี " . ($year_filter + 543);

// --- ดึงข้อมูลจากฐานข้อมูล ---
$sql = "SELECT e.employee_id, e.full_name, 
               p.full_days, p.half_days, p.late_days, 
               p.leave_days, p.absent_days, p.work_days, p.salary
        FROM employees e
        LEFT JOIN employee_payments p 
               ON e.employee_id = p.employee_id";

$where_clauses = [];
$params = [];
$types = '';

if ($month_filter > 0) { 
    $where_clauses[] = "MONTH(p.pay_month) = ?"; 
    $params[] = $month_filter; 
    $types .= 'i'; 
}
if ($year_filter > 0) { 
    $where_clauses[] = "YEAR(p.pay_month) = ?"; 
    $params[] = $year_filter; 
    $types .= 'i'; 
}

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(' AND ', $where_clauses);
}

$sql .= " WHERE e.status = 1 ORDER BY e.employee_id ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// --- สร้าง PDF ---
$pdf = new FPDF('L','mm','A4');
$pdf->AddFont('THSarabunNew','','THSarabunNew.php');
$pdf->AddFont('THSarabunNew','B','THSarabunNew.php');
$pdf->AddPage();

// --- Header ---
$pdf->SetFont('THSarabunNew','B',18);
$pdf->Cell(0,10,iconv('UTF-8','TIS-620',$report_title),0,1,'C');
$pdf->Ln(5);

// --- หัวตาราง ---
$pdf->SetFont('THSarabunNew','B',12);
$pdf->SetFillColor(220,220,220);
$header = ['ลำดับ','รหัส','ชื่อ-สกุล','เต็มวัน','ครึ่งวัน','สาย','ลา','ขาด','รวมวันทำงาน','เงินเดือน (บาท)'];
$w = [10,15,60,15,15,15,15,15,25,25];

for($i=0;$i<count($header);$i++){
    $pdf->Cell($w[$i],10,iconv('UTF-8','TIS-620',$header[$i]),1,0,'C',true);
}
$pdf->Ln();

// --- เนื้อหาตาราง ---
$pdf->SetFont('THSarabunNew','',12);

if (!empty($data)) {
    $totals = ['full_days'=>0,'half_days'=>0,'late_days'=>0,'leave_days'=>0,'absent_days'=>0,'work_days'=>0,'salary'=>0];
    $i=1;
    foreach($data as $row){
        $fill = ($i%2==0);
        $pdf->Cell($w[0],8,$i++,1,0,'C',$fill);
        $pdf->Cell($w[1],8,$row['employee_id'],1,0,'C',$fill);
        $pdf->Cell($w[2],8,iconv('UTF-8','TIS-620',$row['full_name']),1,0,'L',$fill);
        $pdf->Cell($w[3],8,(int)$row['full_days'],1,0,'C',$fill);
        $pdf->Cell($w[4],8,(int)$row['half_days'],1,0,'C',$fill);
        $pdf->Cell($w[5],8,(int)$row['late_days'],1,0,'C',$fill);
        $pdf->Cell($w[6],8,number_format((float)$row['leave_days'],1),1,0,'C',$fill);
        $pdf->Cell($w[7],8,number_format((float)$row['absent_days'],1),1,0,'C',$fill);
        $pdf->Cell($w[8],8,number_format((float)$row['work_days'],1),1,0,'C',$fill);
        $pdf->Cell($w[9],8,number_format((float)$row['salary'],2),1,1,'R',$fill);

        foreach($totals as $key=>&$value) $value += (float)($row[$key]??0);
    }

    // แถวสรุปยอดรวม
    $pdf->SetFont('THSarabunNew','B',12);
    $pdf->SetFillColor(200,200,200);
    $pdf->Cell(array_sum(array_slice($w,0,3)),8,iconv('UTF-8','TIS-620','รวมทั้งหมด'),1,0,'R',true);
    $pdf->Cell($w[3],8,$totals['full_days'],1,0,'C',true);
    $pdf->Cell($w[4],8,$totals['half_days'],1,0,'C',true);
    $pdf->Cell($w[5],8,$totals['late_days'],1,0,'C',true);
    $pdf->Cell($w[6],8,number_format($totals['leave_days'],1),1,0,'C',true);
    $pdf->Cell($w[7],8,number_format($totals['absent_days'],1),1,0,'C',true);
    $pdf->Cell($w[8],8,number_format($totals['work_days'],1),1,0,'C',true);
    $pdf->Cell($w[9],8,number_format($totals['salary'],2),1,1,'R',true);

} else {
    $pdf->Cell(array_sum($w),10,iconv('UTF-8','TIS-620','ไม่พบข้อมูล'),1,1,'C');
}

// --- ส่งออก PDF ---
ob_end_clean();
$pdf->Output('D','payroll_report_'.date('Ymd').'.pdf');

?>
