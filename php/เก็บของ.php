<?php
// --- เปิดการแสดงข้อผิดพลาด ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ตั้งค่าเวลาสูงสุดในการรันสคริปต์
set_time_limit(300);


// --- FPDF Path และ Autoload ---
define('FPDF_FONTPATH', __DIR__ . '/fonts/');
require '../vendor/autoload.php';
require_once 'db.php';

// --- (2) ฟังก์ชันช่วยเหลือ (เหมือนเดิม) ---
function thai_type($type){
    return strtolower($type) === 'income' ? 'รายรับ' : 'รายจ่าย';
}
function thai_month($month){
    $months = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    return $months[intval($month)] ?? '';
}
// ฟังก์ชันแปลงข้อความไทยสำหรับ FPDF
function utf8_to_tis620($string) {
    return iconv('UTF-8', 'TIS-620', $string);
}


// --- (3) ส่วนของการดึงข้อมูลและประมวลผล (เหมือนเดิมทุกประการ) ---
$type_filter = $_GET['type'] ?? '';
$filter_month = isset($_GET['month']) ? intval($_GET['month']) : 0;
$filter_year  = isset($_GET['year']) && intval($_GET['year']) > 0 ? intval($_GET['year']) : intval(date('Y'));

// --- ดึงข้อมูล Transactions ---
$sql = "SELECT t.*, od.product_id, p.product_name, o.order_id
        FROM transactions t
        LEFT JOIN order_details od ON t.order_detail_id = od.order_detail_id
        LEFT JOIN products p ON od.product_id = p.product_id
        LEFT JOIN orders o ON od.order_id = o.order_id";
$params = []; $types = ""; $where_clauses = [];
if ($type_filter) { $where_clauses[] = "t.transaction_type = ?"; $params[] = $type_filter; $types .= "s"; }
if ($filter_month > 0) { $where_clauses[] = "MONTH(t.transaction_date) = ?"; $params[] = $filter_month; $types .= "i"; }
if ($filter_year > 0) { $where_clauses[] = "YEAR(t.transaction_date) = ?"; $params[] = $filter_year; $types .= "i"; }
if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$transaction_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- ดึงข้อมูลเงินเดือน ---
$salary_rows = [];
if ($type_filter === '' || $type_filter === 'expense') {
    $salary_sql = "SELECT total_amount, pay_month FROM salary";
    $salary_params = []; $salary_types = ""; $salary_where = [];
    if ($filter_month > 0) { $salary_where[] = "MONTH(pay_month) = ?"; $salary_params[] = $filter_month; $salary_types .= "i"; }
    if ($filter_year > 0) { $salary_where[] = "YEAR(pay_month) = ?"; $salary_params[] = $filter_year; $salary_types .= "i"; }
    if (!empty($salary_where)) { $salary_sql .= " WHERE " . implode(" AND ", $salary_where); }
    $stmt_salary = $conn->prepare($salary_sql);
    if (!empty($salary_params)) { $stmt_salary->bind_param($salary_types, ...$salary_params); }
    $stmt_salary->execute();
    $salary_results = $stmt_salary->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($salary_results as $salary) {
        $salary_rows[] = [
            'transaction_id' => 'S' . date('mY', strtotime($salary['pay_month'])),
            'transaction_type' => 'expense', 'amount' => $salary['total_amount'],
            'transaction_date' => $salary['pay_month'],
            'expense_type' => 'เงินเดือนพนักงานเดือน ' . thai_month(date('n', strtotime($salary['pay_month']))),
            'order_id' => null, 'slip_image' => null, 'product_name' => null
        ];
    }
}

// --- รวม, จัดเรียง, และคำนวณยอดรวม ---
$all_rows = array_merge($transaction_rows, $salary_rows);
usort($all_rows, fn($a, $b) => strtotime($b['transaction_date']) - strtotime($a['transaction_date']));
$total_income = array_sum(array_column(array_filter($all_rows, fn($r) => $r['transaction_type'] === 'income'), 'amount'));
$total_expense = array_sum(array_column(array_filter($all_rows, fn($r) => $r['transaction_type'] === 'expense'), 'amount'));
$balance = $total_income - $total_expense;

// --- สร้างหัวข้อรายงาน ---
$report_title_utf8 = "รายงาน";
if ($type_filter == 'income') $report_title_utf8 .= "รายรับ";
elseif ($type_filter == 'expense') $report_title_utf8 .= "รายจ่าย";
else $report_title_utf8 .= "รายรับ-รายจ่าย";
if ($filter_month > 0) $report_title_utf8 .= " เดือน " . thai_month($filter_month);
if ($filter_year > 0) $report_title_utf8 .= " ปี " . ($filter_year + 543);


// --- (4) สร้างเอกสาร PDF ด้วย FPDF ---

