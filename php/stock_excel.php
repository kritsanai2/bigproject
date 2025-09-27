<?php
// 1. เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล
require_once 'db.php';

// ฟังก์ชันช่วย
function thai_type_text($type) { 
    return strtolower($type) == 'import' ? 'รับเข้า' : 'จ่ายออก'; 
}

// 2. รับค่าตัวกรองจาก URL
$type_filter = $_GET['type'] ?? ''; 
$month_filter = $_GET['month'] ?? 0; 
$year_filter = $_GET['year'] ?? date('Y');

// 3. ดึงข้อมูลจากฐานข้อมูล
$sql = "SELECT s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit FROM stock s JOIN products p ON s.product_id = p.product_id";

$where_clauses = [];
if ($type_filter) $where_clauses[] = "s.stock_type = '" . $conn->real_escape_string($type_filter) . "'";
if ($month_filter > 0) $where_clauses[] = "MONTH(s.stock_date) = " . (int)$month_filter;
if ($year_filter) $where_clauses[] = "YEAR(s.stock_date) = " . (int)$year_filter;

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY s.stock_date DESC, s.stock_id DESC";
$result = $conn->query($sql);

// 4. สร้างและส่งออกไฟล์ CSV สำหรับ Excel
$filename = "stock_report_" . date('Ymd') . ".csv";

// ตั้งค่า Header: ใช้ UTF-8
header('Content-Type: text/csv; charset=utf-8'); 
header('Content-Disposition: attachment; filename="' . $filename . '"');

// ** BOM (Byte Order Mark) สำหรับ UTF-8 ** (จำเป็นเพื่อให้ Excel รุ่นใหม่รู้จัก UTF-8)
echo "\xEF\xBB\xBF"; 

$output = fopen('php://output', 'w');

// เขียนหัวตาราง (ใช้ข้อมูล UTF-8 ดั้งเดิม)
fputcsv($output, ['ลำดับ', 'วันที่', 'สินค้า', 'ประเภท', 'จำนวน', 'หน่วย']);

// เขียนข้อมูล (ใช้ข้อมูล UTF-8 ดั้งเดิม ไม่ต้องใช้ iconv)
if ($result && $result->num_rows > 0) {
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $i++, 
            date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543),
            $row['product_name'], // ข้อมูล UTF-8
            thai_type_text($row['stock_type']), // ข้อมูล UTF-8
            $row['quantity'], 
            $row['unit'] // ข้อมูล UTF-8
        ]);
    }
}

fclose($output);
exit();
?>