<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Path สำหรับ FPDF และฟอนต์
define('FPDF_FONTPATH', __DIR__ . '/fonts/'); 
require '../vendor/autoload.php';
require_once 'db.php';

// ฟังก์ชันแปลงเลขเดือนเป็นชื่อเดือนภาษาไทย
function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}

// 1. รับค่าตัวกรอง (เหมือนใน dashboard)
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filter_year  = isset($_GET['year']) ? intval($_GET['year']) : 0;

// 2. สร้างหัวข้อรายงานแบบ Dynamic
$report_title = "รายงานการขาย";
if ($filter_month > 0) $report_title .= " เดือน " . thai_month($filter_month);
if ($filter_year > 0) $report_title .= " ปี " . ($filter_year + 543);
if ($filter_month == 0 && $filter_year == 0) $report_title .= "ทั้งหมด";


// 3. ดึงข้อมูล (SQL และ Logic เดียวกับ dashboard)
$sql = "
    SELECT 
        o.order_date, c.full_name AS customer_name, p.product_name, p.unit,
        od.quantity, od.price, (od.quantity * od.price) AS item_total
    FROM order_details od
    JOIN orders o ON od.order_id = o.order_id
    JOIN customers c ON o.customer_id = c.customer_id
    JOIN products p ON od.product_id = p.product_id
";

$params = []; $types = ""; $where_clauses = [];
if ($filter_month > 0) { $where_clauses[] = "MONTH(o.order_date) = ?"; $params[] = $filter_month; $types .= "i"; }
if ($filter_year > 0) { $where_clauses[] = "YEAR(o.order_date) = ?"; $params[] = $filter_year; $types .= "i"; }
if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
$sql .= " ORDER BY o.order_date DESC, o.order_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
$sales_data = $result->fetch_all(MYSQLI_ASSOC);

// คำนวณยอดรวม
$grand_total = 0;
foreach ($sales_data as $row) {
    $grand_total += $row['item_total'];
}

// 4. สร้างเอกสาร PDF
$pdf = new FPDF('L'); // ตั้งค่าแนวนอน
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
$pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
$pdf->AddPage();
$pdf->SetFont('THSarabunNew', 'B', 18);
$pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
$pdf->Ln(5);

// หัวตาราง
$pdf->SetFont('THSarabunNew', 'B', 12);
$pdf->SetFillColor(220, 220, 220);
$header = ['ลำดับ', 'วันที่', 'ชื่อลูกค้า', 'ชื่อสินค้า', 'จำนวน', 'ราคา/หน่วย', 'ราคารวม (บาท)'];
$w = [15, 30, 60, 60, 30, 30, 40]; // ความกว้างแต่ละคอลัมน์

for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

// เนื้อหาตาราง
$pdf->SetFont('THSarabunNew', '', 12);
$i = 1;

if (!empty($sales_data)) {
    foreach ($sales_data as $row) {
        $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
        $pdf->Cell($w[1], 8, date('d/m/', strtotime($row['order_date'])) . (date('Y', strtotime($row['order_date'])) + 543), 1, 0, 'C');
        $pdf->Cell($w[2], 8, iconv('UTF-8', 'TIS-620', $row['customer_name']), 1, 0, 'L');
        $pdf->Cell($w[3], 8, iconv('UTF-8', 'TIS-620', $row['product_name']), 1, 0, 'L');
        $pdf->Cell($w[4], 8, number_format($row['quantity']) . ' ' . iconv('UTF-8', 'TIS-620', $row['unit']), 1, 0, 'R');
        $pdf->Cell($w[5], 8, number_format($row['price'], 2), 1, 0, 'R');
        $pdf->Cell($w[6], 8, number_format($row['item_total'], 2), 1, 1, 'R');
    }
    // แถวสรุป
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(array_sum(array_slice($w, 0, 6)), 10, iconv('UTF-8', 'TIS-620', 'ยอดรวมทั้งหมด'), 1, 0, 'R');
    $pdf->Cell($w[6], 10, number_format($grand_total, 2), 1, 1, 'R');

} else {
    $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลการขายตามเงื่อนไขที่เลือก'), 1, 1, 'C');
}

ob_end_clean();

// 5. ส่งออกไฟล์
$pdf->Output('D', 'sales_report_' . date('Ymd') . '.pdf');
exit();
?>
