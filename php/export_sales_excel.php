<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// 4. สร้างและส่งออกไฟล์ CSV
$filename = "sales_report_" . date('Ymd') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF"; // BOM (Byte Order Mark) สำหรับ UTF-8

$output = fopen('php://output', 'w');

// เขียนหัวข้อ
fputcsv($output, [$report_title]);
fputcsv($output, []); // บรรทัดว่าง

// เขียนหัวตาราง
fputcsv($output, ['ลำดับ', 'วันที่', 'ชื่อลูกค้า', 'ชื่อสินค้า', 'จำนวน', 'หน่วย', 'ราคา/หน่วย', 'ราคารวม (บาท)']);

// เขียนข้อมูล
$i = 1;
if (!empty($sales_data)) {
    foreach ($sales_data as $row) {
        fputcsv($output, [
            $i++, 
            date('d/m/', strtotime($row['order_date'])) . (date('Y', strtotime($row['order_date'])) + 543),
            $row['customer_name'], 
            $row['product_name'], 
            $row['quantity'],
            $row['unit'],
            number_format($row['price'], 2),
            number_format($row['item_total'], 2)
        ]);
    }
    
    // แถวสรุป
    fputcsv($output, []); // บรรทัดว่าง
    fputcsv($output, ['', '', '', '', '', '', 'ยอดรวมทั้งหมด', number_format($grand_total, 2)]);
}

fclose($output);
exit();
?>
