<?php
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

// 3. ดึงข้อมูลจากฐานข้อมูล (ใช้ Prepared Statement)
$sql = "SELECT s.stock_date, p.product_name, s.stock_type, s.quantity, p.unit FROM stock s JOIN products p ON s.product_id = p.product_id";

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


// 4. สร้างและส่งออกไฟล์ CSV
$filename = "stock_report_" . date('Ymd') . ".csv";

header('Content-Type: text/csv; charset=utf-8'); 
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF"; 

$output = fopen('php://output', 'w');

// เขียนหัวข้อรายงาน
fputcsv($output, [$report_title]);
fputcsv($output, []); // บรรทัดว่าง

// เขียนหัวตาราง
fputcsv($output, ['ลำดับ', 'วันที่', 'สินค้า', 'ประเภท', 'จำนวน', 'หน่วย']);

// เขียนข้อมูล
if (!empty($rows)) {
    $i = 1;
    foreach ($rows as $row) {
        fputcsv($output, [
            $i++, 
            date('d/m/', strtotime($row['stock_date'])) . (date('Y', strtotime($row['stock_date'])) + 543),
            $row['product_name'],
            thai_type_text($row['stock_type']),
            $row['quantity'], 
            $row['unit']
        ]);
    }
    // แถวสรุป
    fputcsv($output, []); // บรรทัดว่าง
    fputcsv($output, ['ยอดรวมรับเข้า', $total_import]);
    fputcsv($output, ['ยอดรวมจ่ายออก', $total_remove]);
}

fclose($output);
exit();
?>
