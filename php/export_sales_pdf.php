<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path สำหรับ FPDF และฟอนต์
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require '../vendor/autoload.php';
require_once 'db.php';

use FPDF;

// ฟังก์ชันช่วย
function thai_month_name($month) {
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}

// 1. รับค่าตัวกรอง
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0; 
$current_year = intval(date('Y'));
$filter_year = isset($_GET['year']) && intval($_GET['year']) > 0 ? intval($_GET['year']) : $current_year;

$report_period = "";
if ($filter_month > 0) {
    $report_period .= "ประจำเดือน " . thai_month($filter_month);
}
if ($filter_year > 0) {
    $report_period .= " ปี พ.ศ. " . ($filter_year + 543);
}
$report_title = 'รายงานการขายรายสินค้า ' . $report_period;


// 2. ดึงข้อมูล (ใช้ Prepared Statements)
$sql_detailed_sales = "
    SELECT 
        o.order_date, c.full_name AS customer_name, p.product_name, p.unit,
        od.quantity, od.price, (od.quantity * od.price) AS item_total
    FROM order_details od
    JOIN orders o ON od.order_id = o.order_id
    JOIN customers c ON o.customer_id = c.customer_id
    JOIN products p ON od.product_id = p.product_id
";

$params = [];
$types = "";
$where_clauses = [];

if ($filter_month > 0) { $where_clauses[] = "MONTH(o.order_date) = ?"; $params[] = $filter_month; $types .= "i"; }
if ($filter_year > 0) { $where_clauses[] = "YEAR(o.order_date) = ?"; $params[] = $filter_year; $types .= "i"; }

if (!empty($where_clauses)) {
    $sql_detailed_sales .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql_detailed_sales .= " ORDER BY o.order_date DESC, o.order_id DESC";

$stmt = $conn->prepare($sql_detailed_sales);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result_sales = $stmt->get_result();

$rows = [];
$grand_total = 0;
if($result_sales) {
    while($row = $result_sales->fetch_assoc()) {
        $rows[] = $row;
        $grand_total += $row['item_total'];
    }
}


// 3. สร้างเอกสาร PDF
$pdf = new FPDF('P'); 
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
$pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
$pdf->AddPage();

// หัวข้อรายงาน
$pdf->SetFont('THSarabunNew', 'B', 18);
$pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
$pdf->Ln(2);

// หัวตาราง
$pdf->SetFont('THSarabunNew', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$header = ['ลำดับ', 'วันที่', 'ลูกค้า', 'สินค้า', 'จำนวน', 'ราคา/หน่วย', 'ราคารวม (บาท)'];
$w = [15, 25, 45, 45, 20, 20, 20]; // ความกว้างแต่ละคอลัมน์
$align = ['C', 'C', 'L', 'L', 'R', 'R', 'R'];

for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 8, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

// เนื้อหาตาราง
$pdf->SetFont('THSarabunNew', '', 10);
$i = 1;
if (!empty($rows)) {
    foreach ($rows as $row) {
        $pdf->Cell($w[0], 7, $i++, 1, 0, 'C');
        $date_th = date('d/m/', strtotime($row['order_date'])) . (date('Y', strtotime($row['order_date'])) + 543);
        $pdf->Cell($w[1], 7, $date_th, 1, 0, 'C');
        $pdf->Cell($w[2], 7, iconv('UTF-8', 'TIS-620', $row['customer_name']), 1, 0, 'L');
        $pdf->Cell($w[3], 7, iconv('UTF-8', 'TIS-620', $row['product_name']), 1, 0, 'L');
        $pdf->Cell($w[4], 7, number_format($row['quantity']) . ' ' . iconv('UTF-8', 'TIS-620', $row['unit']), 1, 0, 'R');
        $pdf->Cell($w[5], 7, number_format($row['price'], 2), 1, 0, 'R');
        $pdf->Cell($w[6], 7, number_format($row['item_total'], 2), 1, 1, 'R');
    }
    
    // แถวรวม
    $pdf->SetFont('THSarabunNew', 'B', 10);
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell(array_sum($w) - $w[6], 8, iconv('UTF-8', 'TIS-620', 'ยอดรวมทั้งหมด'), 1, 0, 'R', true);
    $pdf->Cell($w[6], 8, number_format($grand_total, 2), 1, 1, 'R', true);
    
} else {
    $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลการขายในเดือนที่เลือก'), 1, 1, 'C');
}

ob_end_clean();

// 4. ส่งออกไฟล์
$pdf->Output('D', 'sales_report_' . date('Ymd') . '.pdf');
exit();
?>