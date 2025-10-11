<?php
// กำหนดให้ PHP แสดงข้อผิดพลาดทั้งหมดสำหรับการดีบัก
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php'; 

// ฟังก์ชันแปลงประเภทและเดือนเป็นไทย
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month_name($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}

// 1. รับค่าตัวกรอง
$type_filter = $_GET['type'] ?? '';
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0; 
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0;

// 2. สร้างหัวข้อรายงาน
$report_title = "รายงาน";
if ($type_filter == 'income') $report_title .= "รายรับ";
elseif ($type_filter == 'expense') $report_title .= "รายจ่าย";
else $report_title .= "รายรับ-รายจ่าย";
if ($filter_month > 0) $report_title .= " เดือน " . thai_month_name($filter_month);
if ($filter_year > 0) $report_title .= " ปี " . ($filter_year + 543);
if ($filter_month == 0 && $filter_year == 0 && empty($type_filter)) $report_title .= "ทั้งหมด";

// 3. ดึงข้อมูลรายการธุรกรรม
$sql = "SELECT t.*, od.product_id, p.product_name, o.order_id
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id
        LEFT JOIN orders o ON od.order_id = o.order_id";

$params = [];
$types = "";
$where_clauses = [];

if ($type_filter) { $where_clauses[] = "t.transaction_type = ?"; $params[] = $type_filter; $types .= "s"; }
if ($filter_month > 0) { $where_clauses[] = "MONTH(t.transaction_date) = ?"; $params[] = $filter_month; $types .= "i"; }
if ($filter_year > 0) { $where_clauses[] = "YEAR(t.transaction_date) = ?"; $params[] = $filter_year; $types .= "i"; }

if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
// หมายเหตุ: การเรียงลำดับจะทำหลังจากรวมข้อมูลแล้ว

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$transaction_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


// --- (แก้ไข) ดึงข้อมูลเงินเดือนทีละรายการ ไม่ใช่ยอดรวม ---
$salary_rows = [];
if ($type_filter === '' || $type_filter === 'expense') {
    // 1. แก้ SQL ให้ดึงข้อมูลมาทีละแถว
    $salary_sql = "SELECT total_amount, pay_month FROM salary";
    $salary_params = [];
    $salary_types = "";
    $salary_where = [];

    if ($filter_month > 0) { $salary_where[] = "MONTH(pay_month) = ?"; $salary_params[] = $filter_month; $salary_types .= "i"; }
    if ($filter_year > 0) { $salary_where[] = "YEAR(pay_month) = ?"; $salary_params[] = $filter_year; $salary_types .= "i"; }

    if (!empty($salary_where)) { $salary_sql .= " WHERE " . implode(" AND ", $salary_where); }

    $stmt_salary = $conn->prepare($salary_sql);
    if (!empty($salary_params)) { $stmt_salary->bind_param($salary_types, ...$salary_params); }
    $stmt_salary->execute();
    $salary_results = $stmt_salary->get_result()->fetch_all(MYSQLI_ASSOC);

    // 2. สร้าง Array ใหม่ให้มีโครงสร้างเหมือน transaction
    foreach($salary_results as $salary) {
        $salary_rows[] = [
            'transaction_type' => 'expense',
            'amount' => $salary['total_amount'],
            'transaction_date' => $salary['pay_month'],
            'expense_type' => 'เงินเดือนพนักงานเดือน ' . thai_month_name(date('n', strtotime($salary['pay_month']))),
            'product_name' => null,
            'order_id' => null
        ];
    }
}

// --- (เพิ่มใหม่) รวมและเรียงลำดับข้อมูลทั้งหมด ---
// 3. รวม Array ทั้งสองเข้าด้วยกัน
$all_rows = array_merge($transaction_rows, $salary_rows);

// 4. เรียงข้อมูลทั้งหมดตามวันที่จากใหม่ไปเก่า
usort($all_rows, function($a, $b) {
    return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
});


// --- (แก้ไข) คำนวณยอดรวมจากข้อมูลที่รวมกันแล้ว ---
$total_income = 0; 
$total_expense = 0;
foreach($all_rows as $row){ // <-- ใช้ $all_rows
    if($row['transaction_type'] === 'income'){ $total_income += $row['amount']; } else { $total_expense += $row['amount']; }
}
$balance = $total_income - $total_expense;


// 4. สร้างและส่งออกไฟล์ CSV
$filename = "transactions_report_" . date('Ymd') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "\xEF\xBB\xBF"; 
$output = fopen('php://output', 'w');

fputcsv($output, [$report_title]);
fputcsv($output, []); 
fputcsv($output, ['ลำดับ', 'ประเภท', 'จำนวนเงิน (บาท)', 'วันที่', 'รายละเอียด', 'รหัสสั่งซื้อ']);

// --- (แก้ไข) เขียนข้อมูลจาก Array ที่รวมแล้ว ---
$i = 1;
if (!empty($all_rows)) { // <-- ใช้ $all_rows
    foreach ($all_rows as $row) { // <-- ใช้ $all_rows
        $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? ($row['product_name'] ?? '-')) : ($row['product_name'] ?? '-');
        $date_be = date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543);
        
        fputcsv($output, [
            $i++, 
            thai_type($row['transaction_type']), 
            number_format($row['amount'], 2), 
            $date_be,
            $desc,
            $row['order_id'] ?? '-'
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['สรุปยอดรวม']);
    fputcsv($output, ['ยอดรวมรายรับ', number_format($total_income, 2)]);
    fputcsv($output, ['ยอดรวมรายจ่าย', number_format($total_expense, 2)]);
    fputcsv($output, ['ยอดคงเหลือ', number_format($balance, 2)]);
} else {
    // เพิ่มกรณีไม่พบข้อมูลเลย
    fputcsv($output, ['-- ไม่พบข้อมูลตามเงื่อนไขที่เลือก --']);
}

fclose($output);
exit();
?>