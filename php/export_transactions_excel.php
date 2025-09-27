<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php'; 

// ฟังก์ชันแปลงประเภทและเดือนเป็นไทย
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month_name_excel($month){
    $months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
    return $months[intval($month)] ?? '';
}

// 1. รับค่าตัวกรอง
$type_filter = $_GET['type'] ?? '';
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0; 
$current_year = intval(date('Y'));
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

// 2. สร้างหัวข้อรายงาน
$report_period = "";
if ($filter_month > 0) $report_period .= "ประจำเดือน " . thai_month_name_excel($filter_month);
if ($filter_year > 0) $report_period .= " ปี พ.ศ. " . ($filter_year + 543);
if ($type_filter) $report_period .= " (ประเภท: " . thai_type($type_filter) . ")";
$report_title = 'รายงานรายรับ-รายจ่าย' . $report_period;

// 3. ดึงข้อมูล (แก้ไข SQL)
$sql = "SELECT t.*, od.product_id, p.product_name
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id"; // ไม่ JOIN expense_categories

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
$stmt->execute();
$result_sales = $stmt->get_result();
$rows = $result_sales->fetch_all(MYSQLI_ASSOC);

// คำนวณยอดรวม
$total_income = 0;
$total_expense = 0;
foreach($rows as $row){
    if($row['transaction_type'] === 'income'){ $total_income += $row['amount']; } else { $total_expense += $row['amount']; }
}
$balance = $total_income - $total_expense;


// 4. สร้างและส่งออกไฟล์ CSV
$filename = "transactions_report_" . date('Ymd') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM (Byte Order Mark) สำหรับ UTF-8
echo "\xEF\xBB\xBF"; 

$output = fopen('php://output', 'w');

// เขียนหัวข้อ
fputcsv($output, [$report_title]);
fputcsv($output, []); // บรรทัดว่าง

// เขียนหัวตาราง
fputcsv($output, ['ลำดับ', 'รหัส', 'วันที่', 'ประเภท', 'จำนวนเงิน (บาท)', 'รายละเอียด']);

// เขียนข้อมูล
$i = 1;
if (!empty($rows)) {
    foreach ($rows as $row) {
        // ใช้ $row['expense_type'] ที่มีอยู่ในตาราง transactions โดยตรง
        $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : (!empty($row['product_name']) ? "ขาย: " . $row['product_name'] : 'รายรับจากออเดอร์');
        
        fputcsv($output, [
            $i++, 
            $row['transaction_id'], 
            date('d/m/Y', strtotime($row['transaction_date']) + 543),
            thai_type($row['transaction_type']), 
            number_format($row['amount'], 2), 
            $desc
        ]);
    }
    
    // แถวสรุป
    fputcsv($output, []);
    fputcsv($output, ['สรุปยอดรวม:', '', '', 'รายรับรวม', 'รายจ่ายรวม', 'ยอดคงเหลือ']);
    fputcsv($output, ['', '', '', number_format($total_income, 2), number_format($total_expense, 2), number_format($balance, 2)]);
}

fclose($output);
exit();
?>