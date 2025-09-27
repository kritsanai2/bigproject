<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php'; 

// ฟังก์ชันช่วย
function thai_month_name_excel($month) {
    $months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
    return $months[intval($month)] ?? '';
}

// 1. รับค่าตัวกรอง
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0; 
$current_year = intval(date('Y'));
$filter_year = isset($_GET['year']) && intval($_GET['year']) > 0 ? intval($_GET['year']) : $current_year;

$report_period = "";
if ($filter_month > 0) {
    $report_period .= "ประจำเดือน " . thai_month_name_excel($filter_month);
}
if ($filter_year > 0) {
    $report_period .= " ปี พ.ศ. " . ($filter_year + 543);
}

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

// 3. สร้างและส่งออกไฟล์ CSV
$filename = "sales_report_" . date('Ymd') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM (Byte Order Mark) สำหรับ UTF-8
echo "\xEF\xBB\xBF"; 

$output = fopen('php://output', 'w');

// เขียนหัวข้อ
fputcsv($output, ['รายงานการขายรายสินค้า ' . $report_period]);
fputcsv($output, []); // บรรทัดว่าง

// เขียนหัวตาราง
fputcsv($output, ['ลำดับ', 'วันที่', 'ชื่อลูกค้า', 'ชื่อสินค้า', 'จำนวน (หน่วย)', 'ราคา/หน่วย', 'ราคารวม (บาท)']);

// เขียนข้อมูล
$i = 1;
if (!empty($rows)) {
    foreach ($rows as $row) {
        $date_th = date('d/m/', strtotime($row['order_date'])) . (date('Y', strtotime($row['order_date'])) + 543);

        fputcsv($output, [
            $i++, 
            $date_th, 
            $row['customer_name'], 
            $row['product_name'], 
            number_format($row['quantity']) . ' ' . $row['unit'],
            number_format($row['price'], 2), 
            number_format($row['item_total'], 2)
        ]);
    }
    
    // แถวรวม
    fputcsv($output, []);
    fputcsv($output, [
        'ยอดรวมทั้งหมด', 
        '', 
        '',
        '',
        '',
        '',
        number_format($grand_total, 2)
    ]);
}

fclose($output);
exit();
?>