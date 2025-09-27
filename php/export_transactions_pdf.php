<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path สำหรับ FPDF และฟอนต์
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require '../vendor/autoload.php';
require_once 'db.php';

// ฟังก์ชันแปลงประเภทและเดือนเป็นไทย
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month_name($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}

// 1. รับค่าตัวกรอง (เหมือนใน dashboard)
$type_filter = $_GET['type'] ?? '';
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0; 
$current_year = intval(date('Y'));
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

// 2. สร้างหัวข้อรายงาน
$report_period = "";
if ($filter_month > 0) $report_period .= "ประจำเดือน " . thai_month_name($filter_month);
if ($filter_year > 0) $report_period .= " ปี พ.ศ. " . ($filter_year + 543);
if ($type_filter) $report_period .= " (ประเภท: " . thai_type($type_filter) . ")";

$report_title = 'รายงานรายรับ-รายจ่าย' . $report_period;

// 3. ดึงข้อมูล (ใช้ Prepared Statements เหมือนใน dashboard)
// *** แก้ไข: นำ LEFT JOIN expense_categories และ e.expense_type ออก ***
$sql = "SELECT t.*, od.product_id, p.product_name
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id";

$params = [];
$types = "";
$where_clauses = [];

if ($type_filter) { $where_clauses[] = "t.transaction_type = ?"; $params[] = $type_filter; $types .= "s"; }
if ($filter_month > 0) { $where_clauses[] = "MONTH(t.transaction_date) = ?"; $params[] = $filter_month; $types .= "i"; }
if ($filter_year > 0) { $where_clauses[] = "YEAR(t.transaction_date) = ?"; $params[] = $filter_year; $types .= "i"; }

if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }

// ใช้ die() เพื่อป้องกัน Error 500 หาก Execute ล้มเหลว
if (!$stmt->execute()) {
    die("Database query error: " . $stmt->error);
}

$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);

// คำนวณยอดรวม
$total_income = 0;
$total_expense = 0;
foreach($rows as $row){
    if($row['transaction_type'] === 'income'){ $total_income += $row['amount']; } else { $total_expense += $row['amount']; }
}
$balance = $total_income - $total_expense;

// 4. สร้างเอกสาร PDF
$pdf = new FPDF('P'); 
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
$pdf = new \FPDF('P'); 
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
$pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
$pdf->AddPage();
$pdf->SetFont('THSarabunNew', 'B', 18);
$pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
$pdf->Ln(2);

// หัวตาราง
$pdf->SetFont('THSarabunNew', 'B', 12);
$pdf->SetFillColor(230, 230, 230);
$header = ['ลำดับ', 'รหัส', 'วันที่', 'ประเภท', 'จำนวนเงิน (บาท)', 'รายละเอียด'];
$w = [15, 25, 25, 20, 30, 75]; // ความกว้างแต่ละคอลัมน์
$align = ['C', 'C', 'C', 'C', 'R', 'L'];
$grand_w = array_sum($w);

for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

// เนื้อหาตาราง
$pdf->SetFont('THSarabunNew', '', 12);
$i = 1;

if (!empty($rows)) {
    foreach ($rows as $row) {
        // *** แก้ไข: ใช้ $row['expense_type'] ที่มีอยู่ในตาราง transactions โดยตรง ***
        $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : (!empty($row['product_name']) ? "ขาย: " . $row['product_name'] : 'รายรับจากออเดอร์');
        
        $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
        $pdf->Cell($w[1], 8, iconv('UTF-8', 'TIS-620', $row['transaction_id']), 1, 0, 'C');
        $pdf->Cell($w[2], 8, date('d/m/Y', strtotime($row['transaction_date'])), 1, 0, 'C');
        $pdf->Cell($w[3], 8, iconv('UTF-8', 'TIS-620', thai_type($row['transaction_type'])), 1, 0, 'C');
        $pdf->Cell($w[4], 8, number_format($row['amount'], 2), 1, 0, 'R');
        $pdf->Cell($w[5], 8, iconv('UTF-8', 'TIS-620', $desc), 1, 1, 'L');
    }
    
    // แถวสรุป
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell($w[0]+$w[1]+$w[2]+$w[3], 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมรายรับ/รายจ่าย/คงเหลือ'), 1, 0, 'R', true);
    
    // Summary data for balance card
    $pdf->Cell($w[4], 10, iconv('UTF-8', 'TIS-620', 'รับ: '.number_format($total_income, 2)), 1, 0, 'R', true);
    $pdf->Cell($w[5], 10, iconv('UTF-8', 'TIS-620', 'จ่าย: '.number_format($total_expense, 2) . ' / คงเหลือ: ' . number_format($balance, 2)), 1, 1, 'R', true);
    
} else {
    $pdf->Cell($grand_w, 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลตามเงื่อนไขที่เลือก'), 1, 1, 'C');
}

ob_end_clean();

// 5. ส่งออกไฟล์
$pdf->Output('D', 'transactions_report_' . date('Ymd') . '.pdf');
exit();
?>