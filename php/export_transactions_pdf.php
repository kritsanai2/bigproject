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
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0;

// 2. สร้างหัวข้อรายงาน
$report_title = "รายงาน";
if ($type_filter == 'income') $report_title .= "รายรับ";
elseif ($type_filter == 'expense') $report_title .= "รายจ่าย";
else $report_title .= "รายรับ-รายจ่าย";
if ($filter_month > 0) $report_title .= " เดือน " . thai_month_name($filter_month);
if ($filter_year > 0) $report_title .= " ปี " . ($filter_year + 543);
if ($filter_month == 0 && $filter_year == 0) $report_title .= "ทั้งหมด";

// 3. ดึงข้อมูลรายการธุรกรรม (SQL เดียวกับ dashboard)
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
$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);


// --- (เพิ่มใหม่) ดึงยอดรวมเงินเดือนจากตาราง `salary` ---
$total_salary_expense = 0;
if ($type_filter === '' || $type_filter === 'expense') {
    $salary_sql = "SELECT SUM(total_amount) as total FROM salary";
    $salary_params = [];
    $salary_types = "";
    $salary_where = [];

    if ($filter_month > 0) {
        $salary_where[] = "MONTH(pay_month) = ?";
        $salary_params[] = $filter_month;
        $salary_types .= "i";
    }
    if ($filter_year > 0) {
        $salary_where[] = "YEAR(pay_month) = ?";
        $salary_params[] = $filter_year;
        $salary_types .= "i";
    }

    if (!empty($salary_where)) {
        $salary_sql .= " WHERE " . implode(" AND ", $salary_where);
    }

    $stmt_salary = $conn->prepare($salary_sql);
    if (!empty($salary_params)) {
        $stmt_salary->bind_param($salary_types, ...$salary_params);
    }
    $stmt_salary->execute();
    $salary_result = $stmt_salary->get_result()->fetch_assoc();
    $total_salary_expense = $salary_result['total'] ?? 0;
}


// --- (แก้ไข) คำนวณยอดรวม ---
$total_income = 0; 
$total_expense = 0;
foreach($rows as $row){
    if($row['transaction_type'] === 'income'){ $total_income += $row['amount']; } else { $total_expense += $row['amount']; }
}

// (แก้ไข) นำยอดรวมเงินเดือนมาบวกเพิ่มเข้าไปในรายจ่าย
$total_expense += $total_salary_expense;

$balance = $total_income - $total_expense;

// 4. สร้างเอกสาร PDF
$pdf = new FPDF('P', 'mm', 'A4'); 
$pdf->AddFont('THSarabunNew', '', 'THSarabunNew.php'); 
$pdf->AddFont('THSarabunNew', 'B', 'THSarabunNew.php');
$pdf->AddPage();
$pdf->SetFont('THSarabunNew', 'B', 18);
$pdf->Cell(0, 10, iconv('UTF-8', 'TIS-620', $report_title), 0, 1, 'C');
$pdf->Ln(5);

// หัวตาราง
$pdf->SetFont('THSarabunNew', 'B', 12);
$pdf->SetFillColor(220, 220, 220);
$header = ['ลำดับ', 'วันที่', 'ประเภท', 'จำนวนเงิน', 'รายละเอียด', 'รหัสสั่งซื้อ'];
$w = [15, 25, 30, 35, 65, 20]; // ความกว้างแต่ละคอลัมน์
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 10, iconv('UTF-8', 'TIS-620', $header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

// เนื้อหาตาราง
$pdf->SetFont('THSarabunNew', '', 12);
$i = 1;

if (!empty($rows)) {
    foreach ($rows as $row) {
        $desc = $row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : ($row['product_name'] ?? '-');
        $date_be = date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543);
        
        $pdf->Cell($w[0], 8, $i++, 1, 0, 'C');
        $pdf->Cell($w[1], 8, $date_be, 1, 0, 'C');
        $pdf->Cell($w[2], 8, iconv('UTF-8', 'TIS-620', thai_type($row['transaction_type'])), 1, 0, 'C');
        $pdf->Cell($w[3], 8, number_format($row['amount'], 2), 1, 0, 'R');
        $pdf->Cell($w[4], 8, iconv('UTF-8', 'TIS-620', $desc), 1, 0, 'L');
        $pdf->Cell($w[5], 8, iconv('UTF-8', 'TIS-620', $row['order_id'] ?? '-'), 1, 1, 'C');
    }
    
    // แถวสรุป (จะใช้ค่าที่คำนวณใหม่โดยอัตโนมัติ)
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(array_sum($w), 0.5, '', 'T', 1); // เส้นคั่น
    $pdf->Cell(70, 8, iconv('UTF-8', 'TIS-620', 'ยอดรวมรายรับ'), 'LBR', 0, 'R');
    $pdf->Cell(120, 8, number_format($total_income, 2) . iconv('UTF-8', 'TIS-620', ' บาท'), 'BR', 1, 'L');
    $pdf->Cell(70, 8, iconv('UTF-8', 'TIS-620', 'ยอดรวมรายจ่าย'), 'LBR', 0, 'R');
    $pdf->Cell(120, 8, number_format($total_expense, 2) . iconv('UTF-8', 'TIS-620', ' บาท'), 'BR', 1, 'L');
    $pdf->Cell(70, 8, iconv('UTF-8', 'TIS-620', 'ยอดคงเหลือ'), 'LBR', 0, 'R');
    $pdf->Cell(120, 8, number_format($balance, 2) . iconv('UTF-8', 'TIS-620', ' บาท'), 'BR', 1, 'L');
    
} else {
    $pdf->Cell(array_sum($w), 10, iconv('UTF-8', 'TIS-620', 'ไม่พบข้อมูลตามเงื่อนไขที่เลือก'), 1, 1, 'C');
}

ob_end_clean();

// 5. ส่งออกไฟล์
$pdf->Output('D', 'transactions_report_' . date('Ymd') . '.pdf');
exit();
?>