class PDF extends FPDF {
    function Header() {
        global $report_title_utf8;
        $this->SetFont('THSarabunNew','B',18);
        $this->Cell(0, 10, utf8_to_tis620($report_title_utf8), 0, 1, 'C');
        $this->Ln(5);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew','',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4'); // แนวนอน, หน่วยเป็นมิลลิเมตร, ขนาด A4
$pdf->AliasNbPages();

// --- เพิ่มฟอนต์ภาษาไทย ---
$pdf->AddFont('THSarabunNew','','THSarabunNew.php');
$pdf->AddFont('THSarabunNew','B','THSarabunNew.php');

$pdf->AddPage();
$pdf->SetFont('THSarabunNew','B',12);

// --- หัวตาราง ---
$pdf->SetFillColor(220, 220, 220);
$header = ['ลำดับ', 'ประเภท', 'จำนวนเงิน', 'วันที่', 'รายละเอียด', 'รหัสสั่งซื้อ', 'รูปภาพ'];
$w = [15, 25, 30, 25, 90, 30, 62]; // ความกว้างคอลัมน์
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 10, utf8_to_tis620($header[$i]), 1, 0, 'C', true);
}
$pdf->Ln();

// --- เนื้อหาตาราง ---
$pdf->SetFont('THSarabunNew','',11);
$pdf->SetFillColor(255, 255, 255);
$i = 1;

if (!empty($all_rows)) {
    foreach ($all_rows as $row) {
        $cellHeight = 25; // กำหนดความสูงของแถว
        
        // เก็บตำแหน่ง Y เริ่มต้นของแถว
        $startY = $pdf->GetY();
        
        // คอลัมน์ที่ไม่ใช่ MultiCell
        $pdf->Cell($w[0], $cellHeight, $i++, 'LR', 0, 'C');
        $pdf->Cell($w[1], $cellHeight, utf8_to_tis620(thai_type($row['transaction_type'])), 'LR', 0, 'C');
        $pdf->Cell($w[2], $cellHeight, number_format($row['amount'], 2), 'LR', 0, 'R');
        $pdf->Cell($w[3], $cellHeight, date('d/m/', strtotime($row['transaction_date'])) . (date('Y', strtotime($row['transaction_date'])) + 543), 'LR', 0, 'C');

        // ตำแหน่ง X สำหรับคอลัมน์ "รายละเอียด"
        $descX = $pdf->GetX();
        $pdf->MultiCell($w[4], 5, utf8_to_tis620($row['transaction_type'] == 'expense' ? ($row['expense_type'] ?? '-') : ($row['product_name'] ?? '-')), 0, 'L');
        
        // วาดเส้นขอบสำหรับ MultiCell
        $pdf->Rect($descX, $startY, $w[4], $cellHeight);
        
        // ย้ายตำแหน่งกลับมาในแถวเดิม
        $pdf->SetXY($descX + $w[4], $startY);
        
        $pdf->Cell($w[5], $cellHeight, $row['order_id'] ?? '-', 'LR', 0, 'C');

        // ==================== START: CORRECTED IMAGE HANDLING ====================

        // FIX 1: Use null coalescing operator (?? '') to prevent ltrim warning
        $imagePath = '../' . ltrim($row['slip_image'] ?? '', '/');

        // Check if the path is valid and the file exists
        if (!empty($row['slip_image']) && file_exists($imagePath)) {
            // FIX 2: Manually set the type for JFIF images
            $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $imageType = ($ext == 'jfif') ? 'JPEG' : ''; // Let FPDF auto-detect other types

            try {
                 // The last parameter ($imageType) tells FPDF how to handle the file
                $pdf->Image($imagePath, $pdf->GetX() + 2, $startY + 1, $w[6] - 4, $cellHeight - 2, $imageType);
                $pdf->Cell($w[6], $cellHeight, '', 'LR', 0, 'C');
            } catch (Exception $e) {
                // If image fails to load, show an error text instead of crashing
                $pdf->Cell($w[6], $cellHeight, utf8_to_tis620('[Image Error]'), 'LR', 0, 'C');
            }
        } else {
            // If there is no image, print a dash
            $pdf->Cell($w[6], $cellHeight, '-', 'LR', 0, 'C');
        }
        
        // ===================== END: CORRECTED IMAGE HANDLING =====================
        
        $pdf->Ln($cellHeight);
        // วาดเส้นใต้บรรทัด
        $pdf->Cell(array_sum($w), 0, '', 'T', 1);
    }
} else {
    //... (the rest of your code is fine)
    $pdf->Cell(array_sum($w), 10, utf8_to_tis620('ไม่พบข้อมูลตามเงื่อนไขที่เลือก'), 1, 1, 'C');
}

// --- ส่วนสรุปยอด ---
$pdf->SetFont('THSarabunNew','B',12);
$pdf->Ln(5);
$pdf->Cell(array_sum(array_slice($w, 0, 6)), 10, utf8_to_tis620('ยอดรวมรายรับ'), 1, 0, 'R');
$pdf->Cell($w[6], 10, number_format($total_income, 2), 1, 1, 'R');
$pdf->Cell(array_sum(array_slice($w, 0, 6)), 10, utf8_to_tis620('ยอดรวมรายจ่าย'), 1, 0, 'R');
$pdf->Cell($w[6], 10, number_format($total_expense, 2), 1, 1, 'R');
$pdf->Cell(array_sum(array_slice($w, 0, 6)), 10, utf8_to_tis620('ยอดคงเหลือสุทธิ'), 1, 0, 'R');
$pdf->Cell($w[6], 10, number_format($balance, 2), 1, 1, 'R');


// --- (5) ส่งออกไฟล์ PDF ---
$filename = "transaction_report_" . date('Ymd') . ".pdf";
$pdf->Output('I', $filename); // 'I' แสดงในเบราว์เซอร์, 'D' สำหรับดาวน์โหลด
exit();
?>