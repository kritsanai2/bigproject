<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('FPDF_FONTPATH', __DIR__ . '/fonts/');
require '../vendor/autoload.php';
require_once 'db.php';

// ฟังก์ชันช่วย
function thai_type_text($type) { 
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก'; 
}
function thai_month($month) {
    $months = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    return $months[(int)$month] ?? '';
}

// 1. รับค่าตัวกรองจาก URL (เหมือนใน dashboard)
$type_filter = $_GET['type'] ?? '';
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0;

// 2. สร้างหัวข้อรายงานแบบ Dynamic
if ($month_filter == 0 && $filter_year == 0 && empty($type_filter)) {
    $report_title = "รายงานสต็อกทั้งหมด";
} else {
    $report_title = "รายงานสต็อก";
    if (!empty($type_filter)) $report_title .= "ประเภท " . thai_type_text($type_filter);
    if ($month_filter > 0) $report_title .= " เดือน " . thai_month($month_filter);
    if ($filter_year > 0) $report_title .= " ปี " . ($filter_year + 543);
}

// 3. ดึงข้อมูลจากฐานข้อมูล (ใช้ Prepared Statements)
$sql = "SELECT s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit 
        FROM stock s JOIN products p ON s.product_id = p.product_id";
        
$where_clauses = [];
$params = [];
$types = '';

if ($type_filter) { $where_clauses[] = "s.stock_type = ?"; $params[] = $type_filter; $types .= 's'; }
if ($month_filter > 0) { $where_clauses[] = "MONTH(s.stock_date) = ?"; $params[] = $month_filter; $types .= 'i'; }
if ($filter_year > 0) { $where_clauses[] = "YEAR(s.stock_date) = ?"; $params[] = $filter_year; $types .= 'i'; }

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

// คำนวณยอดรวม
$total_import = 0;
$total_remove = 0;
foreach ($rows as $row) {
    if ($row['stock_type'] == 'import') {
        $total_import += $row['quantity'];
    } else {
        $total_remove += $row['quantity'];
    }
}

// 4. สร้างเอกสาร PDF  หรือ สร้างออบเจกต์ PDF ใหม่
$pdf = new FPDF();
//เพิ่มฟอนต์ภาษาไทย (ในที่นี้คือ THSarabunNew)  b ตัวหนา
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
$pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
$pdf->AddPage();

// หัวข้อรายงาน //กำหนดฟอนต์ (THSarabunNew, ตัวหนา, ขนาด 18)
$pdf->SetFont('THSarabunNew', 'B', 18);
$pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
$pdf->Ln(5);

// หัวตาราง
$pdf->SetFont('THSarabunNew', 'B', 12);
$pdf->SetFillColor(220, 220, 220);
$header = ['ลำดับ', 'วันที่', 'สินค้า', 'ประเภท', 'จำนวน', 'หน่วย'];
$w = [20, 30, 70, 25, 25, 20]; // ความกว้างคอลัมน์
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

// เนื้อหาตาราง
$pdf->SetFont('THSarabunNew', '', 12);
if (!empty($rows)) {
    $i = 1;
    foreach ($rows as $row) {
        $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
        $date_th = date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543);
        $pdf->Cell($w[1], 8, $date_th, 1, 0, 'C');
        $pdf->Cell($w[2], 8, iconv('UTF-8', 'TIS-620', $row['product_name']), 1, 0, 'L');
        $pdf->Cell($w[3], 8, iconv('UTF-8', 'TIS-620', thai_type_text($row['stock_type'])), 1, 0, 'C');
        $pdf->Cell($w[4], 8, number_format($row['quantity']), 1, 0, 'R');
        $pdf->Cell($w[5], 8, iconv('UTF-8', 'TIS-620', $row['unit']), 1, 1, 'C');
    }
    // แถวสรุป
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(array_sum($w) - $w[4] - $w[5], 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมรับเข้า'), 1, 0, 'R');
    $pdf->Cell($w[4], 10, number_format($total_import), 1, 1, 'R');
    $pdf->Cell(array_sum($w) - $w[4] - $w[5], 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมจ่ายออก'), 1, 0, 'R');
    $pdf->Cell($w[4], 10, number_format($total_remove), 1, 1, 'R');
} else { 
    $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูล'), 1, 1, 'C'); 
}

ob_end_clean();

// 5. ส่งออกไฟล์
$pdf->Output('D', 'stock_report_'.date('Ymd').'.pdf');
?>
