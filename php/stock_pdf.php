<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ปรับ Path ให้ถูกต้องตามโครงสร้างโฟลเดอร์ของคุณ
define('FPDF_FONTPATH', __DIR__ . '/fonts/');

// 1. เรียกใช้ Library และไฟล์เชื่อมต่อ
require '../vendor/autoload.php';
require_once 'db.php';

// ฟังก์ชันช่วย
function thai_type_text($type) { 
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก'; 
}

// 2. รับค่าตัวกรองจาก URL
$type_filter = $_GET['type'] ?? ''; 
$month_filter = $_GET['month'] ?? 0; 
$year_filter = $_GET['year'] ?? date('Y');

// 3. ดึงข้อมูลจากฐานข้อมูล (ใช้ Prepared Statements)
$sql = "SELECT s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit 
        FROM stock s JOIN products p ON s.product_id = p.product_id";
        
$where_clauses = [];
$params = [];
$types = '';

if ($type_filter) {
    $where_clauses[] = "s.stock_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}
if ($month_filter > 0) {
    $where_clauses[] = "MONTH(s.stock_date) = ?";
    $params[] = (int)$month_filter;
    $types .= 'i';
}
if ($year_filter) {
    $where_clauses[] = "YEAR(s.stock_date) = ?";
    $params[] = (int)$year_filter;
    $types .= 'i';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";

// ใช้ Prepared Statement
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// 4. สร้างเอกสาร PDF
$pdf = new FPDF();

// ตั้งค่าฟอนต์ภาษาไทย
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
$pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');

$pdf->AddPage();

// หัวข้อรายงาน
$pdf->SetFont('THSarabunNew', 'B', 18);
$pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', 'รายงานสต็อกสินค้า'), 0, 1, 'C');

// หัวตาราง
$pdf->SetFont('THSarabunNew', 'B', 12);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(20, 10, iconv('UTF-8', 'TIS-620', 'ลำดับ'), 1, 0, 'C', true);
$pdf->Cell(30, 10, iconv('UTF-8', 'TIS-620', 'วันที่'), 1, 0, 'C', true);
$pdf->Cell(70, 10, iconv('UTF-8', 'TIS-620', 'สินค้า'), 1, 0, 'C', true);
$pdf->Cell(25, 10, iconv('UTF-8', 'TIS-620', 'ประเภท'), 1, 0, 'C', true);
$pdf->Cell(25, 10, iconv('UTF-8', 'TIS-620', 'จำนวน'), 1, 0, 'C', true);
$pdf->Cell(20, 10, iconv('UTF-8', 'TIS-620', 'หน่วย'), 1, 1, 'C', true);

// เนื้อหาตาราง
$pdf->SetFont('THSarabunNew', '', 12);
if ($result && $result->num_rows > 0) {
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(20, 8, $i++, 1, 0, 'C');
        $date_th = date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543);
        $pdf->Cell(30, 8, $date_th, 1, 0, 'C');
        $pdf->Cell(70, 8, iconv('UTF-8', 'TIS-620', $row['product_name']), 1, 0, 'L');
        $pdf->Cell(25, 8, iconv('UTF-8', 'TIS-620', thai_type_text($row['stock_type'])), 1, 0, 'C');
        $pdf->Cell(25, 8, number_format($row['quantity']), 1, 0, 'R');
        $pdf->Cell(20, 8, iconv('UTF-8', 'TIS-620', $row['unit']), 1, 1, 'C');
    }
} else { 
    $pdf->Cell(190, 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูล'), 1, 1, 'C'); 
}

ob_end_clean();

// 5. ส่งออกไฟล์
$pdf->Output('D', 'stock_report.pdf');
?>